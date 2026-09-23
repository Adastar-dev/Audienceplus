import { Role } from '../../constants/enums'

export const utilisateurs = [
  { id_utilisateur: 1, nom: 'Fatou Diallo', email: 'f.diallo@justice.sn', role: Role.JUGE, identite_verifiee: true },
  { id_utilisateur: 2, nom: 'Moussa Sy', email: 'm.sy@justice.sn', role: Role.GREFFIER, identite_verifiee: true },
  { id_utilisateur: 3, nom: 'Awa Fall', email: 'awa.fall@barreau.sn', role: Role.AVOCAT, identite_verifiee: true },
  { id_utilisateur: 4, nom: 'Aïda Ndiaye', email: 'aida.ndiaye@mail.sn', role: Role.JUSTICIABLE, identite_verifiee: false },
]

export const tribunaux = [
  { id_tribunal: 1, nom: 'Tribunal de Grande Instance de Dakar', ville: 'Dakar', salles_virtuelles: 4 },
  { id_tribunal: 2, nom: 'Tribunal de Thiès', ville: 'Thiès', salles_virtuelles: 2 },
]

export const logs = [
  { id_log: 1, action: 'Connexion', utilisateur: 'Fatou Diallo', date: '2026-08-31T08:02:00', adresse_ip: '41.82.10.4' },
  { id_log: 2, action: 'Ouverture audience TRB-DKR-2026-0142', utilisateur: 'Fatou Diallo', date: '2026-08-31T09:00:00', adresse_ip: '41.82.10.4' },
  { id_log: 3, action: 'Validation pièce Constat_huissier.pdf', utilisateur: 'Moussa Sy', date: '2026-08-30T14:22:00', adresse_ip: '41.82.11.9' },
]
