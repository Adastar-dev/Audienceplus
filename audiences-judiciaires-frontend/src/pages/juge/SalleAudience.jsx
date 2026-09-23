import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { Gavel, ArrowLeft, Loader2 } from 'lucide-react'
import { getAudienceById } from '../../services/api/audiences'
import { listerParticipants, admettreParticipant, refuserParticipant } from '../../services/api/participants'
import { useAuth } from '../../context/AuthContext'
import JitsiMeetRoom from '../../components/jitsi/JitsiMeetRoom'
import ParticipantRow from '../../components/ui/ParticipantRow'

// NOTE : l'activation/desactivation du micro et de la camera reste locale a
// cette page (pas de champ backend synchronise pour l'instant) - Jitsi gere
// ces etats en temps reel de son cote independamment de notre API.
export default function SalleAudience() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { user } = useAuth()
  const [audience, setAudience] = useState(null)
  const [participants, setParticipants] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    Promise.all([getAudienceById(id), listerParticipants(id)])
      .then(([a, p]) => {
        setAudience(a)
        setParticipants(p)
      })
      .catch(() => setErreur('Impossible de charger cette audience.'))
      .finally(() => setChargement(false))
  }, [id])

  function toggleMicro(participantId) {
    setParticipants((list) =>
      list.map((p) => (p.id_participation === participantId ? { ...p, micro_actif: !p.micro_actif } : p)),
    )
  }

  function toggleCamera(participantId) {
    setParticipants((list) =>
      list.map((p) => (p.id_participation === participantId ? { ...p, camera_active: !p.camera_active } : p)),
    )
  }

  async function admettre(participantId) {
    try {
      const updated = await admettreParticipant(id, participantId)
      setParticipants((list) => list.map((p) => (p.id_participation === participantId ? updated : p)))
    } catch {
      setErreur("Impossible d'admettre ce participant.")
    }
  }

  async function refuser(participantId) {
    try {
      const updated = await refuserParticipant(id, participantId)
      setParticipants((list) => list.map((p) => (p.id_participation === participantId ? updated : p)))
    } catch {
      setErreur('Impossible de refuser ce participant.')
    }
  }

  function handleFermerAudience() {
    navigate(`/juge/audiences/${id}`)
  }

  if (chargement) {
    return (
      <div className="fixed inset-0 flex items-center justify-center bg-navy-950 text-navy-100">
        <Loader2 size={20} className="animate-spin" />
      </div>
    )
  }

  if (erreur || !audience) {
    return <p className="text-sm text-slate-600 p-6">{erreur || 'Audience introuvable.'}</p>
  }

  const presents = participants.filter((p) => p.present).length

  return (
    <div className="fixed inset-0 flex flex-col bg-navy-950">
      <div className="flex items-center justify-between px-5 py-3 border-b border-navy-800 text-white">
        <div className="flex items-center gap-3">
          <button
            onClick={() => navigate(`/juge/audiences/${id}`)}
            className="text-navy-100 hover:text-white"
          >
            <ArrowLeft size={18} />
          </button>
          <div>
            <p className="text-sm font-medium">{audience.dossier?.parties}</p>
            <p className="text-xs text-navy-100">{audience.dossier?.numero}</p>
          </div>
        </div>

        <button
          onClick={handleFermerAudience}
          className="flex items-center gap-2 bg-danger-700 text-white text-sm font-medium rounded px-4 py-1.5 hover:opacity-90 transition-opacity"
        >
          <Gavel size={15} />
          Fermer l'audience
        </button>
      </div>

      <div className="flex-1 flex min-h-0">
        <div className="flex-1">
          <JitsiMeetRoom roomName={`audience-${id}`} displayName={user?.nom ?? 'Juge'} moderator />
        </div>

        <aside className="w-72 shrink-0 border-l border-navy-800 flex flex-col">
          <div className="px-4 py-3 border-b border-navy-800">
            <p className="text-sm text-white font-medium">Participants</p>
            <p className="text-xs text-navy-100">{presents} / {participants.length} présents</p>
          </div>
          <div className="flex-1 overflow-y-auto">
            {participants.map((p) => (
              <ParticipantRow
                key={p.id_participation}
                participant={{ ...p, id: p.id_participation, nom: p.utilisateur?.nom, role: p.role_audience }}
                onToggleMicro={toggleMicro}
                onToggleCamera={toggleCamera}
                onAdmettre={admettre}
                onRefuser={refuser}
              />
            ))}
          </div>
        </aside>
      </div>
    </div>
  )
}
