<?php

namespace App\Http\Controllers;

use App\Models\Audience;
use App\Models\ParticipationAudience;
use App\Models\Signature;
use App\Services\CniOcrService;
use App\Services\DocumentHashService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ParticipationController extends Controller
{
    public function __construct(
        private OtpService $otp,
        private CniOcrService $cniOcr,
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

    public function enregistrerSelfie(Request $request, Audience $audience)
    {
        $validator = Validator::make($request->all(), [
            'selfie' => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mime = $request->file('selfie')->getMimeType();
        if (! str_starts_with($mime, 'image/')) {
            return response()->json(['errors' => ['selfie' => ["Le fichier n'est pas une image valide."]]], 422);
        }

        $participation = ParticipationAudience::firstOrCreate(
            ['id_audience' => $audience->id_audience, 'id_utilisateur' => $request->user()->id_utilisateur],
            ['role_audience' => $request->user()->role]
        );

        $chemin = $request->file('selfie')->store('selfies-verification', 'local');
        $participation->update(['selfie_path' => $chemin]);

        if ($participation->cni_photo_path) {
            $this->comparerPhotos($participation);
        }

        return response()->json($participation);
    }

    public function enregistrerCni(Request $request, Audience $audience)
    {
        $validator = Validator::make($request->all(), [
            'cni' => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mime = $request->file('cni')->getMimeType();
        if (! str_starts_with($mime, 'image/')) {
            return response()->json(['errors' => ['cni' => ["Le fichier n'est pas une image valide."]]], 422);
        }

        $participation = ParticipationAudience::firstOrCreate(
            ['id_audience' => $audience->id_audience, 'id_utilisateur' => $request->user()->id_utilisateur],
            ['role_audience' => $request->user()->role]
        );

        $chemin = $request->file('cni')->store('cni-verification', 'local');
        $participation->update(['cni_photo_path' => $chemin]);

        if ($participation->selfie_path) {
            $this->comparerPhotos($participation);
        }

        return response()->json($participation);
    }

    // Verifie que le numero OCR de la CNI correspond au CNI declare a
    // l'inscription — un indicateur pour le greffier/juge, jamais un blocage
    // automatique (l'OCR se trompe facilement sur une photo prise au telephone).
    // La comparaison faciale (Face++) a ete retiree au profit d'un code OTP par
    // SMS, voir envoyerOtp/verifierOtp ci-dessous.
    private function comparerPhotos(ParticipationAudience $participation): void
    {
        $numeroDetecte = $this->cniOcr->extraireNumero(Storage::disk('local')->path($participation->cni_photo_path));
        $numeroDeclare = $participation->utilisateur->cni ?? null;

        $participation->update([
            'numero_cni_detecte' => $numeroDetecte,
            'numero_cni_concorde' => ($numeroDetecte !== null && $numeroDeclare !== null)
                ? $numeroDetecte === $numeroDeclare
                : null,
        ]);
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

    // Verification d'identite par code OTP envoye par SMS, condition d'entree
    // dans la salle d'attente virtuelle (App\Services\OtpService).
    public function envoyerOtp(Request $request, Audience $audience)
    {
        if ($refus = $this->refuserSiHorsFenetre($audience)) {
            return $refus;
        }

        $participation = ParticipationAudience::firstOrCreate(
            ['id_audience' => $audience->id_audience, 'id_utilisateur' => $request->user()->id_utilisateur],
            ['role_audience' => $request->user()->role]
        );

        if (! $this->otp->envoyer($participation)) {
            return response()->json([
                'message' => "Aucun numéro de téléphone n'est associé à votre compte.",
            ], 422);
        }

        return response()->json(['message' => 'Code envoyé par SMS.']);
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
            return response()->json(['message' => $messages[$resultat]], 422);
        }

        return response()->json($participation->fresh());
    }

    public function selfie(Request $request, Audience $audience, ParticipationAudience $participation)
    {
        $utilisateur = $request->user();
        $autorise = in_array($utilisateur->role, ['GREFFIER', 'JUGE', 'ADMINISTRATEUR'], true)
            || $utilisateur->id_utilisateur === $participation->id_utilisateur;

        if (! $autorise) {
            abort(403);
        }

        if (! $participation->selfie_path || ! Storage::disk('local')->exists($participation->selfie_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($participation->selfie_path);
    }

    public function cniPhoto(Request $request, Audience $audience, ParticipationAudience $participation)
    {
        $utilisateur = $request->user();
        $autorise = in_array($utilisateur->role, ['GREFFIER', 'JUGE', 'ADMINISTRATEUR'], true)
            || $utilisateur->id_utilisateur === $participation->id_utilisateur;

        if (! $autorise) {
            abort(403);
        }

        if (! $participation->cni_photo_path || ! Storage::disk('local')->exists($participation->cni_photo_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($participation->cni_photo_path);
    }
}