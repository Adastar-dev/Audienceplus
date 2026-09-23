import { Role } from '../../constants/enums'

export const participantsByAudience = {
  1: [
    { id: 1, nom: 'Fatou Diallo', role: Role.JUGE, micro_actif: true, camera_active: true, present: true, admis: true },
    { id: 2, nom: 'Moussa Sy', role: Role.GREFFIER, micro_actif: true, camera_active: false, present: true, admis: true },
    { id: 3, nom: 'Aïda Ndiaye', role: Role.JUSTICIABLE, micro_actif: false, camera_active: true, present: true, admis: false },
    { id: 4, nom: 'Cheikh Sarr', role: Role.JUSTICIABLE, micro_actif: false, camera_active: true, present: false, admis: false },
    { id: 5, nom: 'Me. Awa Fall', role: Role.AVOCAT, micro_actif: false, camera_active: true, present: true, admis: true },
  ],
  2: [
    { id: 1, nom: 'Fatou Diallo', role: Role.JUGE, micro_actif: true, camera_active: true, present: true, admis: true },
    { id: 2, nom: 'Moussa Sy', role: Role.GREFFIER, micro_actif: true, camera_active: false, present: true, admis: true },
    { id: 6, nom: 'Ibrahima Diop', role: Role.JUSTICIABLE, micro_actif: false, camera_active: false, present: false, admis: false },
    { id: 7, nom: 'Procureur Sow', role: Role.PROCUREUR, micro_actif: false, camera_active: true, present: true, admis: true },
  ],
}

export function getParticipants(idAudience) {
  return participantsByAudience[idAudience] ?? []
}
