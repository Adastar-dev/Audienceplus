import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Users, ShieldAlert, Building2, ScrollText, Loader2 } from 'lucide-react'
import { listerUtilisateurs } from '../../services/api/utilisateurs'
import { listerTribunaux } from '../../services/api/tribunaux'
import { listerLogs } from '../../services/api/logs'
import { Role, ROLE_LABELS } from '../../constants/enums'

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

export default function AdminDashboard() {
  const [utilisateurs, setUtilisateurs] = useState([])
  const [tribunaux, setTribunaux] = useState([])
  const [logs, setLogs] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    Promise.all([listerUtilisateurs(), listerTribunaux(), listerLogs()])
      .then(([u, t, l]) => {
        setUtilisateurs(u)
        setTribunaux(t)
        setLogs(l)
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

  const aVerifier = utilisateurs.filter(
    (u) => !u.identite_verifiee && [Role.AVOCAT, Role.JUSTICIABLE].includes(u.role),
  ).length
  const derniersLogs = [...logs].sort((a, b) => new Date(b.date) - new Date(a.date)).slice(0, 6)

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="font-display text-2xl text-navy-900">Tableau de bord — Administrateur</h1>
          <p className="text-sm text-slate-600 mt-1">Utilisateurs, tribunaux et supervision de la plateforme.</p>
        </div>
        <Link
          to="/admin/utilisateurs"
          className="flex items-center gap-1.5 bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 transition-colors shrink-0"
        >
          <Users size={16} />
          Gérer les utilisateurs
        </Link>
      </div>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>}

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <StatCard icon={Users} label="Utilisateurs" value={utilisateurs.length} />
        <StatCard icon={ShieldAlert} label="Comptes à vérifier" value={aVerifier} />
        <StatCard icon={Building2} label="Tribunaux" value={tribunaux.length} />
        <StatCard icon={ScrollText} label="Actions enregistrées" value={logs.length} />
      </div>

      <div className="grid md:grid-cols-2 gap-6">
        <div>
          <div className="flex items-center justify-between mb-2">
            <h2 className="text-sm font-medium text-navy-900">Répartition des comptes par rôle</h2>
          </div>
          <div className="bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
            {Object.values(Role).map((r) => {
              const count = utilisateurs.filter((u) => u.role === r).length
              return (
                <div key={r} className="flex items-center justify-between px-4 py-3">
                  <span className="text-sm text-navy-900">{ROLE_LABELS[r]}</span>
                  <span className="text-sm text-slate-600">{count}</span>
                </div>
              )
            })}
          </div>
        </div>

        <div>
          <div className="flex items-center justify-between mb-2">
            <h2 className="text-sm font-medium text-navy-900">Dernières actions</h2>
            <Link to="/admin/securite" className="text-xs text-navy-900 hover:underline">
              Paramètres de sécurité →
            </Link>
          </div>
          <div className="bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
            {derniersLogs.map((l) => (
              <div key={l.id_log} className="flex items-center gap-3 px-4 py-3">
                <ScrollText size={15} className="text-slate-400 shrink-0" />
                <div className="flex-1">
                  <p className="text-sm text-navy-900">{l.action}</p>
                  <p className="text-xs text-slate-400">
                    {l.utilisateur?.nom ?? 'Système'} — {new Date(l.date).toLocaleString('fr-FR')}
                  </p>
                </div>
              </div>
            ))}
            {derniersLogs.length === 0 && (
              <p className="px-4 py-6 text-center text-sm text-slate-400">Aucune action enregistrée.</p>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
