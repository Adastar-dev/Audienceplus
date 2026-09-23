import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, Loader2 } from 'lucide-react'
import { getAudienceById, getJetonJitsi } from '../../services/api/audiences'
import JitsiMeetRoom from '../../components/jitsi/JitsiMeetRoom'
import { useAuth } from '../../context/AuthContext'
import { StatutAudience } from '../../constants/enums'

const INTERVALLE_ATTENTE_MS = 5000

// Page de visioconférence pour les participants non-juge : pas de contrôle
// micro/caméra sur les autres, juste rejoindre et quitter.
//
// On attend que le juge soit connecté à la conférence (audience.juge_connecte)
// avant de demander un jeton Jitsi : le backend le refuse sinon, et le juge
// peut ainsi activer la salle d'attente avant que quiconque n'entre.
export default function RejoindreAudience({ backTo }) {
  const { id } = useParams()
  const navigate = useNavigate()
  const { user } = useAuth()
  const [audience, setAudience] = useState(null)
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')
  const [jeton, setJeton] = useState(null)
  const [erreurSalle, setErreurSalle] = useState('')

  useEffect(() => {
    getAudienceById(id)
      .then(setAudience)
      .catch(() => setErreur('Impossible de charger cette audience.'))
      .finally(() => setChargement(false))
  }, [id])

  const salleOuverte = audience?.statut === StatutAudience.EN_COURS && audience?.juge_connecte
  const enAttente = audience && !jeton && [StatutAudience.PROGRAMMEE, StatutAudience.EN_COURS].includes(audience.statut)

  // Une fois entré, on garde la conférence même si le juge se déconnecte
  // brièvement : seule l'entrée est conditionnée à sa présence.
  useEffect(() => {
    if (!salleOuverte || jeton) return
    getJetonJitsi(id)
      .then(setJeton)
      .catch((e) => setErreurSalle(e.response?.data?.message ?? "Impossible d'entrer dans la salle."))
  }, [id, salleOuverte, jeton])

  useEffect(() => {
    if (!enAttente || salleOuverte) return
    const timer = setInterval(() => {
      getAudienceById(id).then(setAudience).catch(() => {})
    }, INTERVALLE_ATTENTE_MS)
    return () => clearInterval(timer)
  }, [id, enAttente, salleOuverte])

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

  return (
    <div className="fixed inset-0 flex flex-col bg-navy-950">
      <div className="flex items-center justify-between px-5 py-3 border-b border-navy-800 text-white">
        <div className="flex items-center gap-3">
          <button onClick={() => navigate(backTo ?? -1)} className="text-navy-100 hover:text-white">
            <ArrowLeft size={18} />
          </button>
          <div>
            <p className="text-sm font-medium">{audience.dossier?.parties}</p>
            <p className="text-xs text-navy-100">{audience.dossier?.numero}</p>
          </div>
        </div>
        <button
          onClick={() => navigate(backTo ?? -1)}
          className="bg-danger-700 text-white text-sm font-medium rounded px-4 py-1.5 hover:opacity-90 transition-opacity"
        >
          Quitter
        </button>
      </div>

      <div className="flex-1">
        {jeton ? (
          <JitsiMeetRoom
            domain={jeton.domaine}
            roomName={jeton.salle}
            jwt={jeton.jwt}
            displayName={user?.nom ?? 'Participant'}
            onLeft={() => navigate(backTo ?? -1)}
          />
        ) : erreurSalle ? (
          <div className="w-full h-full flex items-center justify-center text-navy-100 text-sm">{erreurSalle}</div>
        ) : enAttente ? (
          <div className="w-full h-full flex flex-col items-center justify-center gap-3 text-navy-100 text-sm">
            <Loader2 size={20} className="animate-spin" />
            En attente de l'ouverture de la salle par le juge...
          </div>
        ) : (
          <div className="w-full h-full flex items-center justify-center text-navy-100 text-sm">
            Cette audience n'est plus accessible.
          </div>
        )}
      </div>
    </div>
  )
}
