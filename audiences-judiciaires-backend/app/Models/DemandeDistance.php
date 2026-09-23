<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemandeDistance extends Model
{
    use HasFactory;

    protected $table = 'demandes_distance';
    protected $primaryKey = 'id_demande';

    protected $fillable = [
        'id_audience', 'id_utilisateur', 'motif', 'statut',
        'reponse_greffier', 'date_demande', 'date_traitement',
        'avis_greffier', 'avis_greffier_par', 'avis_greffier_date',
        'commentaire_juge', 'decide_par',
    ];

    protected $casts = [
        'date_demande' => 'datetime',
        'date_traitement' => 'datetime',
        'avis_greffier_date' => 'datetime',
    ];

    public function audience()
    {
        return $this->belongsTo(Audience::class, 'id_audience', 'id_audience');
    }

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_utilisateur', 'id_utilisateur');
    }

    public function greffierQuiADonneAvis()
    {
        return $this->belongsTo(Utilisateur::class, 'avis_greffier_par', 'id_utilisateur');
    }

    public function jugeQuiADecide()
    {
        return $this->belongsTo(Utilisateur::class, 'decide_par', 'id_utilisateur');
    }
}
