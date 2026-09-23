import { useEffect, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { ArrowLeft, PenLine, Undo2, Loader2 } from 'lucide-react'
import { getAudienceById } from '../../services/api/audiences'
import { listerParticipants, marquerPresent, marquerAbsent } from '../../services/api/participants'
import { ROLE_LABELS } from '../../constants/enums'

export default function Emargement() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [audience, setAudience] = useState(null)
  const [participants, setParticipants] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')
  const [enCours, setEnCours] = useState(null)

  useEffect(() => {
    Promise.all([getAudienceById(id), listerParticipants(id)])
      .then(([audienceData, participantsData]) => {
        setAudience(audienceData)
        setParticipants(participantsData)
      })
      .catch(() => setErreur('Impossible de charger cette audience.'))
      .finally(() => setChargement(false))
  }, [id])

  async function handleMarquerPresent(idParticipation) {
    setEnCours(idParticipation)
    try {
      const { participation } = await marquerPresent(id, idParticipation)
      setParticipants((list) =>
        list.map((p) => (p.id_participation === idParticipation ? { ...p, present: true } : p)),
      )
    } catch {
      setErreur("Impossible d'enregistrer la présence.")
    } finally {
      setEnCours(null)
    }
  }

  async function handleMarquerAbsent(idParticipation) {
    setEnCours(idParticipation)
    try {
      await marquerAbsent(id, idParticipation)
      setParticipants((list) =>
        list.map((p) => (p.id_participation === idParticipation ? { ...p, present: false } : p)),
      )
    } catch {
      setErreur("Impossible de mettre à jour la présence.")
    } finally {
      setEnCours(null)
    }
  }

  if (chargement) {
    return (
      <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
        <Loader2 size={16} className="animate-spin" />
        Chargement...
      </div>
    )
  }

  if (!audience) {
    return (
      <div>
        <p className="text-sm text-slate-600">Audience introuvable.</p>
        <Link to="/greffier/dossiers" className="text-sm text-navy-900 underline">Retour</Link>
      </div>
    )
  }

  return (
    <div>
      <button onClick={() => navigate(-1)} className="flex items-center gap-1 text-sm text-slate-600 hover:text-navy-900 mb-4">
        <ArrowLeft size={14} /> Retour
      </button>

      <h1 className="font-display text-2xl text-navy-900">Présence et émargement</h1>
      <p className="text-sm text-slate-600 mt-1">{audience.dossier?.parties} — {audience.dossier?.numero}</p>
      <p className="text-xs text-slate-400 mt-1">
        Marquer un participant présent génère une signature électronique d'attestation.
      </p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      <div className="mt-6 bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
        {participants.map((p) => (
          <div key={p.id_participation} className="flex items-center justify-between px-4 py-3">
            <div>
              <p className="text-sm font-medium text-navy-900">{p.utilisateur?.nom}</p>
              <p className="text-xs text-slate-400">{ROLE_LABELS[p.role_audience] ?? p.role_audience}</p>
            </div>

            {p.present ? (
              <div className="flex items-center gap-3">
                <span className="flex items-center gap-1 text-xs text-success-700 font-medium">
                  <PenLine size={13} /> Présence attestée
                </span>
                <button
                  onClick={() => handleMarquerAbsent(p.id_participation)}
                  disabled={enCours === p.id_participation}
                  className="flex items-center gap-1 text-xs text-slate-400 hover:text-danger-700 disabled:opacity-40"
                >
                  <Undo2 size={12} />
                  Annuler
                </button>
              </div>
            ) : (
              <button
                onClick={() => handleMarquerPresent(p.id_participation)}
                disabled={enCours === p.id_participation}
                className="flex items-center gap-1.5 bg-navy-900 text-white text-xs font-medium rounded px-3 py-1.5 hover:bg-navy-800 disabled:opacity-60 transition-colors"
              >
                <PenLine size={13} />
                {enCours === p.id_participation ? 'Signature...' : 'Marquer présent et signer'}
              </button>
            )}
          </div>
        ))}

        {participants.length === 0 && (
          <p className="px-4 py-8 text-center text-sm text-slate-400">
            Aucun participant identifié (juge non assigné ou aucune convocation envoyée).
          </p>
        )}
      </div>
    </div>
  )
}
