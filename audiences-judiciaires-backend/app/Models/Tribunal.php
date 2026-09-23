<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tribunal extends Model
{
    use HasFactory;

    protected $table = 'tribunaux';
    protected $primaryKey = 'id_tribunal';

    protected $fillable = ['nom', 'ville', 'adresse', 'telephone', 'email_contact'];

    public function utilisateurs()
    {
        return $this->hasMany(Utilisateur::class, 'id_tribunal', 'id_tribunal');
    }

    public function dossiers()
    {
        return $this->hasMany(Dossier::class, 'id_tribunal', 'id_tribunal');
    }

    public function sallesVirtuelles()
    {
        return $this->hasMany(SalleVirtuelle::class, 'id_tribunal', 'id_tribunal');
    }
}
