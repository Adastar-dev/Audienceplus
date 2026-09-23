import Badge from './Badge'
import { STATUT_PV_LABELS } from '../../constants/enums'
import { statusTone } from '../../utils/statusTone'

export default function PVStatusBadge({ statut }) {
  return <Badge tone={statusTone(statut)}>{STATUT_PV_LABELS[statut] ?? statut}</Badge>
}
