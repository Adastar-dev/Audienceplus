import { useEffect, useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { Search, Plus, Loader2 } from 'lucide-react'
import { listerDossiers } from '../../services/api/dossiers'
import { StatutDossier, STATUT_DOSSIER_LABELS, TYPE_AUDIENCE_LABELS } from '../../constants/enums'
import DossierStatusBadge from '../../components/ui/DossierStatusBadge'

export default function DossiersList() {
  const navigate = useNavigate()
  const [dossiers, setDossiers] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')
  const [search, setSearch] = useState('')
  const [statutFilter, setStatutFilter] = useState('')

  useEffect(() => {
    listerDossiers()
      .then(setDossiers)
      .catch(() => setErreur("Impossible de charger les dossiers depuis le serveur."))
      .finally(() => setChargement(false))
  }, [])

  const filtered = dossiers.filter((d) => {
    const matchSearch =
      d.numero.toLowerCase().includes(search.toLowerCase()) ||
      d.parties.toLowerCase().includes(search.toLowerCase())
    const matchStatut = statutFilter ? d.statut === statutFilter : true
    return matchSearch && matchStatut
  })

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="font-display text-2xl text-navy-900">Dossiers</h1>
          <p className="text-sm text-slate-600 mt-1">
            {dossiers.length} dossier{dossiers.length > 1 ? 's' : ''} au total
          </p>
        </div>
        <Link
          to="/greffier/dossiers/nouveau"
          className="flex items-center gap-1.5 bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 transition-colors"
        >
          <Plus size={16} />
          Nouveau dossier
        </Link>
      </div>

      <div className="flex gap-3 mb-4">
        <div className="relative flex-1">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Rechercher par numéro ou par partie..."
            className="w-full border border-slate-200 rounded pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700 bg-white"
          />
        </div>
        <select
          value={statutFilter}
          onChange={(e) => setStatutFilter(e.target.value)}
          className="border border-slate-200 rounded px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-navy-700"
        >
          <option value="">Tous les statuts</option>
          {Object.values(StatutDossier).map((s) => (
            <option key={s} value={s}>
              {STATUT_DOSSIER_LABELS[s]}
            </option>
          ))}
        </select>
      </div>

      {chargement && (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
          <Loader2 size={16} className="animate-spin" />
          Chargement des dossiers...
        </div>
      )}

      {erreur && !chargement && (
        <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>
      )}

      {!chargement && !erreur && (
        <div className="bg-white border border-slate-200 rounded-md overflow-hidden">
          <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-200 text-left text-slate-600">
                <th className="px-4 py-3 font-medium">Numéro</th>
                <th className="px-4 py-3 font-medium">Parties</th>
                <th className="px-4 py-3 font-medium">Type</th>
                <th className="px-4 py-3 font-medium">Tribunal</th>
                <th className="px-4 py-3 font-medium">Statut</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((d) => (
                <tr
                  key={d.id_dossier}
                  onClick={() => navigate(`/greffier/dossiers/${d.id_dossier}`)}
                  className="border-b border-slate-100 last:border-0 hover:bg-navy-50 cursor-pointer"
                >
                  <td className="px-4 py-3 font-medium text-navy-900">{d.numero}</td>
                  <td className="px-4 py-3">{d.parties}</td>
                  <td className="px-4 py-3 text-slate-600">{TYPE_AUDIENCE_LABELS[d.type] ?? d.type}</td>
                  <td className="px-4 py-3 text-slate-600">{d.tribunal?.nom ?? '—'}</td>
                  <td className="px-4 py-3">
                    <DossierStatusBadge statut={d.statut} />
                  </td>
                </tr>
              ))}

              {filtered.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-8 text-center text-slate-400">
                    Aucun dossier ne correspond à cette recherche
                  </td>
                </tr>
              )}
            </tbody>
          </table>
          </div>
        </div>
      )}
    </div>
  )
}
