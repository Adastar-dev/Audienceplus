import { StatutDossier, StatutAudience, StatutPV, StatutConvocation, StatutDemandeDistance } from '../constants/enums'

// Associe chaque valeur de statut métier à un "tone" visuel de Badge.
// Centralisé ici pour que la couleur d'un statut soit cohérente partout dans l'app.
export function statusTone(statut) {
  switch (statut) {
    case StatutDossier.EN_COURS:
    case StatutAudience.EN_COURS:
    case StatutPV.EN_VALIDATION:
    case StatutConvocation.ENVOYEE:
    case StatutDemandeDistance.EN_ATTENTE:
      return 'info'

    case StatutDossier.CLOTURE:
    case StatutAudience.CLOTUREE:
    case StatutPV.CLOTURE:
    case StatutConvocation.CONFIRMEE:
    case StatutConvocation.REPORT_APPROUVEE:
    case StatutDemandeDistance.APPROUVEE:
      return 'success'

    case StatutDossier.RENVOYE:
    case StatutAudience.RENVOYEE:
    case StatutAudience.DELIBERE:
    case StatutConvocation.REPORT_DEMANDE:
    case StatutConvocation.AVIS_GREFFIER_FAVORABLE:
    case StatutConvocation.AVIS_GREFFIER_DEFAVORABLE:
    case StatutDemandeDistance.AVIS_GREFFIER_FAVORABLE:
    case StatutDemandeDistance.AVIS_GREFFIER_DEFAVORABLE:
      return 'warning'

    case StatutConvocation.REPORT_REFUSEE:
    case StatutDemandeDistance.REFUSEE:
    case StatutAudience.RATEE:
      return 'danger'

    case StatutDossier.JUGE:
      return 'neutral'

    default:
      return 'neutral'
  }
}
