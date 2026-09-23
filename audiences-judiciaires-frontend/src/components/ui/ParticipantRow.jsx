import { Mic, MicOff, Video, VideoOff, CircleUserRound, UserCheck, UserX } from 'lucide-react'
import { ROLE_LABELS } from '../../constants/enums'

export default function ParticipantRow({ participant, onToggleMicro, onToggleCamera, onAdmettre, onRefuser }) {
  const { nom, role, micro_actif, camera_active, present, admis } = participant

  if (present && !admis) {
    return (
      <div className="flex items-center justify-between px-3 py-2.5 border-b border-navy-700 last:border-0 bg-navy-800/50">
        <div className="flex items-center gap-2.5 min-w-0">
          <CircleUserRound size={20} className="text-gold-600" />
          <div className="min-w-0">
            <p className="text-sm truncate text-white">{nom}</p>
            <p className="text-xs text-gold-600">En attente d'admission</p>
          </div>
        </div>
        <div className="flex items-center gap-1 shrink-0">
          <button
            onClick={() => onAdmettre(participant.id)}
            className="p-1.5 rounded hover:bg-navy-700 text-success-700"
            title="Admettre"
          >
            <UserCheck size={16} />
          </button>
          <button
            onClick={() => onRefuser(participant.id)}
            className="p-1.5 rounded hover:bg-navy-700 text-danger-700"
            title="Refuser"
          >
            <UserX size={16} />
          </button>
        </div>
      </div>
    )
  }

  return (
    <div className="flex items-center justify-between px-3 py-2.5 border-b border-navy-700 last:border-0">
      <div className="flex items-center gap-2.5 min-w-0">
        <CircleUserRound size={20} className={present ? 'text-navy-100' : 'text-navy-700'} />
        <div className="min-w-0">
          <p className={`text-sm truncate ${present ? 'text-white' : 'text-navy-700'}`}>{nom}</p>
          <p className="text-xs text-navy-100">{ROLE_LABELS[role]}</p>
        </div>
      </div>

      <div className="flex items-center gap-1 shrink-0">
        <button
          onClick={() => onToggleMicro(participant.id)}
          disabled={!present}
          className={`p-1.5 rounded hover:bg-navy-800 disabled:opacity-30 ${
            micro_actif ? 'text-success-700' : 'text-navy-100'
          }`}
          title={micro_actif ? 'Couper le micro' : 'Micro coupé'}
        >
          {micro_actif ? <Mic size={15} /> : <MicOff size={15} />}
        </button>
        <button
          onClick={() => onToggleCamera(participant.id)}
          disabled={!present}
          className={`p-1.5 rounded hover:bg-navy-800 disabled:opacity-30 ${
            camera_active ? 'text-success-700' : 'text-navy-100'
          }`}
          title={camera_active ? 'Couper la caméra' : 'Caméra coupée'}
        >
          {camera_active ? <Video size={15} /> : <VideoOff size={15} />}
        </button>
      </div>
    </div>
  )
}
