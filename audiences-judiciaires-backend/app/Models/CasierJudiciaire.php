<?php

namespace App\Models;

use App\Support\CodeQr;
use App\Support\Verification;
use Illuminate\Database\Eloquent\Model;

class CasierJudiciaire extends Model
{

    protected $table = 'casiers_judiciaires';
    protected $primaryKey = 'id_casier';

    protected $fillable = ['id_utilisateur', 'resultat', 'qr_code', 'fichier_pdf', 'date_demandee'];

    protected $casts = [
        'date_demandee' => 'datetime',
    ];

    // QR code menant à la page publique de vérification de l'extrait.
    protected $appends = ['qr_image', 'url_verification'];

    public function getQrImageAttribute(): ?string
    {
        return $this->qr_code ? CodeQr::dataUri(Verification::url($this->qr_code)) : null;
    }

    public function getUrlVerificationAttribute(): ?string
    {
        return $this->qr_code ? Verification::url($this->qr_code) : null;
    }

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_utilisateur', 'id_utilisateur');
    }
}
