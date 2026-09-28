import { useEffect, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { ArrowLeft, PenLine, Undo2, Loader2 } from 'lucide-react'
import { getAudienceById } from '../../services/api/audiences'
import { listerParticipants, marquerPresent, marquerAbsent, admettreParticipant, refuserParticipant } from '../../services/api/participants'
import { ROLE_LABELS } from '../../constants/enums'

// Indicateurs de la salle d'attente pour le greffier : compte vérifié par
// l'administration (photo de CNI) et code de vérification confirmé.
const ROLES_EN_SALLE_ATTENTE = ['JUSTICIABLE', 'AVOCAT', 'PROCUREUR']

function IndicateursIdentite({ participation }) {
  if (!ROLES_EN_SALLE_ATTENTE.includes(participation.role_audience)) return null
  const compteVerifie = participation.utilisateur?.identite_verifiee

  return (
    <p className="text-xs mt-0.5 flex gap-3">
      <span className={participation.identite_confirmee_otp ? 'text-success-700' : 'text-slate-400'}>
        {participation.identite_confirmee_otp ? 'Code confirmé' : 'Code non confirmé'}
      </span>
      {participation.role_audience !== 'PROCUREUR' && (
        <span className={compteVerifie ? 'text-success-700' : 'text-danger-700'}>
          {compteVerifie ? 'Compte vérifié' : 'Compte non vérifié'}
        </span>
      )}
    </p>
  )
}

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

  // Rafraîchit la liste pour voir arriver les participants en salle d'attente.
  useEffect(() => {
    const timer = setInterval(() => {
      listerParticipants(id).then(setParticipants).catch(() => {})
    }, 5000)
    return () => clearInterval(timer)
  }, [id])

  async function handleAdmission(idParticipation, admettre) {
    setEnCours(idParticipation)
    try {
      const maj = admettre
        ? await admettreParticipant(id, idParticipation)
        : await refuserParticipant(id, idParticipation)
      setParticipants((list) => list.map((p) => (p.id_participation === idParticipation ? { ...p, ...maj } : p)))
    } catch {
      setErreur(admettre ? "Impossible d'admettre ce participant." : 'Impossible de refuser ce participant.')
    } finally {
      setEnCours(null)
    }
  }

  async function handleMarquerPresent(idParticipation) {
    setEnCours(idParticipation)
    try {
      await marquerPresent(id, idParticipation)
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
              <IndicateursIdentite participation={p} />
              {ROLES_EN_SALLE_ATTENTE.includes(p.role_audience) && p.identite_confirmee_otp && !p.admis && (
                <div className="flex gap-2 mt-1.5">
                  <button
                    onClick={() => handleAdmission(p.id_participation, true)}
                    disabled={enCours === p.id_participation}
                    className="text-xs font-medium text-success-700 hover:underline disabled:opacity-40"
                  >
                    Faire entrer dans la salle
                  </button>
                  <button
                    onClick={() => handleAdmission(p.id_participation, false)}
                    disabled={enCours === p.id_participation}
                    className="text-xs text-danger-700 hover:underline disabled:opacity-40"
                  >
                    Refuser
                  </button>
                </div>
              )}
              {ROLES_EN_SALLE_ATTENTE.includes(p.role_audience) && p.admis && (
                <p className="text-xs text-success-700 mt-1">Admis dans la salle</p>
              )}
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
                {enCours === p.id_participation ? 'Scellement...' : 'Marquer présent et sceller'}
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
