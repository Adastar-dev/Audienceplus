import { Role } from './enums'

export const NAV_BY_ROLE = {
  [Role.JUGE]: [
    { label: 'Tableau de bord', to: '/juge' },
    { label: 'Calendrier des audiences', to: '/juge/audiences' },
    { label: 'Dossiers programmés', to: '/juge/dossiers' },
    { label: 'Validations du greffe', to: '/juge/validations' },
    { label: 'Messagerie', to: '/juge/messagerie' },
  ],
  [Role.GREFFIER]: [
    { label: 'Tableau de bord', to: '/greffier' },
    { label: 'Dossiers', to: '/greffier/dossiers' },
    { label: 'Convocations', to: '/greffier/convocations' },
    { label: 'Messagerie', to: '/greffier/messagerie' },
    { label: 'Demandes à distance', to: '/greffier/demandes-distance' },
  ],
  [Role.PROCUREUR]: [
    { label: 'Tableau de bord', to: '/procureur' },
    { label: 'Dossiers assignés', to: '/procureur/dossiers' },
    { label: 'Décisions rendues', to: '/procureur/decisions' },
    { label: 'Messagerie', to: '/procureur/messagerie' },
    { label: 'Notifications', to: '/procureur/notifications' },
  ],
  [Role.AVOCAT]: [
    { label: 'Tableau de bord', to: '/avocat' },
    { label: 'Mes dossiers', to: '/avocat/dossiers' },
    { label: 'Convocations', to: '/avocat/convocations' },
    { label: 'Historique des audiences', to: '/avocat/historique' },
    { label: 'Messagerie', to: '/avocat/messagerie' },
  ],
  [Role.JUSTICIABLE]: [
    { label: 'Tableau de bord', to: '/justiciable' },
    { label: 'Mon dossier', to: '/justiciable/dossier' },
    { label: 'Assister à distance', to: '/justiciable/demande-distance' },
    { label: 'Décisions', to: '/justiciable/decisions' },
    { label: 'Messagerie', to: '/justiciable/messagerie' },
    { label: 'Notifications', to: '/justiciable/notifications' },
    { label: 'Casier judiciaire', to: '/justiciable/casier' },
  ],
  [Role.ADMINISTRATEUR]: [
    { label: 'Tableau de bord', to: '/admin' },
    { label: 'Utilisateurs & rôles', to: '/admin/utilisateurs' },
    { label: 'Tribunaux & salles', to: '/admin/tribunaux' },
    { label: 'Paramètres de sécurité', to: '/admin/securite' },
    { label: 'Logs & traçabilité', to: '/admin/logs' },
  ],
}
