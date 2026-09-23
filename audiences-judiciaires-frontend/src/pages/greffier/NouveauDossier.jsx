import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ArrowLeft, Loader2 } from 'lucide-react'
import { creerDossier } from '../../services/api/dossiers'
import { listerTribunaux } from '../../services/api/tribunaux'
import { listerJusticiables, listerAvocats, listerProcureurs } from '../../services/api/utilisateurs'
import { TypeAudience, TYPE_AUDIENCE_LABELS } from '../../constants/enums'

export default function NouveauDossier() {
  const navigate = useNavigate()
  const [tribunaux, setTribunaux] = useState([])
  const [justiciables, setJusticiables] = useState([])
  const [avocats, setAvocats] = useState([])
  const [procureurs, setProcureurs] = useState([])
  const [chargementTribunaux, setChargementTribunaux] = useState(true)
  const [enCours, setEnCours] = useState(false)
  const [erreur, setErreur] = useState('')
  const [form, setForm] = useState({
    type: TypeAudience.DIVORCE,
    id_tribunal: '',
    demandeur: '',
    defendeur: '',
    id_demandeur_utilisateur: '',
    id_demandeur_avocat: '',
    id_defendeur_utilisateur: '',
    id_defendeur_avocat: '',
    id_procureur: '',
  })

  useEffect(() => {
    Promise.all([listerTribunaux(), listerJusticiables(), listerAvocats(), listerProcureurs()])
      .then(([t, j, a, p]) => {
        setTribunaux(t)
        setJusticiables(j)
        setAvocats(a)
        setProcureurs(p)
        if (t.length > 0) {
          setForm((f) => ({ ...f, id_tribunal: t[0].id_tribunal }))
        }
      })
      .catch(() => setErreur('Impossible de charger les listes nécessaires à la création du dossier.'))
      .finally(() => setChargementTribunaux(false))
  }, [])

  function update(field, value) {
    setForm((f) => ({ ...f, [field]: value }))
  }

  // Quand un justiciable est choisi dans la liste, on préremplit le champ texte
  // demandeur/défendeur avec son nom, sans empêcher de le corriger à la main.
  function updateJusticiable(field, nomField, id) {
    const utilisateur = justiciables.find((j) => String(j.id_utilisateur) === String(id))
    setForm((f) => ({
      ...f,
      [field]: id,
      [nomField]: utilisateur ? utilisateur.nom : f[nomField],
    }))
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setErreur('')
    setEnCours(true)
    try {
      const payload = {
        ...form,
        id_demandeur_utilisateur: form.id_demandeur_utilisateur || null,
        id_demandeur_avocat: form.id_demandeur_avocat || null,
        id_defendeur_utilisateur: form.id_defendeur_utilisateur || null,
        id_defendeur_avocat: form.id_defendeur_avocat || null,
        id_procureur: form.id_procureur || null,
      }
      const nouveauDossier = await creerDossier(payload)
      navigate(`/greffier/dossiers/${nouveauDossier.id_dossier}`)
    } catch (err) {
      setErreur(
        err.response?.data?.errors
          ? Object.values(err.response.data.errors).flat().join(' ')
          : "Impossible de créer le dossier.",
      )
    } finally {
      setEnCours(false)
    }
  }

  return (
    <div className="max-w-lg">
      <button
        onClick={() => navigate('/greffier/dossiers')}
        className="flex items-center gap-1 text-sm text-slate-600 hover:text-navy-900 mb-4"
      >
        <ArrowLeft size={14} />
        Retour aux dossiers
      </button>

      <h1 className="font-display text-2xl text-navy-900">Nouveau dossier</h1>
      <p className="text-sm text-slate-600 mt-1">
        Un numéro de dossier sera généré automatiquement. Rattachez les comptes des
        parties pour qu'elles puissent suivre le dossier depuis leur espace.
      </p>

      {chargementTribunaux ? (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
          <Loader2 size={16} className="animate-spin" />
          Chargement...
        </div>
      ) : (
        <form
          onSubmit={handleSubmit}
          className="mt-6 bg-white border border-slate-200 rounded-md p-6 space-y-4"
        >
          {erreur && (
            <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2">{erreur}</p>
          )}

          <div>
            <label className="block text-sm font-medium text-slate-600 mb-1.5">
              Type de dossier
            </label>
            <select
              value={form.type}
              onChange={(e) => update('type', e.target.value)}
              className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
            >
              {Object.values(TypeAudience).map((t) => (
                <option key={t} value={t}>
                  {TYPE_AUDIENCE_LABELS[t]}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-600 mb-1.5">Tribunal</label>
            <select
              value={form.id_tribunal}
              onChange={(e) => update('id_tribunal', e.target.value)}
              className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
            >
              {tribunaux.map((t) => (
                <option key={t.id_tribunal} value={t.id_tribunal}>
                  {t.nom}
                </option>
              ))}
            </select>
          </div>

          <div className="border-t border-slate-100 pt-4">
            <p className="text-xs font-medium uppercase tracking-wide text-slate-400 mb-2">
              Demandeur
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-slate-600 mb-1.5">
                  Nom du demandeur
                </label>
                <input
                  value={form.demandeur}
                  onChange={(e) => update('demandeur', e.target.value)}
                  required
                  className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-600 mb-1.5">
                  Compte justiciable
                </label>
                <select
                  value={form.id_demandeur_utilisateur}
                  onChange={(e) => updateJusticiable('id_demandeur_utilisateur', 'demandeur', e.target.value)}
                  className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
                >
                  <option value="">— Aucun compte lié —</option>
                  {justiciables.map((j) => (
                    <option key={j.id_utilisateur} value={j.id_utilisateur}>
                      {j.nom} ({j.email})
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div className="mt-4">
              <label className="block text-sm font-medium text-slate-600 mb-1.5">
                Avocat du demandeur (optionnel)
              </label>
              <select
                value={form.id_demandeur_avocat}
                onChange={(e) => update('id_demandeur_avocat', e.target.value)}
                className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
              >
                <option value="">— Aucun avocat —</option>
                {avocats.map((a) => (
                  <option key={a.id_utilisateur} value={a.id_utilisateur}>
                    {a.nom} ({a.email})
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="border-t border-slate-100 pt-4">
            <p className="text-xs font-medium uppercase tracking-wide text-slate-400 mb-2">
              Défendeur
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-slate-600 mb-1.5">
                  Nom du défendeur
                </label>
                <input
                  value={form.defendeur}
                  onChange={(e) => update('defendeur', e.target.value)}
                  required
                  className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-600 mb-1.5">
                  Compte justiciable
                </label>
                <select
                  value={form.id_defendeur_utilisateur}
                  onChange={(e) => updateJusticiable('id_defendeur_utilisateur', 'defendeur', e.target.value)}
                  className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
                >
                  <option value="">— Aucun compte lié —</option>
                  {justiciables.map((j) => (
                    <option key={j.id_utilisateur} value={j.id_utilisateur}>
                      {j.nom} ({j.email})
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div className="mt-4">
              <label className="block text-sm font-medium text-slate-600 mb-1.5">
                Avocat du défendeur (optionnel)
              </label>
              <select
                value={form.id_defendeur_avocat}
                onChange={(e) => update('id_defendeur_avocat', e.target.value)}
                className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
              >
                <option value="">— Aucun avocat —</option>
                {avocats.map((a) => (
                  <option key={a.id_utilisateur} value={a.id_utilisateur}>
                    {a.nom} ({a.email})
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="border-t border-slate-100 pt-4">
            <label className="block text-sm font-medium text-slate-600 mb-1.5">
              Procureur assigné (optionnel)
            </label>
            <p className="text-xs text-slate-400 mb-2">
              Procureur par défaut pour l'avis sur ce dossier. N'importe quel procureur pourra
              tout de même consulter le dossier et donner un avis si besoin.
            </p>
            <select
              value={form.id_procureur}
              onChange={(e) => update('id_procureur', e.target.value)}
              className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
            >
              <option value="">— Aucun procureur assigné —</option>
              {procureurs.map((p) => (
                <option key={p.id_utilisateur} value={p.id_utilisateur}>
                  {p.nom}
                </option>
              ))}
            </select>
          </div>

          <button
            type="submit"
            disabled={enCours}
            className="bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 disabled:opacity-60 transition-colors"
          >
            {enCours ? 'Création...' : 'Créer le dossier'}
          </button>
        </form>
      )}
    </div>
  )
}
