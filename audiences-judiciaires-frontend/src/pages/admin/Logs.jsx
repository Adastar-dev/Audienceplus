import { useEffect, useState } from 'react'
import { listerLogs } from '../../services/api/logs'
import { ScrollText, Loader2, Search } from 'lucide-react'

export default function Logs() {
  const [logs, setLogs] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')
  const [recherche, setRecherche] = useState('')

  useEffect(() => {
    listerLogs()
      .then(setLogs)
      .catch(() => setErreur('Impossible de charger les logs.'))
      .finally(() => setChargement(false))
  }, [])

  const terme = recherche.trim().toLowerCase()
  const affiches = terme
    ? logs.filter((l) =>
        [l.action, l.utilisateur?.nom, l.utilisateur?.role, l.adresse_ip].some((v) => v?.toLowerCase().includes(terme)),
      )
    : logs

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Logs & traçabilité</h1>
      <p className="text-sm text-slate-600 mt-1">
        {terme ? `${affiches.length} sur ${logs.length}` : logs.length} actions enregistrées (500 plus récentes)
      </p>

      <div className="relative mt-4 max-w-sm">
        <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
        <input
          type="search"
          value={recherche}
          onChange={(e) => setRecherche(e.target.value)}
          placeholder="Rechercher (action, utilisateur, dossier, IP...)"
          className="w-full border border-slate-200 rounded pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
        />
      </div>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      {chargement ? (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
          <Loader2 size={16} className="animate-spin" />
          Chargement...
        </div>
      ) : (
        <div className="mt-6 bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
          {affiches.map((l) => (
            <div key={l.id_log} className="flex items-center gap-3 px-4 py-3">
              <ScrollText size={15} className="text-slate-400 shrink-0" />
              <div className="flex-1">
                <p className="text-sm text-navy-900">{l.action}</p>
                <p className="text-xs text-slate-400">
                  {l.utilisateur ? `${l.utilisateur.nom} (${l.utilisateur.role.toLowerCase()})` : 'Système'} —{' '}
                  {new Date(l.date).toLocaleString('fr-FR')}
                  {l.adresse_ip && ` — ${l.adresse_ip}`}
                </p>
              </div>
            </div>
          ))}

          {affiches.length === 0 && (
            <p className="px-4 py-8 text-center text-sm text-slate-400">Aucune action trouvée.</p>
          )}
        </div>
      )}
    </div>
  )
}
