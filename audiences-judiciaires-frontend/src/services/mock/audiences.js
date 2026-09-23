import { StatutAudience } from '../../constants/enums'

export const audiences = [
  {
    id_audience: 1,
    id_dossier: 1,
    numero_dossier: 'TRB-DKR-2026-0142',
    parties: 'Ndiaye c. Sarr',
    date_heure: '2026-09-03T09:00:00',
    statut: StatutAudience.PROGRAMMEE,
    juge: 'Juge Fatou Diallo',
  },
  {
    id_audience: 2,
    id_dossier: 2,
    numero_dossier: 'TRB-DKR-2026-0143',
    parties: 'Ministère public c. Diop',
    date_heure: '2026-09-03T11:00:00',
    statut: StatutAudience.PROGRAMMEE,
    juge: 'Juge Fatou Diallo',
  },
  {
    id_audience: 3,
    id_dossier: 3,
    numero_dossier: 'TRB-THS-2026-0021',
    parties: 'SARL Baobab c. Fall',
    date_heure: '2026-08-28T14:30:00',
    statut: StatutAudience.CLOTUREE,
    juge: 'Juge Fatou Diallo',
  },
  {
    id_audience: 4,
    id_dossier: 4,
    numero_dossier: 'TRB-DKR-2026-0098',
    parties: 'Gueye c. Ba',
    date_heure: '2026-08-20T10:00:00',
    statut: StatutAudience.RENVOYEE,
    juge: 'Juge Fatou Diallo',
  },
]

export function getAudienceById(id) {
  return audiences.find((a) => a.id_audience === Number(id))
}
