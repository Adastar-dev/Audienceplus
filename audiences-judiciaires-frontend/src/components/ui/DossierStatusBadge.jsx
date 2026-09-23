import Badge from './Badge'
import { STATUT_DOSSIER_LABELS } from '../../constants/enums'
import { statusTone } from '../../utils/statusTone'

export default function DossierStatusBadge({ statut }) {
  return <Badge tone={statusTone(statut)}>{STATUT_DOSSIER_LABELS[statut] ?? statut}</Badge>
}
