import { StatutConvocation, CanalNotification } from '../../constants/enums'

export const convocations = [
  {
    id_convocation: 1,
    id_dossier: 1,
    numero_dossier: 'TRB-DKR-2026-0142',
    id_audience: 1,
    date_audience: '2026-09-03T09:00:00',
    lieu: 'Tribunal de Grande Instance de Dakar — Salle virtuelle',
    canal: CanalNotification.SMS,
    statut: StatutConvocation.ENVOYEE,
  },
]

export function getConvocation(id) {
  return convocations.find((c) => c.id_convocation === Number(id))
}
