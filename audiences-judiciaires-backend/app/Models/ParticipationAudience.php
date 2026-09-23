<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParticipationAudience extends Model
{
    use HasFactory;

    protected $table = 'participations_audience';
    protected $primaryKey = 'id_participation';

    protected $fillable = [
        'id_audience', 'id_utilisateur', 'role_audience',
        'micro_actif', 'camera_active', 'selfie_path', 'cni_photo_path',
        'numero_cni_detecte', 'numero_cni_concorde', 'present', 'admis',
        'otp_code_hash', 'otp_expire_a', 'otp_tentatives', 'identite_confirmee_otp',
    ];

    protected $hidden = ['otp_code_hash'];

    protected $casts = [
        'micro_actif' => 'boolean',
        'camera_active' => 'boolean',
        'numero_cni_concorde' => 'boolean',
        'present' => 'boolean',
        'admis' => 'boolean',
        'otp_expire_a' => 'datetime',
        'identite_confirmee_otp' => 'boolean',
    ];

    public function audience()
    {
        return $this->belongsTo(Audience::class, 'id_audience', 'id_audience');
    }

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_utilisateur', 'id_utilisateur');
    }
}
