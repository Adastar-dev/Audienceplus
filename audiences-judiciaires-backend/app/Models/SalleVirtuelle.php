<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalleVirtuelle extends Model
{
    use HasFactory;

    protected $table = 'salles_virtuelles';
    protected $primaryKey = 'id_salle';

    protected $fillable = ['id_tribunal', 'lien_jitsi', 'code_acces'];

    public function tribunal()
    {
        return $this->belongsTo(Tribunal::class, 'id_tribunal', 'id_tribunal');
    }

    public function audiences()
    {
        return $this->hasMany(Audience::class, 'id_salle', 'id_salle');
    }
}
