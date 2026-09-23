<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_message';

    protected $fillable = ['id_expediteur', 'id_destinataire', 'contenu', 'lu', 'date_envoi'];

    protected $casts = [
        'lu' => 'boolean',
        'date_envoi' => 'datetime',
    ];

    public function expediteur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_expediteur', 'id_utilisateur');
    }

    public function destinataire()
    {
        return $this->belongsTo(Utilisateur::class, 'id_destinataire', 'id_utilisateur');
    }
}
