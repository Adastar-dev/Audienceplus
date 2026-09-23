<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->notifications()->latest('date_envoi')->get()
        );
    }

    public function accuserReception(Request $request, Notification $notification)
    {
        if ($notification->id_utilisateur !== $request->user()->id_utilisateur) {
            return response()->json(['message' => "Cette notification ne vous appartient pas."], 403);
        }

        $notification->update(['lu' => true]);

        return response()->json($notification);
    }
}