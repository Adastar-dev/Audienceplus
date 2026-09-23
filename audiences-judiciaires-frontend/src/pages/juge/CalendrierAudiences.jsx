import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Clock, Loader2 } from 'lucide-react'
import { listerAudiences } from '../../services/api/audiences'
import AudienceStatusBadge from '../../components/ui/AudienceStatusBadge'

function groupByDay(list) {
  const groups = {}
  for (const a of list) {
    const day = a.date_heure.slice(0, 10)
    if (!groups[day]) groups[day] = []
    groups[day].push(a)
  }
  return Object.entries(groups).sort(([a], [b]) => a.localeCompare(b))
}

function formatDay(iso) {
  return new Date(iso).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
}

function formatTime(iso) {
  return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

export default function CalendrierAudiences() {
  const navigate = useNavigate()
  const [audiences, setAudiences] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    listerAudiences()
      .then(setAudiences)
      .catch(() => setErreur('Impossible de charger les audiences.'))
      .finally(() => setChargement(false))
  }, [])

  const sorted = [...audiences].sort((a, b) => a.date_heure.localeCompare(b.date_heure))
  const grouped = groupByDay(sorted)

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Calendrier des audiences</h1>
      <p className="text-sm text-slate-600 mt-1">{audiences.length} audiences au total</p>

      {chargement && (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
          <Loader2 size={16} className="animate-spin" />
          Chargement...
        </div>
      )}

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      {!chargement && !erreur && (
        <div className="mt-6 space-y-6">
          {grouped.map(([day, items]) => (
            <div key={day}>
              <p className="text-sm font-medium text-navy-900 capitalize mb-2">{formatDay(day)}</p>
              <div className="bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
                {items.map((a) => (
                  <button
                    key={a.id_audience}
                    onClick={() => navigate(`/juge/audiences/${a.id_audience}`)}
                    className="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-navy-50"
                  >
                    <div className="flex items-center gap-4">
                      <div className="flex items-center gap-1.5 text-sm text-slate-600 w-16">
                        <Clock size={14} />
                        {formatTime(a.date_heure)}
                      </div>
                      <div>
                        <p className="text-sm font-medium text-navy-900">{a.dossier?.parties}</p>
                        <p className="text-xs text-slate-400">{a.dossier?.numero}</p>
                      </div>
                    </div>
                    <AudienceStatusBadge statut={a.statut} />
                  </button>
                ))}
              </div>
            </div>
          ))}

          {grouped.length === 0 && (
            <p className="text-sm text-slate-400 text-center py-8">Aucune audience programmée.</p>
          )}
        </div>
      )}
    </div>
  )
}
