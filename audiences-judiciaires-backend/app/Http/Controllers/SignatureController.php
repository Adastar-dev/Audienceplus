<?php

namespace App\Http\Controllers;

use App\Models\Signature;
use App\Services\DocumentHashService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'signature_image' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $contenu = $this->hasher->contenuAHacher($request->type_document, $request->id_document_signe);

        if ($contenu === null) {
            return response()->json(['message' => 'Document introuvable.'], 404);
        }

        $cheminImage = null;
        if ($request->hasFile('signature_image')) {
            $mime = $request->file('signature_image')->getMimeType();
            if (str_starts_with($mime, 'image/')) {
                $cheminImage = $request->file('signature_image')->store('signatures-manuscrites', 'local');
            }
        }

        $signature = Signature::create([
            'id_utilisateur' => $request->user()->id_utilisateur,
            'type_document' => $request->type_document,
            'id_document_signe' => $request->id_document_signe,
            'hash' => hash('sha256', $contenu),
            'image_path' => $cheminImage,
            'date_signature' => now(),
        ]);

        return response()->json($signature->load('utilisateur'), 201);
    }

    public function image(Signature $signature)
    {
        if (! $signature->image_path || ! Storage::disk('local')->exists($signature->image_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($signature->image_path);
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