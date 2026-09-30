<?php

namespace App\Http\Controllers;

use App\Models\Audience;
use App\Models\Convocation;
use App\Models\Dossier;
use App\Models\ParticipationAudience;
use App\Models\SalleVirtuelle;
use App\Models\Utilisateur;
use App\Services\DisponibiliteJuge;
use App\Services\JitsiTokenService;
use App\Services\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use App\Support\Messages;

class AudienceController extends Controller
{
    private const PAS_DE_SALLE_VIRTUELLE = "Cette audience se tient en présentiel : aucune comparution à distance n'a été accordée.";

    public function __construct(private NotificationDispatcher $notifications)
    {
    }

    public function index(Request $request)
    {
        $utilisateur = $request->user();
        // Chacun ne voit que les audiences des dossiers auxquels il a accès.
        $query = Audience::with(['dossier', 'juge', 'procesVerbal'])
            ->whereHas('dossier', fn ($q) => $q->visiblesPar($utilisateur));

        if ($utilisateur->role === 'JUGE') {
            $query->where('id_juge', $utilisateur->id_utilisateur);
        }

        return response()->json($query->orderBy('date_heure')->get());
    }

    public function show(Request $request, Audience $audience)
    {
        if (! $audience->dossier->estAccessiblePar($request->user())) {
            return response()->json(['message' => "Vous n'avez pas accès à cette audience."], 403);
        }

        $audience->refresh();

        return response()->json($audience->load([
            'dossier.pieces', 'dossier.procureurAssigne', 'dossier.procureurQuiADonneAvis',
            'juge', 'salle', 'participations.utilisateur', 'procesVerbal',
        ]));
    }

    public function store(Request $request, DisponibiliteJuge $disponibilite)
    {
        $validator = Validator::make($request->all(), [
            'id_dossier' => 'required|exists:dossiers,id_dossier',
            'id_juge' => 'nullable|exists:utilisateurs,id_utilisateur',
            'date_heure' => 'required|date|after:now',
            // Une audience est toujours programmee en presentiel : l'acces a distance
            // passe par une demande du justiciable (DemandeDistanceController).
            'mode' => 'nullable|in:PRESENTIEL',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (Dossier::find($request->id_dossier)->estArchive()) {
            return response()->json(['message' => 'Ce dossier est archivé : aucune audience ne peut y être programmée.'], 409);
        }

        $dateHeure = Carbon::parse($request->date_heure);
        if ($request->id_juge && ! $disponibilite->estDisponible((int) $request->id_juge, $dateHeure)) {
            return $disponibilite->reponseIndisponible((int) $request->id_juge, $dateHeure);
        }

        $audience = Audience::create([
            'id_dossier' => $request->id_dossier,
            'id_juge' => $request->id_juge,
            'date_heure' => $request->date_heure,
            'mode' => 'PRESENTIEL',
            'statut' => 'PROGRAMMEE',
        ]);

        $this->convoquerParties($audience);
        $this->prevenirJugeEtProcureur($audience);

        return response()->json($audience->load('dossier'), 201);
    }

    private function prevenirJugeEtProcureur(Audience $audience): void
    {
        $audience->loadMissing('dossier');
        $message = Messages::audienceProgrammee($audience);

        // Le procureur n'est prévenu que pour un dossier communiqué au ministère public.
        $procureur = Dossier::communiqueAuParquet($audience->dossier->type) ? $audience->dossier->id_procureur : null;

        foreach (array_filter([$audience->id_juge, $procureur]) as $idDestinataire) {
            if ($destinataire = Utilisateur::find($idDestinataire)) {
                $this->notifications->envoyerMessage($destinataire, $message);
            }
        }
    }

    private function convoquerParties(Audience $audience): void
    {
        $dossier = $audience->dossier;

        foreach ($dossier->utilisateursAConvoquer() as $utilisateur) {
            $convocation = Convocation::create([
                'id_audience' => $audience->id_audience,
                'id_utilisateur' => $utilisateur->id_utilisateur,
                'canal' => 'EMAIL',
                'statut' => 'ENVOYEE',
                'date_envoi' => now(),
            ]);

            $this->notifications->envoyerConvocation($convocation);
        }
    }

    public function ouvrir(Request $request, Audience $audience)
    {
        if ($refus = $this->refuserSiPasLeJuge($request, $audience)) {
            return $refus;
        }

        $audience->refresh();

        if ($audience->statut !== 'PROGRAMMEE') {
            return response()->json([
                'message' => $audience->statut === 'RATEE'
                    ? "Cette audience est passée sans avoir été ouverte et ne peut plus l'être."
                    : "Cette audience ne peut plus être ouverte (statut actuel : {$audience->statut}).",
            ], 409);
        }

        // Pas d'ouverture anticipée : une audience commence à son heure.
        if (now()->lt($audience->heureOuverture())) {
            return response()->json([
                'message' => "Cette audience ne peut être ouverte qu'à partir du {$audience->heureOuverture()->format('d/m/Y à H:i')}.",
            ], 409);
        }

        if ($audience->salle_virtuelle && ! $audience->id_salle) {
            $salle = $this->attribuerSalleDisponible($audience->dossier->id_tribunal);
            if ($salle) {
                $audience->id_salle = $salle->id_salle;
            }
        }

        $audience->statut = 'EN_COURS';
        $audience->save();

        return response()->json($audience->load('salle'));
    }

    // Appele par la salle du juge une fois la conference Jitsi rejointe (juge
    // moderateur) puis a sa sortie : les autres participants attendent ce signal
    // avant de charger Jitsi, sinon le premier arrive deviendrait moderateur.
    public function jugeConnecte(Request $request, Audience $audience)
    {
        return $this->definirPresenceJuge($request, $audience, true);
    }

    public function jugeDeconnecte(Request $request, Audience $audience)
    {
        return $this->definirPresenceJuge($request, $audience, false);
    }

    // Jeton d'acces a la salle Jitsi : modérateur pour le juge de l'audience,
    // simple participant pour les autres, et seulement une fois le juge entre.
    public function jetonJitsi(Request $request, Audience $audience, JitsiTokenService $jitsi)
    {
        $utilisateur = $request->user();
        $audience->refresh();

        if ($audience->statut !== 'EN_COURS') {
            return response()->json(['message' => "L'audience n'est pas en cours."], 409);
        }

        if (! $audience->salle_virtuelle) {
            return response()->json(['message' => self::PAS_DE_SALLE_VIRTUELLE], 409);
        }

        $estJuge = $utilisateur->role === 'JUGE'
            && (! $audience->id_juge || (int) $audience->id_juge === (int) $utilisateur->id_utilisateur);

        $participantDistant = in_array($utilisateur->role, ['JUSTICIABLE', 'AVOCAT', 'PROCUREUR'], true);

        if (! $estJuge) {
            if (! $audience->dossier->estAccessiblePar($utilisateur)) {
                return response()->json(['message' => "Vous n'avez pas accès à cette audience."], 403);
            }

            if ($participantDistant && ! $audience->peutEtreRejointeADistancePar($utilisateur)) {
                return response()->json(['message' => "Aucune comparution à distance ne vous a été accordée pour cette audience."], 403);
            }

            if (! $audience->juge_connecte) {
                return response()->json(['message' => "Le juge n'a pas encore ouvert la salle."], 409);
            }

            // Justiciable, avocat et procureur : code OTP confirmé dans la salle
            // d'attente, puis admission par le greffier ou le juge. Contrôlé ici,
            // et pas seulement par l'interface. Le greffier n'y est pas soumis.
            if (in_array($utilisateur->role, ['JUSTICIABLE', 'AVOCAT', 'PROCUREUR'], true)) {
                $participation = ParticipationAudience::where('id_audience', $audience->id_audience)
                    ->where('id_utilisateur', $utilisateur->id_utilisateur)
                    ->first();

                if (! $participation || ! $participation->identite_confirmee_otp) {
                    return response()->json([
                        'message' => "Confirmez d'abord votre identité avec le code de vérification, dans la salle d'attente.",
                    ], 403);
                }

                if (! $participation->admis) {
                    return response()->json([
                        'message' => "Vous êtes dans la salle d'attente : le greffier ou le juge va vous faire entrer.",
                        'en_attente_admission' => true,
                    ], 409);
                }
            }
        }

        return response()->json([
            'domaine' => $jitsi->domaine(),
            'salle' => $jitsi->nomSalle($audience),
            'jwt' => $jitsi->genererJeton($audience, $utilisateur, $estJuge),
            'moderateur' => $estJuge,
        ]);
    }

    private function definirPresenceJuge(Request $request, Audience $audience, bool $connecte)
    {
        if ($refus = $this->refuserSiPasLeJuge($request, $audience)) {
            return $refus;
        }

        if ($connecte && $audience->statut !== 'EN_COURS') {
            return response()->json(['message' => "L'audience doit être ouverte avant d'entrer dans la salle."], 409);
        }

        if ($connecte && ! $audience->salle_virtuelle) {
            return response()->json(['message' => self::PAS_DE_SALLE_VIRTUELLE], 409);
        }

        $audience->update(['juge_connecte' => $connecte]);

        return response()->json($audience);
    }

    private function attribuerSalleDisponible(int $idTribunal): ?SalleVirtuelle
    {
        $sallesOccupees = Audience::where('statut', 'EN_COURS')
            ->whereNotNull('id_salle')
            ->pluck('id_salle');

        return SalleVirtuelle::where('id_tribunal', $idTribunal)
            ->whereNotIn('id_salle', $sallesOccupees)
            ->first();
    }

    public function fermer(Request $request, Audience $audience)
    {
        if ($refus = $this->refuserSiPasLeJuge($request, $audience)) {
            return $refus;
        }

        $audience->refresh();

        if ($audience->statut !== 'EN_COURS') {
            return response()->json(['message' => "Seule une audience en cours peut être fermée."], 409);
        }

        // Une audience ne se ferme pas sans décision : sinon le dossier reste
        // sans issue et aucune décision n'est communiquée aux parties.
        if (! $audience->type_decision) {
            return response()->json([
                'message' => "Enregistrez d'abord la décision (jugement, renvoi ou mise en délibéré) avant de fermer l'audience.",
            ], 422);
        }

        $audience->update(['statut' => 'CLOTUREE', 'juge_connecte' => false]);

        if ($audience->type_decision === 'JUGEMENT') {
            $audience->dossier()->update(['statut' => 'JUGE']);
            $this->notifierDecision($audience, 'JUGEMENT');
        }

        return response()->json($audience->load('dossier'));
    }

    public function decider(Request $request, Audience $audience)
    {
        if ($refus = $this->refuserSiPasLeJuge($request, $audience)) {
            return $refus;
        }

        if ($audience->statut !== 'EN_COURS') {
            return response()->json(['message' => "L'audience doit être en cours pour enregistrer une décision."], 409);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:JUGEMENT,RENVOI,DELIBERE',
            'motif' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $audience->type_decision = $request->type;
        $audience->motif_decision = $request->motif;

        if ($request->type === 'RENVOI') {
            $audience->statut = 'RENVOYEE';
            $audience->dossier()->update(['statut' => 'RENVOYE']);
        } elseif ($request->type === 'DELIBERE') {
            $audience->statut = 'DELIBERE';
        }

        if ($audience->statut !== 'EN_COURS') {
            $audience->juge_connecte = false;
        }

        $audience->save();

        if ($request->type === 'RENVOI') {
            $this->notifierDecision($audience, 'RENVOI');
        } elseif ($request->type === 'DELIBERE') {
            $this->notifierDecision($audience, 'DELIBERE');
        }

        return response()->json($audience->load('dossier'));
    }

    private function notifierDecision(Audience $audience, string $typeDecision): void
    {
        $audience->loadMissing('dossier');
        $message = Messages::decision($audience, $typeDecision);

        foreach ($audience->dossier->utilisateursAConvoquer() as $utilisateur) {
            $this->notifications->envoyerMessage($utilisateur, $message);
        }
    }

    // Seul le juge de l'audience (tout juge si aucun n'est désigné) la conduit :
    // l'ouvrir, décider, la fermer, entrer dans la salle.
    private function refuserSiPasLeJuge(Request $request, Audience $audience)
    {
        if (! $audience->estGereePar($request->user())) {
            return response()->json(['message' => "Vous n'êtes pas le juge de cette audience."], 403);
        }

        return null;
    }

    // Admission depuis la salle d'attente : le juge de l'audience, ou un greffier
    // du tribunal du dossier.
    private function refuserSiPasDeLAudience(Request $request, Audience $audience, ParticipationAudience $participation)
    {
        if ((int) $participation->id_audience !== (int) $audience->id_audience) {
            abort(404);
        }

        if (! $audience->estGereePar($request->user())) {
            return response()->json(['message' => "Vous n'avez pas accès à cette audience."], 403);
        }

        return null;
    }

    public function admettreParticipant(Request $request, Audience $audience, ParticipationAudience $participation)
    {
        if ($refus = $this->refuserSiPasDeLAudience($request, $audience, $participation)) {
            return $refus;
        }

        $participation->update(['admis' => true]);

        return response()->json($participation);
    }

    public function refuserParticipant(Request $request, Audience $audience, ParticipationAudience $participation)
    {
        if ($refus = $this->refuserSiPasDeLAudience($request, $audience, $participation)) {
            return $refus;
        }

        // Refus : la personne sort de la salle d'attente et devra refaire la
        // vérification par code pour se représenter.
        $participation->update(['admis' => false, 'present' => false, 'identite_confirmee_otp' => false]);

        return response()->json($participation);
    }
}