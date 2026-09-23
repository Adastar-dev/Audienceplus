import { useEffect, useState } from 'react'
import { listerLogs } from '../../services/api/logs'
import { ScrollText, Loader2 } from 'lucide-react'

export default function Logs() {
  const [logs, setLogs] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    listerLogs()
      .then(setLogs)
      .catch(() => setErreur('Impossible de charger les logs.'))
      .finally(() => setChargement(false))
  }, [])

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Logs & traçabilité</h1>
      <p className="text-sm text-slate-600 mt-1">{logs.length} actions enregistrées</p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      {chargement ? (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
          <Loader2 size={16} className="animate-spin" />
          Chargement...
        </div>
      ) : (
        <div className="mt-6 bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
          {logs.map((l) => (
            <div key={l.id_log} className="flex items-center gap-3 px-4 py-3">
              <ScrollText size={15} className="text-slate-400 shrink-0" />
              <div className="flex-1">
                <p className="text-sm text-navy-900">{l.action}</p>
                <p className="text-xs text-slate-400">
                  {l.utilisateur?.nom ?? 'Système'} — {new Date(l.date).toLocaleString('fr-FR')} — {l.adresse_ip}
                </p>
              </div>
            </div>
          ))}

          {logs.length === 0 && (
            <p className="px-4 py-8 text-center text-sm text-slate-400">Aucun log enregistré.</p>
          )}
        </div>
      )}
    </div>
  )
}
