<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Audience extends Model
{

    protected $primaryKey = 'id_audience';

    protected $fillable = [
        'id_dossier', 'id_juge', 'id_salle', 'date_heure', 'mode', 'statut',
        'type_decision', 'motif_decision', 'juge_connecte', 'rappel_48h_le', 'rappel_2h_le',
    ];

    protected $casts = [
        'date_heure' => 'datetime',
        'juge_connecte' => 'boolean',
        'rappel_48h_le' => 'datetime',
        'rappel_2h_le' => 'datetime',
    ];

    protected $appends = ['salle_virtuelle'];

    // Salle virtuelle (Jitsi) seulement pour une audience en ligne, ou une
    // audience en présentiel où le juge a accordé au moins une comparution à
    // distance (après l'avis du greffier).
    public function getSalleVirtuelleAttribute(): bool
    {
        return $this->mode === 'EN_LIGNE'
            || $this->demandeDistances()->where('statut', 'APPROUVEE')->exists();
    }

    // Participant distant (justiciable, avocat, procureur) : partie au dossier
    // (compte vérifié) et, pour un justiciable ou un avocat d'une audience en
    // présentiel, titulaire d'une demande de comparution à distance approuvée.
    // Le procureur, qui ne formule pas de demande, peut suivre à distance toute
    // audience d'un dossier qui lui est communiqué dès qu'elle a une salle.
    public function peutEtreRejointeADistancePar(Utilisateur $utilisateur): bool
    {
        if (! $this->salle_virtuelle || ! $this->dossier->estAccessiblePar($utilisateur)) {
            return false;
        }

        if ($utilisateur->role === 'PROCUREUR' || $this->mode === 'EN_LIGNE') {
            return true;
        }

        return $this->demandeDistances()
            ->where('id_utilisateur', $utilisateur->id_utilisateur)
            ->where('statut', 'APPROUVEE')
            ->exists();
    }

    // Agir sur l'audience au nom de la juridiction (l'ouvrir, trancher une
    // demande, admettre un participant, émarger) : son juge, ou tout juge si
    // aucun n'est désigné ; pour le greffier, le dossier doit relever de son tribunal.
    public function estGereePar(Utilisateur $utilisateur): bool
    {
        if ($utilisateur->role === 'JUGE') {
            return ! $this->id_juge || (int) $this->id_juge === (int) $utilisateur->id_utilisateur;
        }

        return $utilisateur->role === 'GREFFIER' && $this->dossier->estAccessiblePar($utilisateur);
    }

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

    // Une audience occupe un créneau d'une heure (voir DisponibiliteJuge) : elle
    // n'est « ratée » que si le juge ne l'a pas ouverte avant la fin de ce créneau,
    // et non dès la minute qui suit l'heure prévue.
    public const DUREE_CRENEAU_MINUTES = \App\Services\DisponibiliteJuge::DUREE_MINUTES;

    // Le juge ouvre l'audience, et les participants distants accèdent à la
    // salle d'attente, au plus tôt 30 minutes avant l'heure prévue.
    public const OUVERTURE_AVANT_MINUTES = 30;

    public function heureOuverture(): \Illuminate\Support\Carbon
    {
        return $this->date_heure->copy()->subMinutes(self::OUVERTURE_AVANT_MINUTES);
    }

    public static function marquerRatees(): void
    {
        static::where('statut', 'PROGRAMMEE')
            ->where('date_heure', '<', now()->subMinutes(self::DUREE_CRENEAU_MINUTES))
            ->update(['statut' => 'RATEE']);
    }
}
