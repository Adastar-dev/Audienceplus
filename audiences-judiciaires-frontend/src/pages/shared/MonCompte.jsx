import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Loader2, Lock, ShieldCheck, ShieldAlert } from 'lucide-react'
import { getMonCompte, completerCompte } from '../../services/api/auth'
import { useAuth } from '../../context/AuthContext'
import { ROLE_LABELS } from '../../constants/enums'
import DepotCni from '../../components/identite/DepotCni'

// Champ vide que l'utilisateur peut renseigner une seule fois ; ensuite,
// seul l'administrateur peut le modifier.
function ChampACompleter({ libelle, champ, placeholder, initial = '', onComplete }) {
  const [valeur, setValeur] = useState(initial)
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState('')

  async function enregistrer(e) {
    e.preventDefault()
    setErreur('')
    setEnvoi(true)
    try {
      onComplete(await completerCompte({ [champ]: valeur }))
    } catch (err) {
      setErreur(
        err.response?.data?.errors?.[champ]?.[0] || err.response?.data?.message || "Impossible d'enregistrer cette information.",
      )
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <div className="flex flex-col sm:flex-row sm:items-start px-5 py-3 gap-1">
      <dt className="sm:w-56 text-xs uppercase tracking-wide text-slate-400 sm:pt-2">{libelle}</dt>
      <dd className="flex-1">
        <form onSubmit={enregistrer} className="flex flex-wrap items-center gap-2">
          <input
            value={valeur}
            onChange={(e) => setValeur(e.target.value)}
            placeholder={placeholder}
            className="border border-slate-200 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
          />
          <button
            type="submit"
            disabled={!valeur.trim() || envoi}
            className="bg-navy-900 text-white text-xs font-medium rounded px-3 py-2 hover:bg-navy-800 disabled:opacity-40"
          >
            {envoi ? 'Enregistrement...' : 'Ajouter'}
          </button>
        </form>
        <p className="text-xs text-slate-400 mt-1">Une fois enregistrée, cette information ne pourra être modifiée que par l'administrateur.</p>
        {erreur && <p className="text-xs text-danger-700 mt-1">{erreur}</p>}
      </dd>
    </div>
  )
}

function Ligne({ libelle, valeur }) {
  return (
    <div className="flex flex-col sm:flex-row sm:items-center px-5 py-3 gap-1">
      <dt className="sm:w-56 text-xs uppercase tracking-wide text-slate-400">{libelle}</dt>
      <dd className="text-sm text-navy-900">{valeur || <span className="text-slate-400">Non renseigné</span>}</dd>
    </div>
  )
}

// Informations personnelles du compte, pour tous les rôles : l'utilisateur
// complète ce qui manque (téléphone, n° et photos de CNI) ; ce qui est déjà
// renseigné n'est modifiable que par l'administrateur (gestion des utilisateurs).
export default function MonCompte() {
  const { mettreAJourUtilisateur } = useAuth()
  const [compte, setCompte] = useState(null)
  const [erreur, setErreur] = useState('')

  function complete(utilisateur) {
    setCompte(utilisateur)
    mettreAJourUtilisateur(utilisateur)
  }

  function charger() {
    getMonCompte()
      .then(setCompte)
      .catch(() => setErreur('Impossible de charger votre compte.'))
  }

  useEffect(charger, [])

  if (erreur) return <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2">{erreur}</p>
  if (!compte) {
    return (
      <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
        <Loader2 size={16} className="animate-spin" />
        Chargement...
      </div>
    )
  }

  const partie = ['JUSTICIABLE', 'AVOCAT'].includes(compte.role)
  const doitDeposerCni = partie && !compte.a_photo_cni

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Mon compte</h1>
      <p className="text-sm text-slate-600 mt-1 flex items-center gap-1.5">
        <Lock size={14} />
        Vous pouvez compléter les informations manquantes ; les informations déjà renseignées ne peuvent être modifiées que
        par l'administrateur.
        {compte.role === 'ADMINISTRATEUR' ? (
          <Link to="/admin/utilisateurs" className="text-navy-900 underline ml-1">
            Gérer les comptes
          </Link>
        ) : (
          ' En cas d’erreur, adressez-vous au greffe.'
        )}
      </p>

      {doitDeposerCni && (
        <div className="mt-6">
          <DepotCni onDepose={charger} />
        </div>
      )}

      <dl className="mt-6 bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
        <Ligne libelle="Nom complet" valeur={compte.nom} />
        <Ligne libelle="Rôle" valeur={ROLE_LABELS[compte.role] ?? compte.role} />
        <Ligne libelle="Email" valeur={compte.email} />
        {compte.telephone ? (
          <Ligne libelle="Téléphone" valeur={compte.telephone} />
        ) : (
          <ChampACompleter libelle="Téléphone" champ="telephone" initial="+221 " placeholder="+221 77 000 00 00" onComplete={complete} />
        )}
        {partie &&
          (compte.cni || compte.identite_verifiee ? (
            <Ligne libelle="N° de carte d'identité" valeur={compte.cni} />
          ) : (
            <ChampACompleter libelle="N° de carte d'identité" champ="cni" placeholder="10 à 13 chiffres" onComplete={complete} />
          ))}
        {compte.role === 'AVOCAT' && <Ligne libelle="N° de barreau" valeur={compte.numero_barreau} />}
        {!partie && compte.role !== 'ADMINISTRATEUR' && (
          <Ligne libelle="Tribunal" valeur={compte.tribunal ? `${compte.tribunal.nom} (${compte.tribunal.ville})` : null} />
        )}
        {partie && (
          <Ligne
            libelle="Carte d'identité"
            valeur={compte.a_photo_cni ? 'Recto et verso déposés' : 'À déposer (recto et verso)'}
          />
        )}
        {partie && (
          <Ligne
            libelle="Vérification du compte"
            valeur={
              compte.identite_verifiee ? (
                <span className="flex items-center gap-1 text-success-700">
                  <ShieldCheck size={15} /> Compte vérifié
                </span>
              ) : (
                <span className="flex items-center gap-1 text-gold-600">
                  <ShieldAlert size={15} /> En attente de vérification par l'administration
                </span>
              )
            }
          />
        )}
        <Ligne libelle="Compte créé le" valeur={compte.created_at && new Date(compte.created_at).toLocaleDateString('fr-FR')} />
      </dl>
    </div>
  )
}
