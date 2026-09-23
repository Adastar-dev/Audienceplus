import { useCallback, useEffect, useRef, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { Gavel, ArrowLeft, Loader2 } from 'lucide-react'
import { getAudienceById, getJetonJitsi, cloturerAudience, signalerJugeConnecte, signalerJugeDeconnecte } from '../../services/api/audiences'
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
  const [avertissement, setAvertissement] = useState('')
  const [jeton, setJeton] = useState(null)
  const [choixDecision, setChoixDecision] = useState(false)
  const [cloture, setCloture] = useState(false)
  const jitsiApiRef = useRef(null)

  useEffect(() => {
    Promise.all([getAudienceById(id), listerParticipants(id), getJetonJitsi(id)])
      .then(([a, p, j]) => {
        setAudience(a)
        setParticipants(p)
        setJeton(j)
      })
      .catch(() => setErreur('Impossible de charger cette audience.'))
      .finally(() => setChargement(false))
  }, [id])

  // Les participants n'obtiennent de jeton Jitsi qu'une fois ce signal reçu :
  // le juge est alors déjà dans la conférence (modérateur via son jeton) et a
  // activé la salle d'attente.
  const handleJoined = useCallback(() => {
    signalerJugeConnecte(id)
      .then(() => setAvertissement(''))
      .catch(() => setAvertissement('Présence non signalée : les participants ne peuvent pas encore entrer.'))
  }, [id])

  useEffect(() => {
    const quitter = () => signalerJugeDeconnecte(id)
    window.addEventListener('pagehide', quitter)
    return () => {
      window.removeEventListener('pagehide', quitter)
      quitter()
    }
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

  // Fermer l'audience = enregistrer la décision (l'audience quitte l'état
  // EN_COURS), puis mettre fin à la visioconférence pour tous les participants.
  async function fermerAvecDecision(type) {
    setCloture(true)
    try {
      await cloturerAudience(id, type)
      try {
        jitsiApiRef.current?.executeCommand('endConference')
      } catch {
        // Serveur sans fonction "terminer pour tous" : le juge raccroche seul,
        // les participants ne pourront de toute façon plus obtenir de jeton.
      }
      navigate(`/juge/audiences/${id}`)
    } catch {
      setAvertissement("Impossible de fermer l'audience.")
      setCloture(false)
    }
  }

  if (chargement) {
    return (
      <div className="fixed inset-0 flex items-center justify-center bg-navy-950 text-navy-100">
        <Loader2 size={20} className="animate-spin" />
      </div>
    )
  }

  if (erreur || !audience || !jeton) {
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

        {avertissement && <p className="text-xs text-danger-100">{avertissement}</p>}

        {choixDecision ? (
          <div className="flex items-center gap-2">
            <span className="text-xs text-navy-100 hidden sm:inline">Décision :</span>
            {[
              ['jugement', 'Jugement rendu'],
              ['renvoi', 'Renvoi'],
              ['delibere', 'Mise en délibéré'],
            ].map(([type, label]) => (
              <button
                key={type}
                onClick={() => fermerAvecDecision(type)}
                disabled={cloture}
                className="bg-danger-700 text-white text-sm font-medium rounded px-3 py-1.5 hover:opacity-90 disabled:opacity-60 transition-opacity"
              >
                {label}
              </button>
            ))}
            <button
              onClick={() => setChoixDecision(false)}
              disabled={cloture}
              className="text-sm text-navy-100 hover:text-white px-2"
            >
              Annuler
            </button>
          </div>
        ) : (
          <button
            onClick={() => setChoixDecision(true)}
            className="flex items-center gap-2 bg-danger-700 text-white text-sm font-medium rounded px-4 py-1.5 hover:opacity-90 transition-opacity"
          >
            <Gavel size={15} />
            Fermer l'audience
          </button>
        )}
      </div>

      <div className="flex-1 flex min-h-0">
        <div className="flex-1">
          <JitsiMeetRoom
            domain={jeton.domaine}
            roomName={jeton.salle}
            jwt={jeton.jwt}
            displayName={user?.nom ?? 'Juge'}
            moderator
            onJoined={handleJoined}
            onApiReady={(api) => {
              jitsiApiRef.current = api
            }}
          />
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
