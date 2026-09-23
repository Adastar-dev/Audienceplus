import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Loader2, Video } from 'lucide-react'
import { listerConvocations } from '../../services/api/convocations'
import {
  listerDemandesDistance,
  creerDemandeDistance,
} from '../../services/api/demandesDistance'
import { ModeAudience, StatutDemandeDistance, STATUT_DEMANDE_DISTANCE_LABELS } from '../../constants/enums'
import Badge from '../../components/ui/Badge'

const TONE_PAR_STATUT = {
  [StatutDemandeDistance.EN_ATTENTE]: 'warning',
  [StatutDemandeDistance.APPROUVEE]: 'success',
  [StatutDemandeDistance.REFUSEE]: 'danger',
}

export default function DemandeDistance() {
  const [convocations, setConvocations] = useState([])
  const [demandes, setDemandes] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')
  const [ouvertPour, setOuvertPour] = useState(null)
  const [motif, setMotif] = useState('')
  const [enCours, setEnCours] = useState(false)

  useEffect(() => {
    Promise.all([listerConvocations(), listerDemandesDistance()])
      .then(([convocationsData, demandesData]) => {
        setConvocations(convocationsData)
        setDemandes(demandesData)
      })
      .catch(() => setErreur('Impossible de charger vos audiences.'))
      .finally(() => setChargement(false))
  }, [])

  function demandeExistante(idAudience) {
    return demandes.find((d) => d.id_audience === idAudience)
  }

  async function handleEnvoyer(idAudience) {
    if (!motif.trim()) return
    setEnCours(true)
    try {
      const nouvelle = await creerDemandeDistance(idAudience, motif)
      setDemandes((list) => [nouvelle, ...list])
      setOuvertPour(null)
      setMotif('')
    } catch {
      setErreur("Impossible d'envoyer la demande.")
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

  const audiencesPresentielles = convocations
    .map((c) => c.audience)
    .filter((a) => a && a.mode === ModeAudience.PRESENTIEL)

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Assister à distance</h1>
      <p className="text-sm text-slate-600 mt-1">
        Vos audiences sont prévues en présentiel par défaut. Vous pouvez demander l'autorisation
        du greffier pour y assister à distance.
      </p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      <div className="mt-6 space-y-3">
        {audiencesPresentielles.map((a) => {
          const demande = demandeExistante(a.id_audience)
          return (
            <div key={a.id_audience} className="bg-white border border-slate-200 rounded-md p-5">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-navy-900">{a.dossier?.numero}</p>
                  <p className="text-xs text-slate-400">
                    {new Date(a.date_heure).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })}
                  </p>
                </div>

                {demande ? (
                  <Badge tone={TONE_PAR_STATUT[demande.statut]}>
                    {STATUT_DEMANDE_DISTANCE_LABELS[demande.statut]}
                  </Badge>
                ) : ouvertPour === a.id_audience ? null : (
                  <button
                    onClick={() => setOuvertPour(a.id_audience)}
                    className="flex items-center gap-1.5 text-xs text-navy-900 font-medium hover:underline"
                  >
                    <Video size={13} />
                    Demander à assister à distance
                  </button>
                )}
              </div>

              {demande?.statut === StatutDemandeDistance.APPROUVEE && (
                <Link
                  to={`/justiciable/audiences/${a.id_audience}/rejoindre`}
                  className="inline-flex items-center gap-1.5 mt-3 text-xs text-navy-900 font-medium hover:underline"
                >
                  Rejoindre l'audience à distance →
                </Link>
              )}
              {demande?.statut === StatutDemandeDistance.REFUSEE && demande.reponse_greffier && (
                <p className="mt-3 text-xs text-slate-500">Motif du refus : {demande.reponse_greffier}</p>
              )}

              {ouvertPour === a.id_audience && !demande && (
                <div className="mt-3 pt-3 border-t border-slate-100">
                  <textarea
                    value={motif}
                    onChange={(e) => setMotif(e.target.value)}
                    rows={3}
                    placeholder="Expliquez pourquoi vous ne pouvez pas vous déplacer..."
                    className="w-full border border-slate-200 rounded px-3 py-2 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-navy-700"
                  />
                  <div className="flex items-center gap-3">
                    <button
                      onClick={() => handleEnvoyer(a.id_audience)}
                      disabled={enCours || !motif.trim()}
                      className="bg-navy-900 text-white text-xs font-medium rounded px-3 py-1.5 hover:bg-navy-800 disabled:opacity-40 transition-colors"
                    >
                      Envoyer la demande
                    </button>
                    <button
                      onClick={() => setOuvertPour(null)}
                      className="text-xs text-slate-600 hover:text-navy-900"
                    >
                      Annuler
                    </button>
                  </div>
                </div>
              )}
            </div>
          )
        })}

        {audiencesPresentielles.length === 0 && (
          <p className="text-sm text-slate-400">Aucune audience en présentiel pour le moment.</p>
        )}
      </div>
    </div>
  )
}
