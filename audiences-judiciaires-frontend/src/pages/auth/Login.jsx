import { useState } from 'react'
import { useNavigate, useLocation, Link } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { Role } from '../../constants/enums'

const CHEMINS_PAR_ROLE = {
  [Role.ADMINISTRATEUR]: '/admin',
}

export default function Login() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [identifiant, setIdentifiant] = useState('')
  const [motDePasse, setMotDePasse] = useState('')
  const [erreur, setErreur] = useState('')
  const [enCours, setEnCours] = useState(false)

  async function handleSubmit(e) {
    e.preventDefault()
    setErreur('')
    setEnCours(true)
    try {
      const utilisateur = await login({ identifiant, motDePasse })
      const destinationParDefaut = CHEMINS_PAR_ROLE[utilisateur.role] || `/${utilisateur.role.toLowerCase()}`
      const from = location.state?.from?.pathname || destinationParDefaut
      navigate(from, { replace: true })
    } catch (err) {
      if (err.response?.status === 401) {
        setErreur('Identifiants incorrects.')
      } else if (err.response?.status === 429) {
        setErreur('Trop de tentatives. Réessayez dans une minute.')
      } else {
        setErreur("Impossible de se connecter au serveur. Vérifiez que l'API est démarrée.")
      }
    } finally {
      setEnCours(false)
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-paper px-4">
      <div className="w-full max-w-sm">
        <div className="text-center mb-8">
          <p className="text-sm text-slate-600 tracking-wide">République du Sénégal</p>
          <h1 className="font-display text-2xl text-navy-900 mt-1">
            Audience+
          </h1>
        </div>

        <form
          onSubmit={handleSubmit}
          className="bg-white border border-slate-200 rounded-md p-6 shadow-sm"
        >
          {erreur && (
            <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">
              {erreur}
            </p>
          )}

          <label className="block text-sm font-medium text-slate-600 mb-1.5">
            Email ou téléphone
          </label>
          <input
            type="text"
            value={identifiant}
            onChange={(e) => setIdentifiant(e.target.value)}
            required
            className="w-full border border-slate-200 rounded px-3 py-2 text-sm mb-4 focus:outline-none focus:ring-2 focus:ring-navy-700"
          />

          <label className="block text-sm font-medium text-slate-600 mb-1.5">
            Mot de passe
          </label>
          <input
            type="password"
            value={motDePasse}
            onChange={(e) => setMotDePasse(e.target.value)}
            required
            className="w-full border border-slate-200 rounded px-3 py-2 text-sm mb-6 focus:outline-none focus:ring-2 focus:ring-navy-700"
          />

          <button
            type="submit"
            disabled={enCours}
            className="w-full bg-navy-900 text-white text-sm font-medium rounded py-2.5 hover:bg-navy-800 disabled:opacity-60 transition-colors"
          >
            {enCours ? 'Connexion...' : 'Se connecter'}
          </button>
        </form>

        <p className="text-sm text-slate-600 text-center mt-6">
          Pas encore de compte ?{' '}
          <Link to="/inscription" className="text-navy-900 font-medium hover:underline">
            S'inscrire
          </Link>
        </p>
      </div>
    </div>
  )
}
