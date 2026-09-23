<?php

namespace App\Services;

use App\Models\Convocation;
use App\Models\ParticipationAudience;
use App\Models\ProcesVerbal;

class DocumentHashService
{
    public function contenuAHacher(string $typeDocument, int $idDocument): ?string
    {
        return match ($typeDocument) {
            'PROCES_VERBAL' => optional(ProcesVerbal::find($idDocument))->contenu,
            'CONVOCATION' => $this->representerConvocation($idDocument),
            'PARTICIPATION' => $this->representerParticipation($idDocument),
            default => null,
        };
    }

    private function representerConvocation(int $id): ?string
    {
        $convocation = Convocation::find($id);

        if (! $convocation) {
            return null;
        }

        return implode('|', [
            $convocation->id_audience,
            $convocation->id_utilisateur,
            $convocation->canal,
            $convocation->statut,
        ]);
    }

    private function representerParticipation(int $id): ?string
    {
        $participation = ParticipationAudience::find($id);

        if (! $participation) {
            return null;
        }

        return implode('|', [
            $participation->id_audience,
            $participation->id_utilisateur,
            $participation->present ? '1' : '0',
        ]);
    }
}
