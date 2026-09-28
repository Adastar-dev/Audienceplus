<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Utilisateur extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'utilisateurs';
    protected $primaryKey = 'id_utilisateur';

    protected $fillable = [
        'nom', 'email', 'mot_de_passe', 'role', 'telephone',
        'cni', 'numero_barreau', 'identite_verifiee', 'id_tribunal',
        'tentatives_echouees', 'bloque_jusqu_a',
        'cni_photo_path', 'cni_verso_path', 'numero_cni_detecte', 'numero_cni_concorde',
    ];

    protected $hidden = ['mot_de_passe', 'remember_token', 'cni_photo_path', 'cni_verso_path'];

    protected $casts = [
        'identite_verifiee' => 'boolean',
        'bloque_jusqu_a' => 'datetime',
        'numero_cni_concorde' => 'boolean',
    ];

    // Le chemin du fichier reste caché ; l'interface sait seulement si la
    // photo de la CNI a été déposée.
    protected $appends = ['a_photo_cni'];

    public function getAPhotoCniAttribute(): bool
    {
        // Recto et verso déposés. Un compte déjà vérifié (avant l'ajout du
        // verso) n'a pas à redéposer sa carte.
        return (! empty($this->cni_photo_path) && ! empty($this->cni_verso_path)) || (bool) $this->identite_verifiee;
    }

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
}
