<?php

namespace App\Http\Controllers;

use App\Models\CasierJudiciaire;
use Illuminate\Http\Request;

class CasierJudiciaireController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->casiersJudiciaires()->latest('date_demandee')->get()
        );
    }

    public function store(Request $request)
    {
        $casier = CasierJudiciaire::create([
            'id_utilisateur' => $request->user()->id_utilisateur,
            'resultat' => 'VIERGE',
            'qr_code' => 'AJ-CJ-'.now()->format('Y').'-'.str_pad((string) (CasierJudiciaire::count() + 1), 6, '0', STR_PAD_LEFT),
            'date_demandee' => now(),
        ]);

        return response()->json($casier, 201);
    }
}