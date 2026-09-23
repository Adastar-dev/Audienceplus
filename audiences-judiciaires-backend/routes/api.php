<?php

use App\Http\Controllers\AudienceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CasierJudiciaireController;
use App\Http\Controllers\ConvocationController;
use App\Http\Controllers\DemandeDistanceController;
use App\Http\Controllers\DossierController;
use App\Http\Controllers\LogActiviteController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParticipationController;
use App\Http\Controllers\PieceController;
use App\Http\Controllers\ProcesVerbalController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\TribunalController;
use App\Http\Controllers\UtilisateurController;
use Illuminate\Support\Facades\Route;

Route::post('/inscription', [AuthController::class, 'inscription'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/utilisateur', [AuthController::class, 'utilisateurConnecte']);

    Route::get('/dossiers', [DossierController::class, 'index']);
    Route::get('/dossiers/{dossier}', [DossierController::class, 'show']);
    Route::post('/dossiers', [DossierController::class, 'store'])->middleware('role:GREFFIER');
    Route::patch('/dossiers/{dossier}', [DossierController::class, 'update'])->middleware('role:GREFFIER,JUGE');
    Route::patch('/dossiers/{dossier}/avis', [DossierController::class, 'donnerAvis'])->middleware('role:PROCUREUR');

    Route::get('/dossiers/{dossier}/pieces', [PieceController::class, 'index']);
    Route::post('/dossiers/{dossier}/pieces', [PieceController::class, 'store'])
        ->middleware('role:GREFFIER,AVOCAT,JUSTICIABLE');
    Route::post('/pieces/{piece}/valider', [PieceController::class, 'valider'])->middleware('role:GREFFIER');
    Route::delete('/pieces/{piece}', [PieceController::class, 'destroy'])->middleware('role:GREFFIER,AVOCAT,JUSTICIABLE');
    Route::get('/pieces/{piece}/telecharger', [PieceController::class, 'telecharger']);

    Route::get('/audiences', [AudienceController::class, 'index']);
    Route::get('/audiences/{audience}', [AudienceController::class, 'show']);
    Route::post('/audiences', [AudienceController::class, 'store'])->middleware('role:GREFFIER');
    Route::post('/audiences/{audience}/ouvrir', [AudienceController::class, 'ouvrir'])->middleware('role:JUGE');
    Route::post('/audiences/{audience}/fermer', [AudienceController::class, 'fermer'])->middleware('role:JUGE');
    Route::post('/audiences/{audience}/renvoyer', [AudienceController::class, 'renvoyer'])->middleware('role:JUGE');
    Route::post('/audiences/{audience}/decider', [AudienceController::class, 'decider'])->middleware('role:JUGE');
    Route::post('/audiences/{audience}/participants/{participation}/admettre', [AudienceController::class, 'admettreParticipant'])
        ->middleware('role:JUGE');
    Route::post('/audiences/{audience}/participants/{participation}/refuser', [AudienceController::class, 'refuserParticipant'])
        ->middleware('role:JUGE');

    Route::get('/audiences/{audience}/participants', [ParticipationController::class, 'index']);
    Route::post('/audiences/{audience}/verification-identite/selfie', [ParticipationController::class, 'enregistrerSelfie'])->middleware('throttle:10,1');
    Route::post('/audiences/{audience}/verification-identite/cni', [ParticipationController::class, 'enregistrerCni'])->middleware('throttle:10,1');
    Route::post('/audiences/{audience}/verification-identite/otp/envoyer', [ParticipationController::class, 'envoyerOtp'])->middleware('throttle:3,1');
    Route::post('/audiences/{audience}/verification-identite/otp/verifier', [ParticipationController::class, 'verifierOtp'])->middleware('throttle:5,1');
    Route::get('/audiences/{audience}/participants/{participation}/selfie', [ParticipationController::class, 'selfie']);
    Route::get('/audiences/{audience}/participants/{participation}/cni', [ParticipationController::class, 'cniPhoto']);
    Route::post('/audiences/{audience}/participants/{participation}/marquer-present', [ParticipationController::class, 'marquerPresent'])
        ->middleware('role:GREFFIER');
    Route::post('/audiences/{audience}/participants/{participation}/marquer-absent', [ParticipationController::class, 'marquerAbsent'])
        ->middleware('role:GREFFIER');

    Route::get('/audiences/{audience}/pv', [ProcesVerbalController::class, 'show']);
    Route::put('/audiences/{audience}/pv', [ProcesVerbalController::class, 'storeOrUpdate'])->middleware('role:GREFFIER');
    Route::post('/audiences/{audience}/pv/transcrire', [ProcesVerbalController::class, 'transcrire'])->middleware(['role:GREFFIER', 'throttle:10,1']);
    Route::post('/audiences/{audience}/pv/transmettre', [ProcesVerbalController::class, 'transmettre'])->middleware('role:GREFFIER');
    Route::post('/audiences/{audience}/pv/valider', [ProcesVerbalController::class, 'valider'])->middleware('role:JUGE');
    Route::post('/audiences/{audience}/pv/rejeter', [ProcesVerbalController::class, 'rejeter'])->middleware('role:JUGE');
    Route::post('/audiences/{audience}/pv/avis', [ProcesVerbalController::class, 'donnerAvis'])->middleware('role:PROCUREUR');
    Route::post('/audiences/{audience}/pv/contester', [ProcesVerbalController::class, 'contester'])->middleware('role:AVOCAT');

    Route::post('/signatures', [SignatureController::class, 'store']);
    Route::get('/signatures/document', [SignatureController::class, 'pourDocument']);
    Route::get('/signatures/verifier', [SignatureController::class, 'verifierIntegrite']);
    Route::get('/signatures/{signature}/image', [SignatureController::class, 'image']);

    Route::get('/convocations', [ConvocationController::class, 'index']);
    Route::post('/convocations/{convocation}/confirmer', [ConvocationController::class, 'confirmer'])
        ->middleware('role:JUSTICIABLE,AVOCAT');
    Route::post('/convocations/{convocation}/demander-report', [ConvocationController::class, 'demanderReport'])
        ->middleware('role:JUSTICIABLE,AVOCAT');
    Route::post('/convocations/{convocation}/relancer', [ConvocationController::class, 'relancer'])->middleware('role:GREFFIER');
    Route::post('/convocations/{convocation}/avis-report', [ConvocationController::class, 'donnerAvisReport'])
        ->middleware('role:GREFFIER');
    Route::post('/convocations/{convocation}/approuver-report', [ConvocationController::class, 'approuverReport'])
        ->middleware('role:JUGE');
    Route::post('/convocations/{convocation}/refuser-report', [ConvocationController::class, 'refuserReport'])
        ->middleware('role:JUGE');

    Route::get('/demandes-distance', [DemandeDistanceController::class, 'index']);
    Route::post('/demandes-distance', [DemandeDistanceController::class, 'store'])
        ->middleware('role:JUSTICIABLE,AVOCAT');
    Route::post('/demandes-distance/{demande}/avis', [DemandeDistanceController::class, 'donnerAvis'])
        ->middleware('role:GREFFIER');
    Route::post('/demandes-distance/{demande}/approuver', [DemandeDistanceController::class, 'approuver'])
        ->middleware('role:JUGE');
    Route::post('/demandes-distance/{demande}/refuser', [DemandeDistanceController::class, 'refuser'])
        ->middleware('role:JUGE');

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/accuser-reception', [NotificationController::class, 'accuserReception']);

    Route::get('/casier-judiciaire', [CasierJudiciaireController::class, 'index'])->middleware('role:JUSTICIABLE');
    Route::post('/casier-judiciaire', [CasierJudiciaireController::class, 'store'])->middleware('role:JUSTICIABLE');

    Route::middleware('role:ADMINISTRATEUR')->group(function () {
        Route::get('/utilisateurs', [UtilisateurController::class, 'index']);
        Route::post('/utilisateurs', [UtilisateurController::class, 'store']);
        Route::patch('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'update']);
        Route::delete('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'destroy']);
        Route::get('/comptes-a-verifier', [UtilisateurController::class, 'comptesAVerifier']);
        Route::post('/utilisateurs/{utilisateur}/verifier-identite', [UtilisateurController::class, 'verifierIdentite']);

        Route::post('/tribunaux', [TribunalController::class, 'store']);

        Route::get('/logs', [LogActiviteController::class, 'index']);
    });

    Route::get('/tribunaux', [TribunalController::class, 'index']);

    Route::get('/juges', [UtilisateurController::class, 'juges']);
    Route::get('/greffiers', [UtilisateurController::class, 'greffiers']);
    Route::get('/procureurs', [UtilisateurController::class, 'procureurs']);

    Route::get('/justiciables', [UtilisateurController::class, 'justiciables'])->middleware('role:GREFFIER');
    Route::get('/avocats', [UtilisateurController::class, 'avocats'])->middleware('role:GREFFIER');
    Route::get('/mes-clients', [UtilisateurController::class, 'mesClients'])->middleware('role:AVOCAT');
    Route::get('/mes-avocats', [UtilisateurController::class, 'mesAvocats'])->middleware('role:JUSTICIABLE');

    Route::get('/messages', [MessageController::class, 'index'])->middleware('role:GREFFIER,JUGE,PROCUREUR,AVOCAT,JUSTICIABLE');
    Route::post('/messages', [MessageController::class, 'store'])->middleware('role:GREFFIER,JUGE,PROCUREUR,AVOCAT,JUSTICIABLE');
});
