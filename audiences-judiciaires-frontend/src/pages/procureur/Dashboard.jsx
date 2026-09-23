import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { FileText, CalendarClock, Bell, Loader2 } from 'lucide-react'
import { listerDossiers } from '../../services/api/dossiers'
import { listerAudiences } from '../../services/api/audiences'
import { listerNotifications } from '../../services/api/notifications'
import { StatutDossier, StatutAudience } from '../../constants/enums'
import DossierStatusBadge from '../../components/ui/DossierStatusBadge'

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

export default function ProcureurDashboard() {
  const navigate = useNavigate()
  const [dossiers, setDossiers] = useState([])
  const [audiences, setAudiences] = useState([])
  const [notifications, setNotifications] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    Promise.all([listerDossiers(), listerAudiences(), listerNotifications().catch(() => [])])
      .then(([d, a, n]) => {
        setDossiers(d)
        setAudiences(a)
        setNotifications(n)
      })
      .catch(() => setErreur('Impossible de charger le tableau de bord.'))
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

  const dossiersEnCours = dossiers.filter((d) => d.statut === StatutDossier.EN_COURS)
  const prochainesAudiences = audiences.filter((a) => a.statut === StatutAudience.PROGRAMMEE).length

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="font-display text-2xl text-navy-900">Tableau de bord — Procureur</h1>
          <p className="text-sm text-slate-600 mt-1">Dossiers à examiner et avis à formuler.</p>
        </div>
        <Link
          to="/procureur/dossiers"
          className="flex items-center gap-1.5 bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 transition-colors shrink-0"
        >
          <FileText size={16} />
          Consulter les dossiers
        </Link>
      </div>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>}

      <div className="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
        <StatCard icon={FileText} label="Dossiers en cours" value={dossiersEnCours.length} />
        <StatCard icon={CalendarClock} label="Audiences programmées" value={prochainesAudiences} />
        <StatCard icon={Bell} label="Notifications" value={notifications.length} />
      </div>

      <div className="flex items-center justify-between mb-2">
        <h2 className="text-sm font-medium text-navy-900">Dossiers récents</h2>
        <Link to="/procureur/dossiers" className="text-xs text-navy-900 hover:underline">
          Voir tous les dossiers →
        </Link>
      </div>
      <div className="bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
        {dossiersEnCours.slice(0, 6).map((d) => (
          <button
            key={d.id_dossier}
            onClick={() => navigate('/procureur/dossiers')}
            className="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-navy-50"
          >
            <div>
              <p className="text-sm font-medium text-navy-900">{d.parties}</p>
              <p className="text-xs text-slate-400">{d.numero} — {d.tribunal?.nom ?? '—'}</p>
            </div>
            <DossierStatusBadge statut={d.statut} />
          </button>
        ))}
        {dossiersEnCours.length === 0 && (
          <p className="px-4 py-6 text-center text-sm text-slate-400">Aucun dossier en cours.</p>
        )}
      </div>
    </div>
  )
}
