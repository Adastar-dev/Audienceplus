import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { FolderOpen, CalendarClock, Mail, Loader2, Clock } from 'lucide-react'
import { listerDossiers } from '../../services/api/dossiers'
import { listerConvocations } from '../../services/api/convocations'
import { StatutDossier, StatutConvocation } from '../../constants/enums'
import DossierStatusBadge from '../../components/ui/DossierStatusBadge'
import ConvocationStatusBadge from '../../components/ui/ConvocationStatusBadge'

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

export default function AvocatDashboard() {
  const navigate = useNavigate()
  const [dossiers, setDossiers] = useState([])
  const [convocations, setConvocations] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    Promise.all([listerDossiers(), listerConvocations()])
      .then(([d, c]) => {
        setDossiers(d)
        setConvocations(c)
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

  const dossiersActifs = dossiers.filter((d) => d.statut === StatutDossier.EN_COURS)
  const convocationsActives = convocations.filter(
    (c) => c.statut !== StatutConvocation.CONFIRMEE && c.statut !== StatutConvocation.REPORT_REFUSEE,
  )
  const prochaines = [...convocations]
    .filter((c) => c.audience?.date_heure)
    .sort((a, b) => a.audience.date_heure.localeCompare(b.audience.date_heure))
    .slice(0, 5)

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="font-display text-2xl text-navy-900">Tableau de bord — Avocat</h1>
          <p className="text-sm text-slate-600 mt-1">Vos dossiers et convocations à distance.</p>
        </div>
        <Link
          to="/avocat/dossiers"
          className="flex items-center gap-1.5 bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 transition-colors shrink-0"
        >
          <FolderOpen size={16} />
          Mes dossiers
        </Link>
      </div>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>}

      <div className="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
        <StatCard icon={FolderOpen} label="Dossiers actifs" value={dossiersActifs.length} />
        <StatCard icon={CalendarClock} label="Convocations à traiter" value={convocationsActives.length} />
        <StatCard icon={Mail} label="Dossiers au total" value={dossiers.length} />
      </div>

      <div className="grid md:grid-cols-2 gap-6">
        <div>
          <div className="flex items-center justify-between mb-2">
            <h2 className="text-sm font-medium text-navy-900">Prochaines convocations</h2>
            <Link to="/avocat/convocations" className="text-xs text-navy-900 hover:underline">
              Voir tout →
            </Link>
          </div>
          <div className="bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
            {prochaines.map((c) => (
              <div key={c.id_convocation} className="flex items-center justify-between px-4 py-3">
                <div className="flex items-center gap-3">
                  <Clock size={14} className="text-slate-400" />
                  <div>
                    <p className="text-sm font-medium text-navy-900">{c.audience?.dossier?.parties}</p>
                    <p className="text-xs text-slate-400">
                      {new Date(c.audience?.date_heure).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })}
                    </p>
                  </div>
                </div>
                <ConvocationStatusBadge statut={c.statut} />
              </div>
            ))}
            {prochaines.length === 0 && (
              <p className="px-4 py-6 text-center text-sm text-slate-400">Aucune convocation à venir.</p>
            )}
          </div>
        </div>

        <div>
          <h2 className="text-sm font-medium text-navy-900 mb-2">Dossiers récents</h2>
          <div className="bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
            {dossiers.slice(0, 5).map((d) => (
              <button
                key={d.id_dossier}
                onClick={() => navigate(`/avocat/dossiers/${d.id_dossier}`)}
                className="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-navy-50"
              >
                <div>
                  <p className="text-sm font-medium text-navy-900">{d.parties}</p>
                  <p className="text-xs text-slate-400">{d.numero}</p>
                </div>
                <DossierStatusBadge statut={d.statut} />
              </button>
            ))}
            {dossiers.length === 0 && (
              <p className="px-4 py-6 text-center text-sm text-slate-400">Aucun dossier pour le moment.</p>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
