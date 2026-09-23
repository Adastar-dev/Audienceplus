import { useEffect, useState } from 'react'
import { listerUtilisateurs, creerUtilisateur, updateUtilisateur, verifierIdentiteUtilisateur } from '../../services/api/utilisateurs'
import { Role, ROLE_LABELS } from '../../constants/enums'
import Badge from '../../components/ui/Badge'
import { ShieldCheck, ShieldAlert, Plus, Pencil, X, Loader2 } from 'lucide-react'

const VIDE = { nom: '', email: '', role: Role.JUSTICIABLE, telephone: '', cni: '', numero_barreau: '' }

export default function Utilisateurs() {
  const [utilisateurs, setUtilisateurs] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')
  const [modalOuvert, setModalOuvert] = useState(false)
  const [enEdition, setEnEdition] = useState(null)
  const [form, setForm] = useState(VIDE)
  const [motDePasseTemp, setMotDePasseTemp] = useState(null)

  useEffect(() => {
    listerUtilisateurs()
      .then(setUtilisateurs)
      .catch(() => setErreur('Impossible de charger les utilisateurs.'))
      .finally(() => setChargement(false))
  }, [])

  function ouvrirCreation() {
    setEnEdition(null)
    setForm(VIDE)
    setMotDePasseTemp(null)
    setModalOuvert(true)
  }

  function ouvrirEdition(u) {
    setEnEdition(u.id_utilisateur)
    setForm({
      nom: u.nom,
      email: u.email,
      role: u.role,
      telephone: u.telephone || '',
      cni: u.cni || '',
      numero_barreau: u.numero_barreau || '',
    })
    setMotDePasseTemp(null)
    setModalOuvert(true)
  }

  async function handleSubmit(e) {
    e.preventDefault()
    try {
      if (enEdition) {
        const updated = await updateUtilisateur(enEdition, form)
        setUtilisateurs((list) => list.map((u) => (u.id_utilisateur === enEdition ? updated : u)))
        setModalOuvert(false)
      } else {
        const { utilisateur, mot_de_passe_temporaire } = await creerUtilisateur(form)
        setUtilisateurs((list) => [...list, utilisateur])
        setMotDePasseTemp(mot_de_passe_temporaire)
      }
    } catch {
      setErreur("Impossible d'enregistrer l'utilisateur.")
    }
  }

  async function verifier(id) {
    try {
      const updated = await verifierIdentiteUtilisateur(id)
      setUtilisateurs((list) => list.map((u) => (u.id_utilisateur === id ? updated : u)))
    } catch {
      setErreur("Impossible de vérifier ce compte.")
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
          <h1 className="font-display text-2xl text-navy-900">Utilisateurs & rôles</h1>
          <p className="text-sm text-slate-600 mt-1">{utilisateurs.length} comptes</p>
        </div>
        <button
          onClick={ouvrirCreation}
          className="flex items-center gap-1.5 bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 transition-colors"
        >
          <Plus size={16} />
          Nouvel utilisateur
        </button>
      </div>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>}

      <div className="bg-white border border-slate-200 rounded-md overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-200 text-left text-slate-600">
                <th className="px-4 py-3 font-medium">Nom</th>
                <th className="px-4 py-3 font-medium">Email</th>
                <th className="px-4 py-3 font-medium">Rôle</th>
                <th className="px-4 py-3 font-medium">Identité</th>
                <th className="px-4 py-3 font-medium"></th>
              </tr>
            </thead>
            <tbody>
              {utilisateurs.map((u) => (
                <tr key={u.id_utilisateur} className="border-b border-slate-100 last:border-0">
                  <td className="px-4 py-3 font-medium text-navy-900">{u.nom}</td>
                  <td className="px-4 py-3 text-slate-600">{u.email}</td>
                  <td className="px-4 py-3">
                    <Badge tone="info">{ROLE_LABELS[u.role]}</Badge>
                  </td>
                  <td className="px-4 py-3">
                    {u.identite_verifiee ? (
                      <span className="flex items-center gap-1 text-xs text-success-700">
                        <ShieldCheck size={13} /> Vérifiée
                      </span>
                    ) : (
                      <span className="flex items-center gap-1 text-xs text-slate-400">
                        <ShieldAlert size={13} /> Non vérifiée
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button onClick={() => ouvrirEdition(u)} className="text-slate-400 hover:text-navy-900">
                      <Pencil size={15} />
                    </button>
                    {!u.identite_verifiee && ['AVOCAT', 'JUSTICIABLE'].includes(u.role) && (
                      <button
                        onClick={() => verifier(u.id_utilisateur)}
                        title="Vérifier l'identité"
                        className="text-slate-400 hover:text-success-700 ml-2"
                      >
                        <ShieldCheck size={15} />
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {modalOuvert && (
        <div className="fixed inset-0 bg-navy-950/40 flex items-center justify-center z-10">
          <div className="bg-white rounded-md w-full max-w-sm p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-medium text-navy-900">
                {enEdition ? 'Modifier l’utilisateur' : 'Nouvel utilisateur'}
              </h2>
              <button onClick={() => setModalOuvert(false)} className="text-slate-400 hover:text-navy-900">
                <X size={18} />
              </button>
            </div>

            {motDePasseTemp ? (
              <div>
                <p className="text-sm text-slate-600 mb-2">
                  Compte créé. Mot de passe temporaire à transmettre à l'utilisateur :
                </p>
                <p className="bg-navy-50 text-navy-900 font-mono text-sm rounded px-3 py-2 mb-4">
                  {motDePasseTemp}
                </p>
                <button
                  onClick={() => setModalOuvert(false)}
                  className="bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 transition-colors"
                >
                  Fermer
                </button>
              </div>
            ) : (
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
                  <label className="block text-sm font-medium text-slate-600 mb-1.5">Email</label>
                  <input
                    type="email"
                    value={form.email}
                    onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
                    required
                    className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-600 mb-1.5">Rôle</label>
                  <select
                    value={form.role}
                    onChange={(e) => setForm((f) => ({ ...f, role: e.target.value }))}
                    className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
                  >
                    {Object.values(Role).map((r) => (
                      <option key={r} value={r}>
                        {ROLE_LABELS[r]}
                      </option>
                    ))}
                  </select>
                </div>

                {form.role === Role.JUSTICIABLE && (
                  <div>
                    <label className="block text-sm font-medium text-slate-600 mb-1.5">CNI</label>
                    <input
                      value={form.cni}
                      onChange={(e) => setForm((f) => ({ ...f, cni: e.target.value }))}
                      required
                      className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
                    />
                  </div>
                )}

                {form.role === Role.AVOCAT && (
                  <div>
                    <label className="block text-sm font-medium text-slate-600 mb-1.5">Numéro de barreau</label>
                    <input
                      value={form.numero_barreau}
                      onChange={(e) => setForm((f) => ({ ...f, numero_barreau: e.target.value }))}
                      required
                      className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
                    />
                  </div>
                )}

                <div className="flex items-center gap-3 pt-2">
                  <button
                    type="submit"
                    className="bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 transition-colors"
                  >
                    {enEdition ? 'Enregistrer' : 'Créer le compte'}
                  </button>
                  <button
                    type="button"
                    onClick={() => setModalOuvert(false)}
                    className="text-sm text-slate-600 hover:text-navy-900"
                  >
                    Annuler
                  </button>
                </div>
              </form>
            )}
          </div>
        </div>
      )}
    </div>
  )
}