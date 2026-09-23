// Enums métier — repris directement du diagramme de classes du mémoire.
// Une seule source de vérité pour les valeurs de statut utilisées dans toute l'app.

export const Role = {
  JUGE: 'JUGE',
  GREFFIER: 'GREFFIER',
  PROCUREUR: 'PROCUREUR',
  AVOCAT: 'AVOCAT',
  JUSTICIABLE: 'JUSTICIABLE',
  ADMINISTRATEUR: 'ADMINISTRATEUR',
}

export const TypeAudience = {
  DIVORCE: 'DIVORCE',
  ADOPTION: 'ADOPTION',
  RECTIFICATION_ACTE: 'RECTIFICATION_ACTE',
  CONTENTIEUX_MARIAGE: 'CONTENTIEUX_MARIAGE',
  FILIATION: 'FILIATION',
  GARDE_PENSION: 'GARDE_PENSION',
  TUTELLE: 'TUTELLE',
  DECLARATION_ABSENCE_DECES: 'DECLARATION_ABSENCE_DECES',
  CHANGEMENT_NOM: 'CHANGEMENT_NOM',
  EMANCIPATION: 'EMANCIPATION',
}

export const TYPE_AUDIENCE_LABELS = {
  [TypeAudience.DIVORCE]: 'Divorce et séparation de corps',
  [TypeAudience.ADOPTION]: 'Adoption',
  [TypeAudience.RECTIFICATION_ACTE]: "Rectification et actes supplétifs d'état civil",
  [TypeAudience.CONTENTIEUX_MARIAGE]: 'Contentieux du mariage',
  [TypeAudience.FILIATION]: 'Filiation',
  [TypeAudience.GARDE_PENSION]: 'Garde d\'enfants et pension alimentaire',
  [TypeAudience.TUTELLE]: 'Tutelle',
  [TypeAudience.DECLARATION_ABSENCE_DECES]: "Déclaration d'absence ou de décès présumé",
  [TypeAudience.CHANGEMENT_NOM]: 'Changement de nom',
  [TypeAudience.EMANCIPATION]: 'Émancipation',
}

export const StatutDossier = {
  EN_COURS: 'EN_COURS',
  RENVOYE: 'RENVOYE',
  JUGE: 'JUGE',
  CLOTURE: 'CLOTURE',
}

export const StatutAudience = {
  PROGRAMMEE: 'PROGRAMMEE',
  EN_COURS: 'EN_COURS',
  CLOTUREE: 'CLOTUREE',
  RENVOYEE: 'RENVOYEE',
}

export const ModeAudience = {
  PRESENTIEL: 'PRESENTIEL',
  EN_LIGNE: 'EN_LIGNE',
}

export const StatutDemandeDistance = {
  EN_ATTENTE: 'EN_ATTENTE',
  AVIS_GREFFIER_FAVORABLE: 'AVIS_GREFFIER_FAVORABLE',
  AVIS_GREFFIER_DEFAVORABLE: 'AVIS_GREFFIER_DEFAVORABLE',
  APPROUVEE: 'APPROUVEE',
  REFUSEE: 'REFUSEE',
}

export const STATUT_DEMANDE_DISTANCE_LABELS = {
  [StatutDemandeDistance.EN_ATTENTE]: 'En attente',
  [StatutDemandeDistance.AVIS_GREFFIER_FAVORABLE]: 'Avis favorable du greffe — en attente du juge',
  [StatutDemandeDistance.AVIS_GREFFIER_DEFAVORABLE]: 'Avis défavorable du greffe — en attente du juge',
  [StatutDemandeDistance.APPROUVEE]: 'Approuvée',
  [StatutDemandeDistance.REFUSEE]: 'Refusée',
}

export const StatutConvocation = {
  ENVOYEE: 'ENVOYEE',
  RECUE: 'RECUE',
  CONFIRMEE: 'CONFIRMEE',
  REPORT_DEMANDE: 'REPORT_DEMANDE',
  AVIS_GREFFIER_FAVORABLE: 'AVIS_GREFFIER_FAVORABLE',
  AVIS_GREFFIER_DEFAVORABLE: 'AVIS_GREFFIER_DEFAVORABLE',
  REPORT_APPROUVEE: 'REPORT_APPROUVEE',
  REPORT_REFUSEE: 'REPORT_REFUSEE',
}

export const StatutPV = {
  EN_COURS: 'EN_COURS',
  EN_VALIDATION: 'EN_VALIDATION',
  CLOTURE: 'CLOTURE',
  CONTESTE: 'CONTESTE',
}

export const ResultatCasier = {
  VIERGE: 'VIERGE',
  NON_VIERGE: 'NON_VIERGE',
}

export const CanalNotification = {
  EMAIL: 'EMAIL',
  SMS: 'SMS',
  APPEL_VOCAL: 'APPEL_VOCAL',
  IN_APP: 'IN_APP',
}

export const TypeDocument = {
  PROCES_VERBAL: 'PROCES_VERBAL',
  CONVOCATION: 'CONVOCATION',
  PARTICIPATION: 'PARTICIPATION',
}

// Libellés lisibles en français pour l'affichage (badges, filtres...)
export const STATUT_DOSSIER_LABELS = {
  [StatutDossier.EN_COURS]: 'En cours',
  [StatutDossier.RENVOYE]: 'Renvoyé',
  [StatutDossier.JUGE]: 'Jugé',
  [StatutDossier.CLOTURE]: 'Clôturé',
}

export const STATUT_AUDIENCE_LABELS = {
  [StatutAudience.PROGRAMMEE]: 'Programmée',
  [StatutAudience.EN_COURS]: 'En cours',
  [StatutAudience.CLOTUREE]: 'Clôturée',
  [StatutAudience.RENVOYEE]: 'Renvoyée',
}

export const STATUT_PV_LABELS = {
  [StatutPV.EN_COURS]: 'En cours',
  [StatutPV.EN_VALIDATION]: 'En validation',
  [StatutPV.CLOTURE]: 'Clôturé',
  [StatutPV.CONTESTE]: 'Contesté',
}

export const STATUT_CONVOCATION_LABELS = {
  [StatutConvocation.ENVOYEE]: 'Envoyée',
  [StatutConvocation.RECUE]: 'Reçue',
  [StatutConvocation.CONFIRMEE]: 'Présence confirmée',
  [StatutConvocation.REPORT_DEMANDE]: 'Report demandé',
  [StatutConvocation.AVIS_GREFFIER_FAVORABLE]: 'Avis favorable du greffe — en attente du juge',
  [StatutConvocation.AVIS_GREFFIER_DEFAVORABLE]: 'Avis défavorable du greffe — en attente du juge',
  [StatutConvocation.REPORT_APPROUVEE]: 'Report approuvé',
  [StatutConvocation.REPORT_REFUSEE]: 'Report refusé',
}

export const ROLE_LABELS = {
  [Role.JUGE]: 'Juge',
  [Role.GREFFIER]: 'Greffier',
  [Role.PROCUREUR]: 'Procureur',
  [Role.AVOCAT]: 'Avocat',
  [Role.JUSTICIABLE]: 'Justiciable',
  [Role.ADMINISTRATEUR]: 'Administrateur système',
}
