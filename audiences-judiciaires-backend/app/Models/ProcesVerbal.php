<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcesVerbal extends Model
{
    use HasFactory;

    protected $table = 'proces_verbaux';
    protected $primaryKey = 'id_pv';

    protected $fillable = [
        'id_audience', 'contenu', 'fichier_pdf', 'statut', 'date_validation',
        'commentaire_rejet', 'transcription_brute', 'audio_path',
        'avis_procureur', 'avis_procureur_par', 'avis_procureur_date',
        'contestation_avocat', 'conteste_par', 'date_contestation',
    ];

    protected $casts = [
        'date_validation' => 'datetime',
        'avis_procureur_date' => 'datetime',
        'date_contestation' => 'datetime',
    ];

    public function audience()
    {
        return $this->belongsTo(Audience::class, 'id_audience', 'id_audience');
    }

    public function procureurQuiADonneAvis()
    {
        return $this->belongsTo(Utilisateur::class, 'avis_procureur_par', 'id_utilisateur');
    }

    public function avocatQuiConteste()
    {
        return $this->belongsTo(Utilisateur::class, 'conteste_par', 'id_utilisateur');
    }

    public function signatures()
    {
        return Signature::where('type_document', 'PROCES_VERBAL')
            ->where('id_document_signe', $this->id_pv)
            ->get();
    }
}
