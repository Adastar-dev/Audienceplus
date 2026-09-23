<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogActivite extends Model
{
    use HasFactory;

    protected $table = 'logs_activite';
    protected $primaryKey = 'id_log';

    protected $fillable = ['id_utilisateur', 'action', 'adresse_ip', 'date'];

    protected $casts = [
        'date' => 'datetime',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_utilisateur', 'id_utilisateur');
    }
}
