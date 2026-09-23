import { StatutPV } from '../../constants/enums'

export const pvByAudience = {
  1: null,
  2: {
    id_pv: 1,
    id_audience: 2,
    contenu: "L'audience s'est ouverte à 11h00 en présence du procureur et du greffier. Le prévenu, absent, est représenté par son avocat.",
    statut: StatutPV.EN_COURS,
    date_validation: null,
  },
  3: {
    id_pv: 2,
    id_audience: 3,
    contenu: "Après plaidoiries des deux parties, le juge a mis l'affaire en délibéré. Décision attendue sous 15 jours.",
    statut: StatutPV.CLOTURE,
    date_validation: '2026-08-28',
  },
}

export function getPV(idAudience) {
  return pvByAudience[idAudience] ?? null
}
