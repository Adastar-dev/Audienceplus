<?php

namespace App\Services;

use App\Models\Audience;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

// Disponibilité d'un juge : une audience occupe un créneau d'une heure. Si le
// créneau demandé est pris, on propose les prochains créneaux libres, du lundi
// au vendredi, de 8 h à 17 h.
class DisponibiliteJuge
{
    public const DUREE_MINUTES = 60;

    private const PREMIERE_HEURE = 8;

    private const DERNIERE_HEURE = 17;

    public function estDisponible(int $idJuge, CarbonInterface $debut, ?int $idAudienceExclue = null): bool
    {
        return ! Audience::where('id_juge', $idJuge)
            ->whereIn('statut', ['PROGRAMMEE', 'EN_COURS'])
            ->when($idAudienceExclue, fn ($q) => $q->where('id_audience', '!=', $idAudienceExclue))
            ->whereBetween('date_heure', [
                $debut->copy()->subMinutes(self::DUREE_MINUTES - 1),
                $debut->copy()->addMinutes(self::DUREE_MINUTES - 1),
            ])
            ->exists();
    }

    /** @return list<string> dates au format « Y-m-d H:i:s » */
    public function creneauxLibres(int $idJuge, CarbonInterface $aPartirDe, int $nombre = 3): array
    {
        $creneau = Carbon::parse($aPartirDe)->minute(0)->second(0);
        $creneaux = [];

        // Au plus deux semaines de recherche.
        for ($i = 0; $i < 24 * 14 && count($creneaux) < $nombre; $i++, $creneau->addHour()) {
            $ouvrable = $creneau->isWeekday()
                && $creneau->hour >= self::PREMIERE_HEURE
                && $creneau->hour <= self::DERNIERE_HEURE;

            if ($ouvrable && $creneau->isFuture() && $this->estDisponible($idJuge, $creneau)) {
                $creneaux[] = $creneau->format('Y-m-d H:i:s');
            }
        }

        return $creneaux;
    }

    public function reponseIndisponible(int $idJuge, CarbonInterface $date)
    {
        return response()->json([
            'message' => 'Le juge a déjà une audience sur ce créneau. Choisissez une autre date.',
            'creneaux_libres' => $this->creneauxLibres($idJuge, $date),
        ], 409);
    }
}
