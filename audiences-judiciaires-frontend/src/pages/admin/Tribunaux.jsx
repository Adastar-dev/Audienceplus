import { useEffect, useState } from 'react'
import { listerTribunaux, creerTribunal } from '../../services/api/tribunaux'
import { Building2, Plus, X, Loader2 } from 'lucide-react'

export default function Tribunaux() {
  const [tribunaux, setTribunaux] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')
  const [modalOuvert, setModalOuvert] = useState(false)
  const [form, setForm] = useState({ nom: '', ville: '', salles_virtuelles: 1 })

  useEffect(() => {
    listerTribunaux()
      .then(setTribunaux)
      .catch(() => setErreur('Impossible de charger les tribunaux.'))
      .finally(() => setChargement(false))
  }, [])

  async function handleSubmit(e) {
    e.preventDefault()
    try {
      const nouveau = await creerTribunal({ ...form, salles_virtuelles: Number(form.salles_virtuelles) })
      setTribunaux((list) => [...list, nouveau])
      setForm({ nom: '', ville: '', salles_virtuelles: 1 })
      setModalOuvert(false)
    } catch {
      setErreur('Impossible de créer le tribunal.')
    }
  }

  if (chargement) {
    return (
      <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
        <Loader2 size={16} className="animate-spin" />
        Chargement...
      </div>
    )
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="font-display text-2xl text-navy-900">Tribunaux & salles virtuelles</h1>
          <p className="text-sm text-slate-600 mt-1">{tribunaux.length} tribunaux enregistrés</p>
        </div>
        <button
          onClick={() => setModalOuvert(true)}
          className="flex items-center gap-1.5 bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 transition-colors"
        >
          <Plus size={16} />
          Ajouter un tribunal
        </button>
      </div>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>}

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {tribunaux.map((t) => (
          <div key={t.id_tribunal} className="bg-white border border-slate-200 rounded-md p-5">
            <div className="flex items-center gap-2 mb-2">
              <Building2 size={18} className="text-navy-700" />
              <h2 className="font-medium text-navy-900">{t.nom}</h2>
            </div>
            <p className="text-sm text-slate-600">{t.ville}</p>
            <p className="text-xs text-slate-400 mt-1">
              {t.salles_virtuelles_count ?? t.salles_virtuelles?.length ?? 0} salles virtuelles actives
            </p>
          </div>
        ))}
      </div>

      {modalOuvert && (
        <div className="fixed inset-0 bg-navy-950/40 flex items-center justify-center z-10">
          <div className="bg-white rounded-md w-full max-w-sm p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-medium text-navy-900">Nouveau tribunal</h2>
              <button onClick={() => setModalOuvert(false)} className="text-slate-400 hover:text-navy-900">
                <X size={18} />
              </button>
            </div>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-600 mb-1.5">Nom</label>
                <input
                  value={form.nom}
                  onChange={(e) => setForm((f) => ({ ...f, nom: e.target.value }))}
                  required
                  className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-600 mb-1.5">Ville</label>
                <input
                  value={form.ville}
                  onChange={(e) => setForm((f) => ({ ...f, ville: e.target.value }))}
                  required
                  className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-600 mb-1.5">
                  Nombre de salles virtuelles
                </label>
                <input
                  type="number"
                  min="1"
                  value={form.salles_virtuelles}
                  onChange={(e) => setForm((f) => ({ ...f, salles_virtuelles: e.target.value }))}
                  className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
                />
              </div>
              <button
                type="submit"
                className="bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 transition-colors"
              >
                Créer
              </button>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
