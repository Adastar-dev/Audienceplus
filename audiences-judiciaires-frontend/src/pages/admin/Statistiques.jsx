import { useEffect, useState } from 'react'
import { Loader2 } from 'lucide-react'
import apiClient from '../../services/api/client'
import { TYPE_AUDIENCE_LABELS, STATUT_AUDIENCE_LABELS } from '../../constants/enums'

function Chiffre({ label, valeur }) {
  return (
    <div className="bg-white border border-slate-200 rounded-md p-4">
      <p className="text-xs font-medium uppercase tracking-wide text-slate-400 mb-1">{label}</p>
      <p className="text-2xl font-display text-navy-900">{valeur ?? '—'}</p>
    </div>
  )
}

// Statistiques d'activité : dossiers par type de procédure, issue des
// audiences et délai moyen entre l'enregistrement du dossier et le jugement.
export default function Statistiques() {
  const [stats, setStats] = useState(null)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    apiClient
      .get('/statistiques')
      .then(({ data }) => setStats(data))
      .catch(() => setErreur('Impossible de charger les statistiques.'))
  }, [])

  if (erreur) {
    return <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2">{erreur}</p>
  }

  if (!stats) {
    return (
      <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
        <Loader2 size={16} className="animate-spin" />
        Chargement...
      </div>
    )
  }

  const { totaux, par_type: parType, audiences_par_statut: parStatut, taux_renvoi: tauxRenvoi } = stats
  const maxTotal = Math.max(1, ...parType.map((t) => t.total))

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Statistiques</h1>
      <p className="text-sm text-slate-600 mt-1">Activité de la juridiction par type de procédure.</p>

      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mt-6 mb-8">
        <Chiffre label="Dossiers" valeur={totaux.dossiers} />
        <Chiffre label="Audiences" valeur={totaux.audiences} />
        <Chiffre label="Jugements" valeur={totaux.jugements} />
        <Chiffre label="Taux de renvoi" valeur={tauxRenvoi === null ? null : `${tauxRenvoi} %`} />
        <Chiffre label="Comparutions à distance" valeur={totaux.comparutions_distance} />
        <Chiffre label="PV contestés" valeur={totaux.pv_contestes} />
      </div>

      <h2 className="text-sm font-medium text-navy-900 mb-2">Dossiers par type de procédure</h2>
      <div className="bg-white border border-slate-200 rounded-md overflow-x-auto mb-8">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left text-xs text-slate-400 border-b border-slate-100">
              <th className="px-4 py-2.5 font-medium">Type</th>
              <th className="px-4 py-2.5 font-medium">Dossiers</th>
              <th className="px-4 py-2.5 font-medium">En cours</th>
              <th className="px-4 py-2.5 font-medium">Jugés</th>
              <th className="px-4 py-2.5 font-medium">Archivés</th>
              <th className="px-4 py-2.5 font-medium">Délai moyen de jugement</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {parType.map((t) => (
              <tr key={t.type}>
                <td className="px-4 py-2.5 text-navy-900">
                  {TYPE_AUDIENCE_LABELS[t.type] ?? t.type}
                  <div className="h-1.5 bg-slate-100 rounded mt-1.5 max-w-48">
                    <div className="h-1.5 bg-navy-700 rounded" style={{ width: `${(100 * t.total) / maxTotal}%` }} />
                  </div>
                </td>
                <td className="px-4 py-2.5 text-slate-600">{t.total}</td>
                <td className="px-4 py-2.5 text-slate-600">{t.en_cours}</td>
                <td className="px-4 py-2.5 text-slate-600">{t.juges}</td>
                <td className="px-4 py-2.5 text-slate-600">{t.archives}</td>
                <td className="px-4 py-2.5 text-slate-600">
                  {t.delai_moyen_jours === null ? '—' : `${t.delai_moyen_jours} jours`}
                </td>
              </tr>
            ))}
            {parType.length === 0 && (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-slate-400">
                  Aucun dossier enregistré.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <h2 className="text-sm font-medium text-navy-900 mb-2">Audiences par statut</h2>
      <div className="bg-white border border-slate-200 rounded-md divide-y divide-slate-100 max-w-md">
        {Object.entries(parStatut).map(([statut, nombre]) => (
          <div key={statut} className="flex items-center justify-between px-4 py-2.5 text-sm">
            <span className="text-navy-900">{STATUT_AUDIENCE_LABELS[statut] ?? statut}</span>
            <span className="text-slate-600">{nombre}</span>
          </div>
        ))}
        {Object.keys(parStatut).length === 0 && (
          <p className="px-4 py-6 text-center text-sm text-slate-400">Aucune audience.</p>
        )}
      </div>
    </div>
  )
}
