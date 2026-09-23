import { useEffect, useState } from 'react'
import { FileDown, Loader2 } from 'lucide-react'
import { listerConvocations } from '../../services/api/convocations'
import { getPV } from '../../services/api/procesVerbaux'
import { StatutAudience, StatutPV } from '../../constants/enums'

export default function Decisions() {
  const [decisions, setDecisions] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    listerConvocations()
      .then(async (convocations) => {
        const audiencesCloturees = convocations
          .map((c) => c.audience)
          .filter((a) => a && a.statut === StatutAudience.CLOTUREE)

        const avecPv = await Promise.all(
          audiencesCloturees.map(async (a) => ({ audience: a, pv: await getPV(a.id_audience) })),
        )
        setDecisions(avecPv.filter((d) => d.pv?.statut === StatutPV.CLOTURE))
      })
      .catch(() => setErreur('Impossible de charger vos décisions.'))
      .finally(() => setChargement(false))
  }, [])

  function telecharger(a, pv) {
    const blob = new Blob(
      [`DÉCISION\n\n${a.dossier?.parties ?? ''}\n${a.dossier?.numero ?? ''}\n\n${pv.contenu}`],
      { type: 'text/plain' },
    )
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `decision_${a.id_audience}.txt`
    link.click()
    URL.revokeObjectURL(url)
  }

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Décisions</h1>
      <p className="text-sm text-slate-600 mt-1">Décisions rendues pour votre dossier.</p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      {chargement ? (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
          <Loader2 size={16} className="animate-spin" />
          Chargement...
        </div>
      ) : (
        <div className="mt-6 bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
          {decisions.map(({ audience: a, pv }) => (
            <div key={a.id_audience} className="flex items-center justify-between px-4 py-3.5">
              <div>
                <p className="text-sm font-medium text-navy-900">
                  Audience du {new Date(a.date_heure).toLocaleDateString('fr-FR')}
                </p>
                <p className="text-xs text-slate-400">{a.dossier?.numero}</p>
              </div>
              <button
                onClick={() => telecharger(a, pv)}
                className="flex items-center gap-1 text-xs text-navy-900 font-medium hover:underline"
              >
                <FileDown size={13} />
                Télécharger
              </button>
            </div>
          ))}
          {decisions.length === 0 && (
            <p className="px-4 py-8 text-center text-sm text-slate-400">Aucune décision disponible pour le moment.</p>
          )}
        </div>
      )}
    </div>
  )
}
