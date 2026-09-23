import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Loader2 } from 'lucide-react'
import { listerDossiers } from '../../services/api/dossiers'
import DossierStatusBadge from '../../components/ui/DossierStatusBadge'

export default function DossiersAvocat() {
  const navigate = useNavigate()
  const [dossiers, setDossiers] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    listerDossiers()
      .then(setDossiers)
      .catch(() => setErreur('Impossible de charger vos dossiers.'))
      .finally(() => setChargement(false))
  }, [])

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Mes dossiers</h1>
      <p className="text-sm text-slate-600 mt-1">{dossiers.length} dossiers suivis</p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      {chargement ? (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
          <Loader2 size={16} className="animate-spin" />
          Chargement...
        </div>
      ) : (
        <div className="mt-6 bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
          {dossiers.map((d) => (
            <button
              key={d.id_dossier}
              onClick={() => navigate(`/avocat/dossiers/${d.id_dossier}`)}
              className="w-full flex items-center justify-between px-4 py-3.5 text-left hover:bg-navy-50"
            >
              <div>
                <p className="text-sm font-medium text-navy-900">{d.numero}</p>
                <p className="text-xs text-slate-400">{d.parties}</p>
              </div>
              <DossierStatusBadge statut={d.statut} />
            </button>
          ))}
          {dossiers.length === 0 && (
            <p className="px-4 py-8 text-center text-sm text-slate-400">Aucun dossier.</p>
          )}
        </div>
      )}
    </div>
  )
}
