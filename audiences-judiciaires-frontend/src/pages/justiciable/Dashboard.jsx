import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { CalendarClock, Check, RotateCcw, Loader2 } from 'lucide-react'
import { listerConvocations, confirmerConvocation, demanderReport } from '../../services/api/convocations'
import { StatutConvocation } from '../../constants/enums'
import ConvocationStatusBadge from '../../components/ui/ConvocationStatusBadge'

export default function JusticiableDashboard() {
  const [convocations, setConvocations] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')
  const [showReportForm, setShowReportForm] = useState(false)
  const [motif, setMotif] = useState('')
  const [enCours, setEnCours] = useState(false)

  useEffect(() => {
    listerConvocations()
      .then(setConvocations)
      .catch(() => setErreur('Impossible de charger vos convocations.'))
      .finally(() => setChargement(false))
  }, [])

  const convocation = convocations[0]

  async function handleConfirmer() {
    setEnCours(true)
    try {
      const updated = await confirmerConvocation(convocation.id_convocation)
      setConvocations((list) => list.map((c) => (c.id_convocation === updated.id_convocation ? updated : c)))
    } catch {
      setErreur('Impossible de confirmer votre présence.')
    } finally {
      setEnCours(false)
    }
  }

  async function handleDemanderReport() {
    if (!motif.trim()) return
    setEnCours(true)
    try {
      const updated = await demanderReport(convocation.id_convocation, motif)
      setConvocations((list) => list.map((c) => (c.id_convocation === updated.id_convocation ? updated : c)))
      setShowReportForm(false)
    } catch {
      setErreur('Impossible de transmettre votre demande.')
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
      <h1 className="font-display text-2xl text-navy-900">Bonjour</h1>
      <p className="text-sm text-slate-600 mt-1">Voici votre prochaine convocation.</p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      {!convocation ? (
        <p className="text-sm text-slate-400 mt-6">Aucune convocation pour le moment.</p>
      ) : (
        <div className="mt-6 bg-white border border-slate-200 rounded-md p-6">
          <div className="flex items-start justify-between mb-4">
            <div>
              <p className="text-xs text-slate-400 mb-1">{convocation.audience?.dossier?.numero}</p>
              <div className="flex items-center gap-2 text-navy-900">
                <CalendarClock size={18} />
                <span className="font-medium">
                  {new Date(convocation.audience?.date_heure).toLocaleString('fr-FR', {
                    dateStyle: 'full',
                    timeStyle: 'short',
                  })}
                </span>
              </div>
            </div>
            <ConvocationStatusBadge statut={convocation.statut} />
          </div>

          {(convocation.statut === StatutConvocation.ENVOYEE || convocation.statut === StatutConvocation.RECUE) &&
            !showReportForm && (
              <div className="flex items-center gap-3 pt-4 border-t border-slate-100">
                <button
                  onClick={handleConfirmer}
                  disabled={enCours}
                  className="flex items-center gap-2 bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 disabled:opacity-60 transition-colors"
                >
                  <Check size={15} />
                  Confirmer ma présence
                </button>
                <button
                  onClick={() => setShowReportForm(true)}
                  className="flex items-center gap-2 border border-slate-200 text-slate-600 text-sm font-medium rounded px-4 py-2 hover:bg-slate-50 transition-colors"
                >
                  <RotateCcw size={15} />
                  Demander un report
                </button>
              </div>
            )}

          {showReportForm && (
            <div className="pt-4 border-t border-slate-100">
              <label className="block text-sm font-medium text-slate-600 mb-1.5">Motif du report</label>
              <textarea
                value={motif}
                onChange={(e) => setMotif(e.target.value)}
                rows={3}
                placeholder="Expliquez brièvement la raison de votre demande..."
                className="w-full border border-slate-200 rounded px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-navy-700"
              />
              <div className="flex items-center gap-3">
                <button
                  onClick={handleDemanderReport}
                  disabled={enCours || !motif.trim()}
                  className="bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 disabled:opacity-40 transition-colors"
                >
                  Envoyer la demande
                </button>
                <button onClick={() => setShowReportForm(false)} className="text-sm text-slate-600 hover:text-navy-900">
                  Annuler
                </button>
              </div>
            </div>
          )}

          {convocation.statut === StatutConvocation.CONFIRMEE && (
            <div className="pt-4 border-t border-slate-100">
              <Link to="/justiciable/dossier" className="text-sm text-navy-900 font-medium hover:underline">
                Voir mon dossier →
              </Link>
            </div>
          )}

          {convocation.statut === StatutConvocation.REPORT_DEMANDE && (
            <p className="pt-4 border-t border-slate-100 text-sm text-slate-400">
              Votre demande a été transmise au tribunal. Vous serez notifié de la décision.
            </p>
          )}
        </div>
      )}
    </div>
  )
}
