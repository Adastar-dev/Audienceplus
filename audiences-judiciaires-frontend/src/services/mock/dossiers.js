import { StatutDossier, TypeAudience } from '../../constants/enums'

export const dossiers = [
  {
    id_dossier: 1,
    numero: 'TRB-DKR-2026-0142',
    type: TypeAudience.DIVORCE,
    statut: StatutDossier.EN_COURS,
    tribunal: 'Tribunal de Grande Instance de Dakar',
    parties: 'Ndiaye c. Sarr',
    date_creation: '2026-07-02',
  },
  {
    id_dossier: 2,
    numero: 'TRB-DKR-2026-0143',
    type: TypeAudience.ADOPTION,
    statut: StatutDossier.RENVOYE,
    tribunal: 'Tribunal de Grande Instance de Dakar',
    parties: 'Famille Diop',
    date_creation: '2026-07-05',
  },
  {
    id_dossier: 3,
    numero: 'TRB-THS-2026-0021',
    type: TypeAudience.RECTIFICATION_ACTE,
    statut: StatutDossier.JUGE,
    tribunal: 'Tribunal de Thiès',
    parties: 'Fall (jugement supplétif)',
    date_creation: '2026-06-18',
  },
  {
    id_dossier: 4,
    numero: 'TRB-DKR-2026-0098',
    type: TypeAudience.GARDE_PENSION,
    statut: StatutDossier.CLOTURE,
    tribunal: 'Tribunal de Grande Instance de Dakar',
    parties: 'Gueye c. Ba',
    date_creation: '2026-05-14',
  },
]

export function getDossierById(id) {
  return dossiers.find((d) => d.id_dossier === Number(id))
}
