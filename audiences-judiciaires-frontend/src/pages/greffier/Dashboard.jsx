import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { FolderOpen, CalendarClock, Send, RotateCcw, Plus, Loader2, Clock } from 'lucide-react'
import { listerDossiers } from '../../services/api/dossiers'
import { listerAudiences } from '../../services/api/audiences'
import { listerDemandesDistance } from '../../services/api/demandesDistance'
import { listerConvocations } from '../../services/api/convocations'
import { StatutDossier, StatutAudience, StatutDemandeDistance, StatutConvocation } from '../../constants/enums'
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

export default function GreffierDashboard() {
  const navigate = useNavigate()
  const [dossiers, setDossiers] = useState([])
  const [audiences, setAudiences] = useState([])
  const [demandes, setDemandes] = useState([])
  const [convocations, setConvocations] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    Promise.all([listerDossiers(), listerAudiences(), listerDemandesDistance(), listerConvocations()])
      .then(([d, a, dd, c]) => {
        setDossiers(d)
        setAudiences(a)
        setDemandes(dd)
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

  const dossiersEnCours = dossiers.filter((d) => d.statut === StatutDossier.EN_COURS).length
  const prochaines = [...audiences]
    .filter((a) => a.statut === StatutAudience.PROGRAMMEE)
    .sort((a, b) => a.date_heure.localeCompare(b.date_heure))
    .slice(0, 5)
  const demandesEnAttente = demandes.filter((d) => d.statut === StatutDemandeDistance.EN_ATTENTE).length
  const reportsEnAttente = convocations.filter((c) => c.statut === StatutConvocation.REPORT_DEMANDE).length

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="font-display text-2xl text-navy-900">Tableau de bord — Greffier</h1>
          <p className="text-sm text-slate-600 mt-1">Dossiers, convocations, procès-verbaux à rédiger et à transmettre.</p>
        </div>
        <Link
          to="/greffier/dossiers/nouveau"
          className="flex items-center gap-1.5 bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 transition-colors shrink-0"
        >
          <Plus size={16} />
          Nouveau dossier
        </Link>
      </div>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>}

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <StatCard icon={FolderOpen} label="Dossiers en cours" value={dossiersEnCours} />
        <StatCard icon={CalendarClock} label="Audiences programmées" value={prochaines.length} />
        <StatCard icon={Send} label="Demandes à distance" value={demandesEnAttente} />
        <StatCard icon={RotateCcw} label="Reports demandés" value={reportsEnAttente} />
      </div>

      <div className="grid md:grid-cols-2 gap-6">
        <div>
          <div className="flex items-center justify-between mb-2">
            <h2 className="text-sm font-medium text-navy-900">Prochaines audiences</h2>
            <Link to="/greffier/dossiers" className="text-xs text-navy-900 hover:underline">
              Voir tous les dossiers →
            </Link>
          </div>
          <div className="bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
            {prochaines.map((a) => (
              <button
                key={a.id_audience}
                onClick={() => navigate(`/greffier/dossiers/${a.dossier?.id_dossier}`)}
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

        <div>
          <h2 className="text-sm font-medium text-navy-900 mb-2">Actions rapides</h2>
          <div className="bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
            <Link to="/greffier/audiences/nouvelle" className="block px-4 py-3 text-sm text-navy-900 hover:bg-navy-50">
              Programmer une nouvelle audience
            </Link>
            <Link to="/greffier/convocations" className="block px-4 py-3 text-sm text-navy-900 hover:bg-navy-50">
              Gérer les convocations
            </Link>
            <Link to="/greffier/demandes-distance" className="block px-4 py-3 text-sm text-navy-900 hover:bg-navy-50">
              Traiter les demandes de comparution à distance
              {demandesEnAttente > 0 && (
                <span className="ml-2 inline-flex items-center rounded-full bg-gold-100 text-gold-600 text-xs font-medium px-2 py-0.5">
                  {demandesEnAttente}
                </span>
              )}
            </Link>
            <Link to="/greffier/messagerie" className="block px-4 py-3 text-sm text-navy-900 hover:bg-navy-50">
              Messagerie
            </Link>
          </div>
        </div>
      </div>
    </div>
  )
}
