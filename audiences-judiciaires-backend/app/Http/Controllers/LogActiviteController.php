<?php

namespace App\Http\Controllers;

use App\Models\LogActivite;

class LogActiviteController extends Controller
{
    public function index()
    {
        return response()->json(
            LogActivite::with('utilisateur')->latest('date')->latest('id_log')->limit(500)->get()
        );
    }
}