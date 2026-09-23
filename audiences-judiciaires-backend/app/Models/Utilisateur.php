<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Utilisateur extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'utilisateurs';
    protected $primaryKey = 'id_utilisateur';

    protected $fillable = [
        'nom', 'email', 'mot_de_passe', 'role', 'telephone',
        'cni', 'numero_barreau', 'identite_verifiee', 'id_tribunal',
        'tentatives_echouees', 'bloque_jusqu_a',
    ];

    protected $hidden = ['mot_de_passe', 'remember_token'];

    protected $casts = [
        'identite_verifiee' => 'boolean',
        'bloque_jusqu_a' => 'datetime',
    ];

    public function getAuthPassword()
    {
        return $this->mot_de_passe;
    }

    public function estBloque(): bool
    {
        return $this->bloque_jusqu_a !== null && $this->bloque_jusqu_a->isFuture();
    }

    public function tribunal()
    {
        return $this->belongsTo(Tribunal::class, 'id_tribunal', 'id_tribunal');
    }

    public function piecesDeposees()
    {
        return $this->hasMany(Piece::class, 'depose_par', 'id_utilisateur');
    }

    public function participations()
    {
        return $this->hasMany(ParticipationAudience::class, 'id_utilisateur', 'id_utilisateur');
    }

    public function convocations()
    {
        return $this->hasMany(Convocation::class, 'id_utilisateur', 'id_utilisateur');
    }

    public function signatures()
    {
        return $this->hasMany(Signature::class, 'id_utilisateur', 'id_utilisateur');
    }

    public function casiersJudiciaires()
    {
        return $this->hasMany(CasierJudiciaire::class, 'id_utilisateur', 'id_utilisateur');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'id_utilisateur', 'id_utilisateur');
    }

    public function audiencesJugees()
    {
        return $this->hasMany(Audience::class, 'id_juge', 'id_utilisateur');
    }
}
