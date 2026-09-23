<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Convocation extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_convocation';

    protected $fillable = [
        'id_audience', 'id_utilisateur', 'canal', 'statut', 'motif_report', 'reponse_greffier', 'date_envoi',
        'avis_greffier', 'nouvelle_date_proposee', 'avis_greffier_par', 'avis_greffier_date', 'decide_par',
    ];

    protected $casts = [
        'date_envoi' => 'datetime',
        'nouvelle_date_proposee' => 'datetime',
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
