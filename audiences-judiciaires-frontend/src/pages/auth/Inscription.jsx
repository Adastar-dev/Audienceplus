import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'

export default function Inscription() {
  const { register } = useAuth()
  const navigate = useNavigate()
  const [profil, setProfil] = useState('JUSTICIABLE')
  const [form, setForm] = useState({
    nom: '',
    cni: '',
    telephone: '',
    email: '',
    mot_de_passe: '',
    numero_barreau: '',
  })
  const [erreurs, setErreurs] = useState({})
  const [enCours, setEnCours] = useState(false)

  function update(field, value) {
    setForm((f) => ({ ...f, [field]: value }))
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setErreurs({})
    setEnCours(true)
    try {
      const utilisateur = await register({ ...form, role: profil })
      navigate(`/${utilisateur.role.toLowerCase()}`)
    } catch (err) {
      if (err.response?.status === 422) {
        setErreurs(err.response.data.errors ?? {})
      } else {
        setErreurs({ general: ["Impossible de créer le compte. Réessayez."] })
      }
    } finally {
      setEnCours(false)
    }
  }

  function champErreur(champ) {
    return erreurs[champ]?.[0]
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-paper px-4 py-10">
      <div className="w-full max-w-sm">
        <div className="text-center mb-8">
          <p className="text-sm text-slate-600 tracking-wide">République du Sénégal</p>
          <h1 className="font-display text-2xl text-navy-900 mt-1">Créer un compte</h1>
        </div>

        <div className="flex border border-slate-200 rounded-md overflow-hidden mb-5 text-sm">
          <button
            type="button"
            onClick={() => setProfil('JUSTICIABLE')}
            className={`flex-1 py-2 ${profil === 'JUSTICIABLE' ? 'bg-navy-900 text-white' : 'bg-white text-slate-600'}`}
          >
            Justiciable
          </button>
          <button
            type="button"
            onClick={() => setProfil('AVOCAT')}
            className={`flex-1 py-2 ${profil === 'AVOCAT' ? 'bg-navy-900 text-white' : 'bg-white text-slate-600'}`}
          >
            Avocat
          </button>
        </div>

        <form
          onSubmit={handleSubmit}
          className="bg-white border border-slate-200 rounded-md p-6 shadow-sm space-y-4"
        >
          {erreurs.general && (
            <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2">
              {erreurs.general[0]}
            </p>
          )}

          <div>
            <label className="block text-sm font-medium text-slate-600 mb-1.5">Nom complet</label>
            <input
              value={form.nom}
              onChange={(e) => update('nom', e.target.value)}
              required
              className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
            />
            {champErreur('nom') && <p className="text-xs text-danger-700 mt-1">{champErreur('nom')}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-600 mb-1.5">
              Numéro de carte nationale d'identité
            </label>
            <input
              value={form.cni}
              onChange={(e) => update('cni', e.target.value)}
              required
              className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
            />
            {champErreur('cni') && <p className="text-xs text-danger-700 mt-1">{champErreur('cni')}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-600 mb-1.5">Téléphone</label>
            <input
              value={form.telephone}
              onChange={(e) => update('telephone', e.target.value)}
              required
              placeholder="+221 77 000 00 00"
              className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
            />
            {champErreur('telephone') && <p className="text-xs text-danger-700 mt-1">{champErreur('telephone')}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-600 mb-1.5">
              Email (optionnel)
            </label>
            <input
              type="email"
              value={form.email}
              onChange={(e) => update('email', e.target.value)}
              className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
            />
            {champErreur('email') && <p className="text-xs text-danger-700 mt-1">{champErreur('email')}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-600 mb-1.5">Mot de passe</label>
            <input
              type="password"
              value={form.mot_de_passe}
              onChange={(e) => update('mot_de_passe', e.target.value)}
              required
              minLength={8}
              className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
            />
            {champErreur('mot_de_passe') && <p className="text-xs text-danger-700 mt-1">{champErreur('mot_de_passe')}</p>}
          </div>

          {profil === 'AVOCAT' && (
            <div>
              <label className="block text-sm font-medium text-slate-600 mb-1.5">
                Numéro de barreau
              </label>
              <input
                value={form.numero_barreau}
                onChange={(e) => update('numero_barreau', e.target.value)}
                required
                className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
              />
              {champErreur('numero_barreau') && (
                <p className="text-xs text-danger-700 mt-1">{champErreur('numero_barreau')}</p>
              )}
            </div>
          )}

          <button
            type="submit"
            disabled={enCours}
            className="w-full bg-navy-900 text-white text-sm font-medium rounded py-2.5 hover:bg-navy-800 disabled:opacity-60 transition-colors"
          >
            {enCours ? 'Création...' : 'Créer mon compte'}
          </button>
        </form>

        <p className="text-sm text-slate-600 text-center mt-4">
          Déjà inscrit ?{' '}
          <Link to="/connexion" className="text-navy-900 font-medium hover:underline">
            Se connecter
          </Link>
        </p>
      </div>
    </div>
  )
}
