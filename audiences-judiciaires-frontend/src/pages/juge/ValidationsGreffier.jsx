import { useEffect, useState } from 'react'
import { Check, X, Loader2 } from 'lucide-react'
import { listerDemandesDistance, approuverDemandeDistance, refuserDemandeDistance } from '../../services/api/demandesDistance'
import { listerConvocations, approuverReport, refuserReport } from '../../services/api/convocations'
import { StatutDemandeDistance, StatutConvocation } from '../../constants/enums'
import Badge from '../../components/ui/Badge'

// Décision finale sur les avis du greffier : demandes de comparution à
// distance et demandes de report de convocation. Le greffier a déjà donné
// son avis (favorable ou défavorable) ; le juge valide ou non cet avis.
export default function ValidationsGreffier() {
  const [demandes, setDemandes] = useState([])
  const [convocations, setConvocations] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  const [refusOuvert, setRefusOuvert] = useState(null) // { type: 'demande' | 'report', id }
  const [commentaire, setCommentaire] = useState('')
  const [enCours, setEnCours] = useState(false)

  useEffect(() => {
    Promise.all([listerDemandesDistance(), listerConvocations()])
      .then(([d, c]) => {
        setDemandes(d)
        setConvocations(c)
      })
      .catch(() => setErreur('Impossible de charger les demandes en attente de votre validation.'))
      .finally(() => setChargement(false))
  }, [])

  const demandesEnAttente = demandes.filter((d) =>
    [StatutDemandeDistance.AVIS_GREFFIER_FAVORABLE, StatutDemandeDistance.AVIS_GREFFIER_DEFAVORABLE].includes(d.statut),
  )
  const reportsEnAttente = convocations.filter((c) =>
    [StatutConvocation.AVIS_GREFFIER_FAVORABLE, StatutConvocation.AVIS_GREFFIER_DEFAVORABLE].includes(c.statut),
  )

  async function approuverLaDemande(id) {
    setEnCours(true)
    try {
      await approuverDemandeDistance(id)
      setDemandes((list) => list.filter((d) => d.id_demande !== id))
    } catch {
      setErreur("Impossible d'approuver cette demande.")
    } finally {
      setEnCours(false)
    }
  }

  async function refuserLaDemande(id) {
    if (!commentaire.trim()) return
    setEnCours(true)
    try {
      await refuserDemandeDistance(id, commentaire)
      setDemandes((list) => list.filter((d) => d.id_demande !== id))
      setRefusOuvert(null)
      setCommentaire('')
    } catch {
      setErreur('Impossible de refuser cette demande.')
    } finally {
      setEnCours(false)
    }
  }

  async function approuverLeReport(c) {
    setEnCours(true)
    try {
      await approuverReport(c.id_convocation, c.nouvelle_date_proposee)
      setConvocations((list) => list.filter((x) => x.id_convocation !== c.id_convocation))
    } catch {
      setErreur("Impossible d'approuver ce report.")
    } finally {
      setEnCours(false)
    }
  }

  async function refuserLeReport(id) {
    if (!commentaire.trim()) return
    setEnCours(true)
    try {
      await refuserReport(id, commentaire)
      setConvocations((list) => list.filter((c) => c.id_convocation !== id))
      setRefusOuvert(null)
      setCommentaire('')
    } catch {
      setErreur('Impossible de refuser ce report.')
    } finally {
      setEnCours(false)
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

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Validations du greffier</h1>
      <p className="text-sm text-slate-600 mt-1">
        Avis du greffier en attente de votre décision finale.
      </p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      <h2 className="text-sm font-medium text-navy-900 mt-8 mb-3">Comparutions à distance</h2>
      <div className="space-y-3">
        {demandesEnAttente.map((d) => (
          <div key={d.id_demande} className="bg-white border border-slate-200 rounded-md p-5">
            <div className="flex items-start justify-between">
              <div>
                <p className="text-sm font-medium text-navy-900">{d.utilisateur?.nom}</p>
                <p className="text-xs text-slate-400">
                  {d.audience?.dossier?.numero} —{' '}
                  {d.audience?.date_heure &&
                    new Date(d.audience.date_heure).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })}
                </p>
                <p className="text-sm text-slate-600 mt-2">Motif : {d.motif}</p>
              </div>
              <Badge tone={d.statut === StatutDemandeDistance.AVIS_GREFFIER_FAVORABLE ? 'success' : 'danger'}>
                Avis {d.statut === StatutDemandeDistance.AVIS_GREFFIER_FAVORABLE ? 'favorable' : 'défavorable'} du greffe
              </Badge>
            </div>
            <p className="text-xs text-slate-500 mt-2">
              Par {d.greffier_qui_a_donne_avis?.nom ?? 'le greffier'}
              {d.reponse_greffier && ` — ${d.reponse_greffier}`}
            </p>

            {refusOuvert?.type === 'demande' && refusOuvert.id === d.id_demande ? (
              <div className="mt-3 pt-3 border-t border-slate-100">
                <textarea
                  value={commentaire}
                  onChange={(e) => setCommentaire(e.target.value)}
                  rows={2}
                  placeholder="Motif du refus..."
                  className="w-full border border-slate-200 rounded px-3 py-2 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-navy-700"
                />
                <div className="flex items-center gap-3">
                  <button
                    onClick={() => refuserLaDemande(d.id_demande)}
                    disabled={!commentaire.trim() || enCours}
                    className="bg-danger-700 text-white text-xs font-medium rounded px-3 py-1.5 hover:opacity-90 disabled:opacity-40 transition-opacity"
                  >
                    Confirmer le refus
                  </button>
                  <button
                    onClick={() => setRefusOuvert(null)}
                    className="text-xs text-slate-600 hover:text-navy-900"
                  >
                    Annuler
                  </button>
                </div>
              </div>
            ) : (
              <div className="flex items-center gap-3 mt-3 pt-3 border-t border-slate-100">
                <button
                  onClick={() => approuverLaDemande(d.id_demande)}
                  disabled={enCours}
                  className="flex items-center gap-1.5 bg-navy-900 text-white text-xs font-medium rounded px-3 py-1.5 hover:bg-navy-800 disabled:opacity-40 transition-colors"
                >
                  <Check size={13} />
                  Approuver
                </button>
                <button
                  onClick={() => {
                    setRefusOuvert({ type: 'demande', id: d.id_demande })
                    setCommentaire('')
                  }}
                  disabled={enCours}
                  className="flex items-center gap-1.5 border border-slate-200 text-slate-600 text-xs font-medium rounded px-3 py-1.5 hover:bg-slate-50 transition-colors"
                >
                  <X size={13} />
                  Refuser
                </button>
              </div>
            )}
          </div>
        ))}
        {demandesEnAttente.length === 0 && (
          <p className="text-sm text-slate-400">Aucune demande de comparution en attente de votre décision.</p>
        )}
      </div>

      <h2 className="text-sm font-medium text-navy-900 mt-8 mb-3">Reports de convocation</h2>
      <div className="space-y-3">
        {reportsEnAttente.map((c) => (
          <div key={c.id_convocation} className="bg-white border border-slate-200 rounded-md p-5">
            <div className="flex items-start justify-between">
              <div>
                <p className="text-sm font-medium text-navy-900">{c.audience?.dossier?.numero}</p>
                <p className="text-xs text-slate-400">
                  Audience actuelle :{' '}
                  {c.audience?.date_heure && new Date(c.audience.date_heure).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })}
                </p>
                {c.motif_report && <p className="text-sm text-slate-600 mt-2">Motif du report : {c.motif_report}</p>}
                {c.nouvelle_date_proposee && (
                  <p className="text-sm text-slate-600 mt-1">
                    Nouvelle date proposée par le greffe :{' '}
                    {new Date(c.nouvelle_date_proposee).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })}
                  </p>
                )}
              </div>
              <Badge tone={c.statut === StatutConvocation.AVIS_GREFFIER_FAVORABLE ? 'success' : 'danger'}>
                Avis {c.statut === StatutConvocation.AVIS_GREFFIER_FAVORABLE ? 'favorable' : 'défavorable'} du greffe
              </Badge>
            </div>
            {c.reponse_greffier && <p className="text-xs text-slate-500 mt-2">Commentaire du greffe : {c.reponse_greffier}</p>}

            {refusOuvert?.type === 'report' && refusOuvert.id === c.id_convocation ? (
              <div className="mt-3 pt-3 border-t border-slate-100">
                <textarea
                  value={commentaire}
                  onChange={(e) => setCommentaire(e.target.value)}
                  rows={2}
                  placeholder="Motif du refus..."
                  className="w-full border border-slate-200 rounded px-3 py-2 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-navy-700"
                />
                <div className="flex items-center gap-3">
                  <button
                    onClick={() => refuserLeReport(c.id_convocation)}
                    disabled={!commentaire.trim() || enCours}
                    className="bg-danger-700 text-white text-xs font-medium rounded px-3 py-1.5 hover:opacity-90 disabled:opacity-40 transition-opacity"
                  >
                    Confirmer le refus
                  </button>
                  <button
                    onClick={() => setRefusOuvert(null)}
                    className="text-xs text-slate-600 hover:text-navy-900"
                  >
                    Annuler
                  </button>
                </div>
              </div>
            ) : (
              <div className="flex items-center gap-3 mt-3 pt-3 border-t border-slate-100">
                <button
                  onClick={() => approuverLeReport(c)}
                  disabled={enCours || (c.statut === StatutConvocation.AVIS_GREFFIER_FAVORABLE && !c.nouvelle_date_proposee)}
                  className="flex items-center gap-1.5 bg-navy-900 text-white text-xs font-medium rounded px-3 py-1.5 hover:bg-navy-800 disabled:opacity-40 transition-colors"
                >
                  <Check size={13} />
                  Approuver
                </button>
                <button
                  onClick={() => {
                    setRefusOuvert({ type: 'report', id: c.id_convocation })
                    setCommentaire('')
                  }}
                  disabled={enCours}
                  className="flex items-center gap-1.5 border border-slate-200 text-slate-600 text-xs font-medium rounded px-3 py-1.5 hover:bg-slate-50 transition-colors"
                >
                  <X size={13} />
                  Refuser
                </button>
              </div>
            )}
          </div>
        ))}
        {reportsEnAttente.length === 0 && (
          <p className="text-sm text-slate-400">Aucune demande de report en attente de votre décision.</p>
        )}
      </div>
    </div>
  )
}
