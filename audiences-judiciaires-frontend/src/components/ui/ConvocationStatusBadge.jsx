import Badge from './Badge'
import { STATUT_CONVOCATION_LABELS } from '../../constants/enums'
import { statusTone } from '../../utils/statusTone'

export default function ConvocationStatusBadge({ statut }) {
  return <Badge tone={statusTone(statut)}>{STATUT_CONVOCATION_LABELS[statut] ?? statut}</Badge>
}
