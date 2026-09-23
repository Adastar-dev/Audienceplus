<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Audience extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_audience';

    protected $fillable = [
        'id_dossier', 'id_juge', 'id_salle', 'date_heure', 'mode', 'statut',
        'type_decision', 'motif_decision', 'juge_connecte',
    ];

    protected $casts = [
        'date_heure' => 'datetime',
        'juge_connecte' => 'boolean',
    ];

    public function dossier()
    {
        return $this->belongsTo(Dossier::class, 'id_dossier', 'id_dossier');
    }

    public function juge()
    {
        return $this->belongsTo(Utilisateur::class, 'id_juge', 'id_utilisateur');
    }

    public function salle()
    {
        return $this->belongsTo(SalleVirtuelle::class, 'id_salle', 'id_salle');
    }

    public function participations()
    {
        return $this->hasMany(ParticipationAudience::class, 'id_audience', 'id_audience');
    }

    public function convocations()
    {
        return $this->hasMany(Convocation::class, 'id_audience', 'id_audience');
    }

    public function procesVerbal()
    {
        return $this->hasOne(ProcesVerbal::class, 'id_audience', 'id_audience');
    }

    public function demandeDistances()
    {
        return $this->hasMany(DemandeDistance::class, 'id_audience', 'id_audience');
    }

    public function getRoomNameAttribute(): string
    {
        return "audience-{$this->id_audience}";
    }

    public static function marquerRatees(): void
    {
        static::where('statut', 'PROGRAMMEE')
            ->where('date_heure', '<', now())
            ->update(['statut' => 'RATEE']);
    }
}
