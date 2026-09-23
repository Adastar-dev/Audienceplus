<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Signature extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_signature';

    protected $fillable = ['id_utilisateur', 'type_document', 'id_document_signe', 'hash', 'image_path', 'date_signature'];

    protected $casts = [
        'date_signature' => 'datetime',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_utilisateur', 'id_utilisateur');
    }

    public function document()
    {
        return match ($this->type_document) {
            'PROCES_VERBAL' => ProcesVerbal::where('id_pv', $this->id_document_signe)->first(),
            'CONVOCATION' => Convocation::where('id_convocation', $this->id_document_signe)->first(),
            'PARTICIPATION' => ParticipationAudience::where('id_participation', $this->id_document_signe)->first(),
            default => null,
        };
    }
}
