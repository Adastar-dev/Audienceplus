import { useEffect, useState } from 'react'
import { ThumbsUp, ThumbsDown, Loader2 } from 'lucide-react'
import { listerConvocations, donnerAvisReport } from '../../services/api/convocations'
import ConvocationStatusBadge from '../../components/ui/ConvocationStatusBadge'
import { StatutConvocation, CanalNotification } from '../../constants/enums'

const LIBELLE_CANAL = {
  [CanalNotification.EMAIL]: 'Email',
  [CanalNotification.SMS]: 'SMS',
  [CanalNotification.APPEL_VOCAL]: 'Appel vocal',
  [CanalNotification.IN_APP]: 'In-app',
}

// Le greffier ne décide plus seul du report : il donne un avis (favorable,
// avec une date proposée, ou défavorable) qu'un juge doit ensuite valider.
export default function Convocations() {
  const [convocations, setConvocations] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')
  const [traitementId, setTraitementId] = useState(null)
  const [nouvelleDate, setNouvelleDate] = useState('')
  const [motifDefavorable, setMotifDefavorable] = useState('')
  const [enCours, setEnCours] = useState(false)

  useEffect(() => {
    listerConvocations()
      .then(setConvocations)
      .catch(() => setErreur('Impossible de charger les convocations.'))
      .finally(() => setChargement(false))
  }, [])

  function ouvrirTraitement(id) {
    setTraitementId(id)
    setNouvelleDate('')
    setMotifDefavorable('')
  }

  async function handleAvisFavorable(id) {
    if (!nouvelleDate) return
    setEnCours(true)
    try {
      const updated = await donnerAvisReport(id, 'FAVORABLE', { nouvelleDateHeure: nouvelleDate })
      setConvocations((list) => list.map((c) => (c.id_convocation === id ? updated : c)))
      setTraitementId(null)
    } catch {
      setErreur("Impossible d'enregistrer l'avis sur cette demande de report.")
    } finally {
      setEnCours(false)
    }
  }

  async function handleAvisDefavorable(id) {
    if (!motifDefavorable.trim()) return
    setEnCours(true)
    try {
      const updated = await donnerAvisReport(id, 'DEFAVORABLE', { reponseGreffier: motifDefavorable })
      setConvocations((list) => list.map((c) => (c.id_convocation === id ? updated : c)))
      setTraitementId(null)
    } catch {
      setErreur("Impossible d'enregistrer l'avis sur cette demande de report.")
    } finally {
      setEnCours(false)
    }
  }

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Convocations</h1>
      <p className="text-sm text-slate-600 mt-1">{convocations.length} convocations</p>
      <p className="text-xs text-slate-400 mt-1">
        Votre avis sur une demande de report est transmis au juge, qui rend la décision finale.
      </p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      {chargement ? (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
          <Loader2 size={16} className="animate-spin" />
          Chargement...
        </div>
      ) : (
        <div className="mt-6 bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
          {convocations.map((c) => (
            <div key={c.id_convocation} className="px-4 py-3.5">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-navy-900">{c.audience?.dossier?.numero}</p>
                  <p className="text-xs text-slate-400">
                    {LIBELLE_CANAL[c.canal]} —{' '}
                    {c.audience?.date_heure && new Date(c.audience.date_heure).toLocaleDateString('fr-FR')}
                  </p>
                  {c.statut === StatutConvocation.REPORT_DEMANDE && c.motif_report && (
                    <p className="text-xs text-slate-500 mt-1">Motif : {c.motif_report}</p>
                  )}
                  {c.statut === StatutConvocation.REPORT_REFUSEE && c.reponse_greffier && (
                    <p className="text-xs text-slate-500 mt-1">Réponse : {c.reponse_greffier}</p>
                  )}
                </div>
                <div className="flex items-center gap-3">
                  <ConvocationStatusBadge statut={c.statut} />
                  {c.statut === StatutConvocation.REPORT_DEMANDE && traitementId !== c.id_convocation && (
                    <button
                      onClick={() => ouvrirTraitement(c.id_convocation)}
                      className="text-xs text-navy-900 font-medium hover:underline"
                    >
                      Donner un avis
                    </button>
                  )}
                </div>
              </div>

              {traitementId === c.id_convocation && (
                <div className="mt-3 pt-3 border-t border-slate-100 space-y-3">
                  <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">
                      Nouvelle date proposée (si avis favorable)
                    </label>
                    <input
                      type="datetime-local"
                      value={nouvelleDate}
                      onChange={(e) => setNouvelleDate(e.target.value)}
                      className="border border-slate-200 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
                    />
                    <button
                      onClick={() => handleAvisFavorable(c.id_convocation)}
                      disabled={enCours || !nouvelleDate}
                      className="ml-2 inline-flex items-center gap-1 bg-success-700 text-white text-xs font-medium rounded px-3 py-1.5 hover:bg-navy-800 disabled:opacity-40 transition-colors"
                    >
                      <ThumbsUp size={13} />
                      Avis favorable
                    </button>
                  </div>

                  <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">
                      Motif (si avis défavorable)
                    </label>
                    <textarea
                      value={motifDefavorable}
                      onChange={(e) => setMotifDefavorable(e.target.value)}
                      rows={2}
                      className="w-full border border-slate-200 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
                    />
                    <div className="flex items-center gap-2 mt-1.5">
                      <button
                        onClick={() => handleAvisDefavorable(c.id_convocation)}
                        disabled={enCours || !motifDefavorable.trim()}
                        className="inline-flex items-center gap-1 bg-danger-700 text-white text-xs font-medium rounded px-3 py-1.5 hover:bg-navy-800 disabled:opacity-40 transition-colors"
                      >
                        <ThumbsDown size={13} />
                        Avis défavorable
                      </button>
                      <button
                        onClick={() => setTraitementId(null)}
                        className="text-xs text-slate-500 hover:text-navy-900"
                      >
                        Annuler
                      </button>
                    </div>
                  </div>
                </div>
              )}
            </div>
          ))}

          {convocations.length === 0 && (
            <p className="px-4 py-8 text-center text-sm text-slate-400">Aucune convocation.</p>
          )}
        </div>
      )}
    </div>
  )
}
