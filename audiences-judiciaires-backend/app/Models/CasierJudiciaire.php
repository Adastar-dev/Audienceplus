<?php

namespace App\Models;

use App\Support\CodeQr;
use Illuminate\Database\Eloquent\Model;

class CasierJudiciaire extends Model
{

    protected $table = 'casiers_judiciaires';
    protected $primaryKey = 'id_casier';

    protected $fillable = ['id_utilisateur', 'resultat', 'qr_code', 'fichier_pdf', 'date_demandee'];

    protected $casts = [
        'date_demandee' => 'datetime',
    ];

    // QR code de la référence du casier (unique), affiché à l'utilisateur.
    protected $appends = ['qr_image'];

    public function getQrImageAttribute(): ?string
    {
        return $this->qr_code ? CodeQr::dataUri($this->qr_code) : null;
    }

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_utilisateur', 'id_utilisateur');
    }
}
