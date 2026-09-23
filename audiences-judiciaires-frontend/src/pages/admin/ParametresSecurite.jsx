import { useState } from 'react'
import { ShieldCheck } from 'lucide-react'

export default function ParametresSecurite() {
  const [params, setParams] = useState({
    blocageApresEchecs: true,
    nbTentativesMax: 5,
    chiffrementDonnees: true,
    dureeSessionMinutes: 30,
  })

  function toggle(field) {
    setParams((p) => ({ ...p, [field]: !p[field] }))
  }

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Paramètres de sécurité</h1>
      <p className="text-sm text-slate-600 mt-1">Politique d'accès et de protection des données.</p>

      <div className="mt-6 bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
        <div className="flex items-center justify-between px-5 py-4">
          <div>
            <p className="text-sm font-medium text-navy-900">Blocage après tentatives échouées</p>
            <p className="text-xs text-slate-400">Verrouille le compte après plusieurs échecs de connexion</p>
          </div>
          <input
            type="checkbox"
            checked={params.blocageApresEchecs}
            onChange={() => toggle('blocageApresEchecs')}
            className="w-4 h-4 accent-navy-900"
          />
        </div>

        {params.blocageApresEchecs && (
          <div className="flex items-center justify-between px-5 py-4">
            <p className="text-sm text-slate-600">Nombre de tentatives autorisées</p>
            <input
              type="number"
              min="1"
              value={params.nbTentativesMax}
              onChange={(e) => setParams((p) => ({ ...p, nbTentativesMax: Number(e.target.value) }))}
              className="w-16 border border-slate-200 rounded px-2 py-1 text-sm text-center"
            />
          </div>
        )}

        <div className="flex items-center justify-between px-5 py-4">
          <div>
            <p className="text-sm font-medium text-navy-900">Chiffrement des données sensibles</p>
            <p className="text-xs text-slate-400">HTTPS obligatoire pour identité, pièces et PV</p>
          </div>
          <span className="flex items-center gap-1 text-xs text-success-700 font-medium">
            <ShieldCheck size={13} /> Activé
          </span>
        </div>

        <div className="flex items-center justify-between px-5 py-4">
          <p className="text-sm text-slate-600">Durée maximale de session (minutes)</p>
          <input
            type="number"
            min="5"
            value={params.dureeSessionMinutes}
            onChange={(e) => setParams((p) => ({ ...p, dureeSessionMinutes: Number(e.target.value) }))}
            className="w-16 border border-slate-200 rounded px-2 py-1 text-sm text-center"
          />
        </div>
      </div>
    </div>
  )
}
