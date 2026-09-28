import { useEffect, useState } from 'react'
import { FileDown, Loader2 } from 'lucide-react'
import { listerAudiences } from '../../services/api/audiences'
import { LIBELLES_DECISION, decisionRendue, telechargerDecision } from '../../services/api/decisions'

// Liste des décisions rendues dans les dossiers accessibles à l'utilisateur
// (le backend ne renvoie que les audiences de ces dossiers), avec le PDF.
export default function DecisionsRendues({ titre, sousTitre }) {
  const [decisions, setDecisions] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')
  const [telechargement, setTelechargement] = useState(null)

  useEffect(() => {
    listerAudiences()
      .then((audiences) =>
        setDecisions(
          audiences
            .filter(decisionRendue)
            .sort((a, b) => new Date(b.date_heure) - new Date(a.date_heure)),
        ),
      )
      .catch(() => setErreur('Impossible de charger les décisions.'))
      .finally(() => setChargement(false))
  }, [])

  async function telecharger(audience) {
    setErreur('')
    setTelechargement(audience.id_audience)
    try {
      await telechargerDecision(audience)
    } catch {
      setErreur('Impossible de télécharger la décision.')
    } finally {
      setTelechargement(null)
    }
  }

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">{titre}</h1>
      <p className="text-sm text-slate-600 mt-1">{sousTitre}</p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      {chargement ? (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
          <Loader2 size={16} className="animate-spin" />
          Chargement...
        </div>
      ) : (
        <div className="mt-6 bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
          {decisions.map((a) => (
            <div key={a.id_audience} className="px-4 py-3.5">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-sm font-medium text-navy-900">
                    {LIBELLES_DECISION[a.type_decision] ?? a.type_decision} — {a.dossier?.parties}
                  </p>
                  <p className="text-xs text-slate-400">
                    {a.dossier?.numero} — audience du {new Date(a.date_heure).toLocaleDateString('fr-FR')}
                  </p>
                </div>
                <button
                  onClick={() => telecharger(a)}
                  disabled={telechargement === a.id_audience}
                  className="flex items-center gap-1 text-xs text-navy-900 font-medium hover:underline shrink-0 disabled:opacity-50"
                >
                  {telechargement === a.id_audience ? (
                    <Loader2 size={13} className="animate-spin" />
                  ) : (
                    <FileDown size={13} />
                  )}
                  PDF
                </button>
              </div>
              {a.motif_decision && (
                <p className="text-sm text-slate-600 mt-2 whitespace-pre-line">{a.motif_decision}</p>
              )}
            </div>
          ))}
          {decisions.length === 0 && (
            <p className="px-4 py-8 text-center text-sm text-slate-400">Aucune décision rendue pour le moment.</p>
          )}
        </div>
      )}
    </div>
  )
}
