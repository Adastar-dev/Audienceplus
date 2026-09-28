<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{

    protected $primaryKey = 'id_notification';

    protected $fillable = ['id_utilisateur', 'type', 'message', 'lu', 'date_envoi', 'id_audience', 'date_accuse_reception', 'lien'];

    protected $casts = [
        'lu' => 'boolean',
        'date_envoi' => 'datetime',
        'date_accuse_reception' => 'datetime',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_utilisateur', 'id_utilisateur');
    }
}
