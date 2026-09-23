import { useEffect, useState } from 'react'
import { ThumbsUp, ThumbsDown, Loader2 } from 'lucide-react'
import { listerDemandesDistance, donnerAvisDemandeDistance } from '../../services/api/demandesDistance'
import { StatutDemandeDistance, STATUT_DEMANDE_DISTANCE_LABELS } from '../../constants/enums'
import Badge from '../../components/ui/Badge'
import { statusTone } from '../../utils/statusTone'

// Le greffier ne décide plus seul : il donne un avis (favorable ou
// défavorable) qu'un juge doit ensuite valider pour que la demande soit
// réellement approuvée ou refusée.
export default function DemandesDistance() {
  const [demandes, setDemandes] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')
  const [ouvertPourAvisDefavorable, setOuvertPourAvisDefavorable] = useState(null)
  const [commentaire, setCommentaire] = useState('')

  useEffect(() => {
    listerDemandesDistance()
      .then(setDemandes)
      .catch(() => setErreur('Impossible de charger les demandes.'))
      .finally(() => setChargement(false))
  }, [])

  async function handleAvisFavorable(id) {
    try {
      const updated = await donnerAvisDemandeDistance(id, 'FAVORABLE')
      setDemandes((list) => list.map((d) => (d.id_demande === id ? updated : d)))
    } catch {
      setErreur("Impossible d'enregistrer l'avis.")
    }
  }

  async function handleAvisDefavorable(id) {
    if (!commentaire.trim()) return
    try {
      const updated = await donnerAvisDemandeDistance(id, 'DEFAVORABLE', commentaire)
      setDemandes((list) => list.map((d) => (d.id_demande === id ? updated : d)))
      setOuvertPourAvisDefavorable(null)
      setCommentaire('')
    } catch {
      setErreur("Impossible d'enregistrer l'avis.")
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

  const enAttente = demandes.filter((d) => d.statut === StatutDemandeDistance.EN_ATTENTE)
  const traitees = demandes.filter((d) => d.statut !== StatutDemandeDistance.EN_ATTENTE)

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Demandes d'audience à distance</h1>
      <p className="text-sm text-slate-600 mt-1">
        {enAttente.length} demande{enAttente.length > 1 ? 's' : ''} en attente de votre avis
      </p>
      <p className="text-xs text-slate-400 mt-1">
        Votre avis est transmis au juge, qui rend la décision finale.
      </p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      <div className="mt-6 space-y-3">
        {enAttente.map((d) => (
          <div key={d.id_demande} className="bg-white border border-slate-200 rounded-md p-5">
            <div className="flex items-start justify-between">
              <div>
                <p className="text-sm font-medium text-navy-900">{d.utilisateur?.nom}</p>
                <p className="text-xs text-slate-400">
                  {d.audience?.dossier?.numero} —{' '}
                  {d.audience?.date_heure &&
                    new Date(d.audience.date_heure).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })}
                </p>
                <p className="text-sm text-slate-600 mt-2">{d.motif}</p>
              </div>
              <Badge tone={statusTone(d.statut)}>{STATUT_DEMANDE_DISTANCE_LABELS[d.statut]}</Badge>
            </div>

            {ouvertPourAvisDefavorable === d.id_demande ? (
              <div className="mt-3 pt-3 border-t border-slate-100">
                <textarea
                  value={commentaire}
                  onChange={(e) => setCommentaire(e.target.value)}
                  rows={2}
                  placeholder="Motif de l'avis défavorable..."
                  className="w-full border border-slate-200 rounded px-3 py-2 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-navy-700"
                />
                <div className="flex items-center gap-3">
                  <button
                    onClick={() => handleAvisDefavorable(d.id_demande)}
                    disabled={!commentaire.trim()}
                    className="bg-danger-700 text-white text-xs font-medium rounded px-3 py-1.5 hover:opacity-90 disabled:opacity-40 transition-opacity"
                  >
                    Confirmer l'avis défavorable
                  </button>
                  <button
                    onClick={() => setOuvertPourAvisDefavorable(null)}
                    className="text-xs text-slate-600 hover:text-navy-900"
                  >
                    Annuler
                  </button>
                </div>
              </div>
            ) : (
              <div className="flex items-center gap-3 mt-3 pt-3 border-t border-slate-100">
                <button
                  onClick={() => handleAvisFavorable(d.id_demande)}
                  className="flex items-center gap-1.5 bg-navy-900 text-white text-xs font-medium rounded px-3 py-1.5 hover:bg-navy-800 transition-colors"
                >
                  <ThumbsUp size={13} />
                  Avis favorable
                </button>
                <button
                  onClick={() => setOuvertPourAvisDefavorable(d.id_demande)}
                  className="flex items-center gap-1.5 border border-slate-200 text-slate-600 text-xs font-medium rounded px-3 py-1.5 hover:bg-slate-50 transition-colors"
                >
                  <ThumbsDown size={13} />
                  Avis défavorable
                </button>
              </div>
            )}
          </div>
        ))}

        {enAttente.length === 0 && (
          <p className="text-sm text-slate-400">Aucune demande en attente de votre avis.</p>
        )}
      </div>

      {traitees.length > 0 && (
        <>
          <h2 className="text-sm font-medium text-navy-900 mt-8 mb-3">Demandes transmises au juge / traitées</h2>
          <div className="bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
            {traitees.map((d) => (
              <div key={d.id_demande} className="flex items-center justify-between px-4 py-3">
                <div>
                  <p className="text-sm text-navy-900">{d.utilisateur?.nom}</p>
                  <p className="text-xs text-slate-400">{d.audience?.dossier?.numero}</p>
                </div>
                <Badge tone={statusTone(d.statut)}>{STATUT_DEMANDE_DISTANCE_LABELS[d.statut]}</Badge>
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  )
}
