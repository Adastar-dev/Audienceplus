<?php

namespace App\Http\Controllers;

use App\Models\Audience;
use App\Models\LogActivite;
use App\Models\ParticipationAudience;
use App\Models\Signature;
use App\Services\DocumentHashService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ParticipationController extends Controller
{
    public function __construct(
        private OtpService $otp,
        private DocumentHashService $hasher,
    ) {
    }

    public function index(Audience $audience)
    {
        $existantes = ParticipationAudience::where('id_audience', $audience->id_audience)->count();

        if ($existantes === 0) {
            if ($audience->id_juge) {
                ParticipationAudience::firstOrCreate([
                    'id_audience' => $audience->id_audience,
                    'id_utilisateur' => $audience->id_juge,
                ], ['role_audience' => 'JUGE']);
            }

            foreach ($audience->convocations as $convocation) {
                ParticipationAudience::firstOrCreate([
                    'id_audience' => $audience->id_audience,
                    'id_utilisateur' => $convocation->id_utilisateur,
                ], ['role_audience' => $convocation->utilisateur->role ?? 'JUSTICIABLE']);
            }
        }

        return response()->json(
            ParticipationAudience::where('id_audience', $audience->id_audience)
                ->with('utilisateur')
                ->get()
        );
    }

    public function marquerPresent(Request $request, Audience $audience, ParticipationAudience $participation)
    {
        $participation->update(['present' => true]);

        $contenu = $this->hasher->contenuAHacher('PARTICIPATION', $participation->id_participation);

        $signature = Signature::create([
            'id_utilisateur' => $request->user()->id_utilisateur,
            'type_document' => 'PARTICIPATION',
            'id_document_signe' => $participation->id_participation,
            'hash' => hash('sha256', $contenu),
            'date_signature' => now(),
        ]);

        return response()->json([
            'participation' => $participation,
            'signature' => $signature->load('utilisateur'),
        ]);
    }

    public function marquerAbsent(Audience $audience, ParticipationAudience $participation)
    {
        $participation->update(['present' => false]);

        return response()->json($participation);
    }

    private const OUVERTURE_AVANT_MINUTES = 30;

    // La salle d'attente virtuelle n'est accessible que dans une fenetre autour
    // de l'heure programmee : 30 minutes avant, jusqu'a ce que l'audience se
    // termine (cloturee, renvoyee, ou marquee ratee automatiquement si l'heure
    // est largement depassee - voir Audience::marquerRatees).
    private function refuserSiHorsFenetre(Audience $audience)
    {
        // Le modele a pu etre resolu avant que le middleware MarquerAudiencesRatees
        // (execute plus loin dans le pipeline) ne mette a jour son statut en base.
        $audience->refresh();

        if (in_array($audience->statut, ['CLOTUREE', 'RENVOYEE', 'RATEE'], true)) {
            return response()->json(['message' => 'Cette audience est terminée.'], 403);
        }

        $ouverture = $audience->date_heure->copy()->subMinutes(self::OUVERTURE_AVANT_MINUTES);

        if (now()->lt($ouverture)) {
            return response()->json([
                'message' => "L'accès à la salle d'attente n'est pas encore ouvert. Revenez à partir de {$ouverture->format('H:i')}.",
            ], 403);
        }

        return null;
    }

    // Verification d'identite par code OTP envoye par email, condition d'entree
    // dans la salle d'attente virtuelle (App\Services\OtpService).
    public function envoyerOtp(Request $request, Audience $audience)
    {
        if ($refus = $this->refuserSiHorsFenetre($audience)) {
            return $refus;
        }

        // Avant tout envoi : partie au dossier, compte vérifié et comparution à
        // distance accordée (voir Audience::peutEtreRejointeADistancePar).
        if (in_array($request->user()->role, ['JUSTICIABLE', 'AVOCAT', 'PROCUREUR'], true)
            && ! $audience->peutEtreRejointeADistancePar($request->user())) {
            return response()->json([
                'message' => "Aucune comparution à distance ne vous a été accordée pour cette audience.",
            ], 403);
        }

        $participation = ParticipationAudience::firstOrCreate(
            ['id_audience' => $audience->id_audience, 'id_utilisateur' => $request->user()->id_utilisateur],
            ['role_audience' => $request->user()->role]
        );

        if (! $this->otp->envoyer($participation)) {
            return response()->json([
                'message' => "Aucune adresse email n'est associée à votre compte. Demandez à l'administrateur d'en ajouter une pour recevoir le code.",
            ], 422);
        }

        return response()->json(['message' => 'Code envoyé par email.']);
    }

    public function verifierOtp(Request $request, Audience $audience)
    {
        if ($refus = $this->refuserSiHorsFenetre($audience)) {
            return $refus;
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $participation = ParticipationAudience::where('id_audience', $audience->id_audience)
            ->where('id_utilisateur', $request->user()->id_utilisateur)
            ->first();

        $resultat = $participation ? $this->otp->verifier($participation, $request->code) : 'aucun_code';

        $messages = [
            'aucun_code' => "Aucun code n'a été envoyé. Demandez-en un nouveau.",
            'expire' => 'Ce code a expiré. Demandez-en un nouveau.',
            'trop_de_tentatives' => 'Trop de tentatives. Demandez un nouveau code.',
            'invalide' => 'Code incorrect.',
        ];

        if ($resultat !== 'ok') {
            LogActivite::create([
                'id_utilisateur' => $request->user()->id_utilisateur,
                'action' => "Code de vérification refusé ({$resultat}) - audience n°{$audience->id_audience}",
                'adresse_ip' => $request->ip(),
            ]);

            return response()->json(['message' => $messages[$resultat]], 422);
        }

        return response()->json($participation->fresh());
    }
}
