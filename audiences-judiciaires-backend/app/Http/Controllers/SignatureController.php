<?php

namespace App\Http\Controllers;

use App\Models\Convocation;
use App\Models\ParticipationAudience;
use App\Models\ProcesVerbal;
use App\Models\Signature;
use App\Models\Utilisateur;
use App\Services\DocumentHashService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SignatureController extends Controller
{
    public function __construct(private DocumentHashService $hasher)
    {
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type_document' => 'required|in:PROCES_VERBAL,CONVOCATION,PARTICIPATION',
            'id_document_signe' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $contenu = $this->hasher->contenuAHacher($request->type_document, $request->id_document_signe);

        if ($contenu === null) {
            return response()->json(['message' => 'Document introuvable.'], 404);
        }

        if ($refus = $this->refuserSiNonAutorise($request->user(), $request->type_document, (int) $request->id_document_signe)) {
            return $refus;
        }

        $signature = Signature::create([
            'id_utilisateur' => $request->user()->id_utilisateur,
            'type_document' => $request->type_document,
            'id_document_signe' => $request->id_document_signe,
            'hash' => hash('sha256', $contenu),
            'date_signature' => now(),
        ]);

        return response()->json($signature->load('utilisateur'), 201);
    }

    // Qui peut sceller quoi : le PV par le juge de l'audience, une fois transmis
    // par le greffier ; une convocation ou une participation par la personne
    // concernée ou par le greffier.
    private function refuserSiNonAutorise(Utilisateur $utilisateur, string $typeDocument, int $idDocument)
    {
        if ($typeDocument === 'PROCES_VERBAL') {
            $pv = ProcesVerbal::with('audience')->find($idDocument);
            $audience = $pv?->audience;
            $estJugeDeLAudience = $utilisateur->role === 'JUGE'
                && $audience
                && (! $audience->id_juge || (int) $audience->id_juge === (int) $utilisateur->id_utilisateur);

            if (! $estJugeDeLAudience) {
                return response()->json(['message' => "Seul le juge de l'audience peut signer ce procès-verbal."], 403);
            }

            if (! in_array($pv->statut, ['EN_VALIDATION', 'CLOTURE'], true)) {
                return response()->json(['message' => "Le procès-verbal doit avoir été transmis au juge avant d'être signé."], 422);
            }

            return null;
        }

        $document = $typeDocument === 'CONVOCATION'
            ? Convocation::find($idDocument)
            : ParticipationAudience::find($idDocument);

        $autorise = $utilisateur->role === 'GREFFIER'
            || ($document && (int) $document->id_utilisateur === (int) $utilisateur->id_utilisateur);

        return $autorise ? null : response()->json(['message' => "Vous ne pouvez pas signer ce document."], 403);
    }

    public function pourDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type_document' => 'required|in:PROCES_VERBAL,CONVOCATION,PARTICIPATION',
            'id_document_signe' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $signatures = Signature::where('type_document', $request->type_document)
            ->where('id_document_signe', $request->id_document_signe)
            ->with('utilisateur')
            ->get();

        return response()->json($signatures);
    }

    public function verifierIntegrite(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type_document' => 'required|in:PROCES_VERBAL,CONVOCATION,PARTICIPATION',
            'id_document_signe' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $contenuActuel = $this->hasher->contenuAHacher($request->type_document, $request->id_document_signe);
        $hashActuel = $contenuActuel !== null ? hash('sha256', $contenuActuel) : null;

        $signatures = Signature::where('type_document', $request->type_document)
            ->where('id_document_signe', $request->id_document_signe)
            ->with('utilisateur')
            ->get()
            ->map(fn (Signature $s) => [
                'id_signature' => $s->id_signature,
                'utilisateur' => $s->utilisateur,
                'date_signature' => $s->date_signature,
                'integrite_preservee' => $hashActuel !== null && hash_equals($s->hash, $hashActuel),
            ]);

        return response()->json($signatures);
    }
}