import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Loader2 } from 'lucide-react'
import { listerConvocations } from '../../services/api/convocations'
import ConvocationStatusBadge from '../../components/ui/ConvocationStatusBadge'

export default function ConvocationsAvocat() {
  const [convocations, setConvocations] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    listerConvocations()
      .then(setConvocations)
      .catch(() => setErreur('Impossible de charger les convocations.'))
      .finally(() => setChargement(false))
  }, [])

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Convocations reçues</h1>
      <p className="text-sm text-slate-600 mt-1">{convocations.length} convocations</p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      {chargement ? (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
          <Loader2 size={16} className="animate-spin" />
          Chargement...
        </div>
      ) : (
        <div className="mt-6 bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
          {convocations.map((c) => (
            <div key={c.id_convocation} className="flex items-center justify-between px-4 py-3.5">
              <div>
                <p className="text-sm font-medium text-navy-900">{c.audience?.dossier?.numero}</p>
                <p className="text-xs text-slate-400">
                  {c.audience?.date_heure &&
                    new Date(c.audience.date_heure).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })}
                </p>
              </div>
              <div className="flex items-center gap-3">
                <ConvocationStatusBadge statut={c.statut} />
                {c.audience?.id_dossier && (
                  <Link to={`/avocat/dossiers/${c.audience.id_dossier}`} className="text-xs text-navy-700 hover:underline">
                    Voir le dossier
                  </Link>
                )}
              </div>
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
