import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, Loader2 } from 'lucide-react'
import { getAudienceById } from '../../services/api/audiences'
import JitsiMeetRoom from '../../components/jitsi/JitsiMeetRoom'
import { useAuth } from '../../context/AuthContext'

// Page de visioconférence pour les participants non-juge : pas de contrôle
// micro/caméra sur les autres, juste rejoindre et quitter.
export default function RejoindreAudience({ backTo }) {
  const { id } = useParams()
  const navigate = useNavigate()
  const { user } = useAuth()
  const [audience, setAudience] = useState(null)
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    getAudienceById(id)
      .then(setAudience)
      .catch(() => setErreur('Impossible de charger cette audience.'))
      .finally(() => setChargement(false))
  }, [id])

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
        <JitsiMeetRoom roomName={`audience-${id}`} displayName={user?.nom ?? 'Participant'} />
      </div>
    </div>
  )
}
