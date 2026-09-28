import { useState } from 'react'
import { IdCard, Loader2 } from 'lucide-react'
import { deposerPhotoCni } from '../../services/api/auth'
import { useAuth } from '../../context/AuthContext'

// Dépôt du recto et du verso de la carte d'identité (compte créé par
// l'administrateur, ou verso manquant). L'administration vérifie ensuite le compte.
export default function DepotCni({ onDepose }) {
  const { mettreAJourUtilisateur } = useAuth()
  const [recto, setRecto] = useState(null)
  const [verso, setVerso] = useState(null)
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState('')

  async function handleSubmit(e) {
    e.preventDefault()
    if (!recto || !verso) return
    setErreur('')
    setEnvoi(true)
    try {
      const utilisateur = await deposerPhotoCni(recto, verso)
      mettreAJourUtilisateur(utilisateur)
      onDepose?.(utilisateur)
    } catch (err) {
      setErreur(
        err.response?.data?.errors
          ? Object.values(err.response.data.errors).flat().join(' ')
          : err.response?.data?.message || "Impossible d'envoyer les photos.",
      )
    } finally {
      setEnvoi(false)
    }
  }

  const champ =
    'w-full text-sm text-slate-600 mb-4 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm'

  return (
    <form onSubmit={handleSubmit} className="bg-white border border-gold-600/40 rounded-md p-5">
      <div className="flex items-center gap-2 mb-2">
        <IdCard size={18} className="text-navy-700" />
        <h2 className="font-medium text-navy-900">Carte nationale d'identité</h2>
      </div>
      <p className="text-sm text-slate-600 mb-4">
        Déposez une photo du recto et du verso, numéro bien lisible (JPG ou PNG, 5 Mo maximum par photo). L'administration
        vérifiera ensuite votre compte.
      </p>
      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>}
      <label className="block text-sm font-medium text-slate-600 mb-1">Recto</label>
      <input type="file" accept="image/jpeg,image/png" capture="environment" onChange={(e) => setRecto(e.target.files?.[0] ?? null)} className={champ} />
      <label className="block text-sm font-medium text-slate-600 mb-1">Verso</label>
      <input type="file" accept="image/jpeg,image/png" capture="environment" onChange={(e) => setVerso(e.target.files?.[0] ?? null)} className={champ} />
      <button
        type="submit"
        disabled={!recto || !verso || envoi}
        className="flex items-center gap-2 bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 disabled:opacity-40 transition-colors"
      >
        {envoi && <Loader2 size={15} className="animate-spin" />}
        {envoi ? 'Envoi...' : 'Envoyer les photos'}
      </button>
    </form>
  )
}
