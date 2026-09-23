import Badge from './Badge'
import { STATUT_AUDIENCE_LABELS } from '../../constants/enums'
import { statusTone } from '../../utils/statusTone'

export default function AudienceStatusBadge({ statut }) {
  return <Badge tone={statusTone(statut)}>{STATUT_AUDIENCE_LABELS[statut] ?? statut}</Badge>
}
