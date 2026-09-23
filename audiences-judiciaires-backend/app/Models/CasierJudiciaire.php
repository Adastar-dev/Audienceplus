<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CasierJudiciaire extends Model
{
    use HasFactory;

    protected $table = 'casiers_judiciaires';
    protected $primaryKey = 'id_casier';

    protected $fillable = ['id_utilisateur', 'resultat', 'qr_code', 'fichier_pdf', 'date_demandee'];

    protected $casts = [
        'date_demandee' => 'datetime',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_utilisateur', 'id_utilisateur');
    }
}
