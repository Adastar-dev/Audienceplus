import { useEffect, useState } from 'react'
import { Loader2 } from 'lucide-react'
import { listerAudiences } from '../../services/api/audiences'
import { StatutAudience } from '../../constants/enums'
import AudienceStatusBadge from '../../components/ui/AudienceStatusBadge'

export default function DecisionsProcureur() {
  const [audiences, setAudiences] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    listerAudiences()
      .then(setAudiences)
      .catch(() => setErreur('Impossible de charger les décisions.'))
      .finally(() => setChargement(false))
  }, [])

  const rendues = audiences.filter((a) => a.statut === StatutAudience.CLOTUREE)

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Décisions rendues</h1>
      <p className="text-sm text-slate-600 mt-1">{rendues.length} décisions consultables</p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      {chargement ? (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
          <Loader2 size={16} className="animate-spin" />
          Chargement...
        </div>
      ) : (
        <div className="mt-6 bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
          {rendues.map((a) => (
            <div key={a.id_audience} className="flex items-center justify-between px-4 py-3.5">
              <div>
                <p className="text-sm font-medium text-navy-900">{a.dossier?.parties}</p>
                <p className="text-xs text-slate-400">
                  {a.dossier?.numero} — {new Date(a.date_heure).toLocaleDateString('fr-FR')}
                </p>
              </div>
              <AudienceStatusBadge statut={a.statut} />
            </div>
          ))}
          {rendues.length === 0 && (
            <p className="px-4 py-8 text-center text-sm text-slate-400">Aucune décision disponible.</p>
          )}
        </div>
      )}
    </div>
  )
}
