<?php

namespace App\Http\Controllers;

use App\Models\SalleVirtuelle;
use App\Models\Tribunal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TribunalController extends Controller
{
    public function index()
    {
        return response()->json(Tribunal::withCount('sallesVirtuelles')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'ville' => 'required|string|max:255',
            'salles_virtuelles' => 'required|integer|min:1|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tribunal = Tribunal::create($request->only('nom', 'ville'));

        for ($i = 1; $i <= $request->salles_virtuelles; $i++) {
            SalleVirtuelle::create([
                'id_tribunal' => $tribunal->id_tribunal,
                'lien_jitsi' => "meet.jit.si/aj-{$tribunal->id_tribunal}-salle{$i}",
                'code_acces' => str()->random(8),
            ]);
        }

        return response()->json($tribunal->load('sallesVirtuelles'), 201);
    }
}