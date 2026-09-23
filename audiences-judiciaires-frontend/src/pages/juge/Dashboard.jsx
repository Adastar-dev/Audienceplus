import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { CalendarClock, CheckCircle2, Clock, Loader2 } from 'lucide-react'
import { listerAudiences } from '../../services/api/audiences'
import { StatutAudience } from '../../constants/enums'
import AudienceStatusBadge from '../../components/ui/AudienceStatusBadge'

function StatCard({ icon: Icon, label, value }) {
  return (
    <div className="bg-white border border-slate-200 rounded-md p-4">
      <div className="flex items-center gap-2 text-slate-400 mb-2">
        <Icon size={15} />
        <span className="text-xs font-medium uppercase tracking-wide">{label}</span>
      </div>
      <p className="text-2xl font-display text-navy-900">{value}</p>
    </div>
  )
}

function isToday(iso) {
  const d = new Date(iso)
  const now = new Date()
  return d.toDateString() === now.toDateString()
}

export default function JugeDashboard() {
  const navigate = useNavigate()
  const [audiences, setAudiences] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    listerAudiences()
      .then(setAudiences)
      .catch(() => setErreur('Impossible de charger vos audiences.'))
      .finally(() => setChargement(false))
  }, [])

  if (chargement) {
    return (
      <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
        <Loader2 size={16} className="animate-spin" />
        Chargement...
      </div>
    )
  }

  const programmees = audiences.filter((a) => a.statut === StatutAudience.PROGRAMMEE)
  const aujourdhui = programmees.filter((a) => isToday(a.date_heure))
  const cloturees = audiences.filter((a) => a.statut === StatutAudience.CLOTUREE).length

  const prochaines = [...programmees].sort((a, b) => a.date_heure.localeCompare(b.date_heure)).slice(0, 6)

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="font-display text-2xl text-navy-900">Tableau de bord — Juge</h1>
          <p className="text-sm text-slate-600 mt-1">Vos audiences programmées et procès-verbaux à traiter.</p>
        </div>
        <Link
          to="/juge/audiences"
          className="flex items-center gap-1.5 border border-slate-200 text-navy-900 text-sm font-medium rounded px-4 py-2 hover:bg-navy-50 transition-colors shrink-0"
        >
          <CalendarClock size={16} />
          Voir le calendrier
        </Link>
      </div>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>}

      <div className="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
        <StatCard icon={Clock} label="Audiences aujourd'hui" value={aujourdhui.length} />
        <StatCard icon={CalendarClock} label="Audiences programmées" value={programmees.length} />
        <StatCard icon={CheckCircle2} label="Audiences clôturées" value={cloturees} />
      </div>

      <div className="flex items-center justify-between mb-2">
        <h2 className="text-sm font-medium text-navy-900">Prochaines audiences</h2>
        <Link to="/juge/dossiers" className="text-xs text-navy-900 hover:underline">
          Voir les dossiers programmés →
        </Link>
      </div>
      <div className="bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
        {prochaines.map((a) => (
          <button
            key={a.id_audience}
            onClick={() => navigate(`/juge/audiences/${a.id_audience}`)}
            className="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-navy-50"
          >
            <div className="flex items-center gap-3">
              <Clock size={14} className="text-slate-400" />
              <div>
                <p className="text-sm font-medium text-navy-900">{a.dossier?.parties}</p>
                <p className="text-xs text-slate-400">
                  {a.dossier?.numero} — {new Date(a.date_heure).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })}
                </p>
              </div>
            </div>
            <AudienceStatusBadge statut={a.statut} />
          </button>
        ))}
        {prochaines.length === 0 && (
          <p className="px-4 py-6 text-center text-sm text-slate-400">Aucune audience programmée.</p>
        )}
      </div>
    </div>
  )
}
