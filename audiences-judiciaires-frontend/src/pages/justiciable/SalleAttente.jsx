import { useEffect, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { ShieldCheck, Loader2, IdCard } from 'lucide-react'
import { getAudienceById } from '../../services/api/audiences'
import { envoyerOtp, verifierOtp, envoyerPhotoCni } from '../../services/api/participations'

// Verification d'identite en deux etapes : photo de la CNI (numero lu par OCR,
// indicateur pour le greffier, jamais bloquant) puis code a 6 chiffres envoye
// par SMS (seul verrou, verifie aussi par le backend avant l'acces a Jitsi).
// Remplace la comparaison faciale Face++, retiree.
export default function SalleAttente() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [audience, setAudience] = useState(null)
  const [chargement, setChargement] = useState(true)
  const [confirme, setConfirme] = useState(false)
  const [code, setCode] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const [verification, setVerification] = useState(false)
  const [codeEnvoye, setCodeEnvoye] = useState(false)
  const [photoCni, setPhotoCni] = useState(null)
  const [cniEnvoyee, setCniEnvoyee] = useState(false)
  const [envoiCni, setEnvoiCni] = useState(false)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    getAudienceById(id)
      .then(setAudience)
      .catch(() => setAudience(null))
      .finally(() => setChargement(false))
  }, [id])

  async function handleEnvoyerCni(e) {
    e.preventDefault()
    if (!photoCni) return
    setErreur('')
    setEnvoiCni(true)
    try {
      await envoyerPhotoCni(id, photoCni)
      setCniEnvoyee(true)
    } catch (err) {
      setErreur(
        err.response?.data?.errors
          ? Object.values(err.response.data.errors).flat().join(' ')
          : err.response?.data?.message || "Impossible d'envoyer la photo de la CNI.",
      )
    } finally {
      setEnvoiCni(false)
    }
  }

  async function handleEnvoyerCode() {
    setErreur('')
    setEnvoi(true)
    try {
      await envoyerOtp(id)
      setCodeEnvoye(true)
    } catch (err) {
      setErreur(err.response?.data?.message || "Impossible d'envoyer le code.")
    } finally {
      setEnvoi(false)
    }
  }

  async function handleVerifierCode(e) {
    e.preventDefault()
    setErreur('')
    setVerification(true)
    try {
      await verifierOtp(id, code)
      setConfirme(true)
    } catch (err) {
      setErreur(err.response?.data?.message || 'Code invalide.')
    } finally {
      setVerification(false)
    }
  }

  if (chargement) {
    return (
      <div className="flex items-center justify-center py-16">
        <Loader2 size={20} className="animate-spin text-slate-400" />
      </div>
    )
  }

  if (!audience) {
    return (
      <div>
        <p className="text-sm text-slate-600">Audience introuvable.</p>
        <Link to="/justiciable" className="text-sm text-navy-900 underline">
          Retour
        </Link>
      </div>
    )
  }

  // Doit rester cohérent avec ParticipationController::refuserSiHorsFenetre
  // côté backend, qui reste la source de vérité (seul rempart réel).
  const STATUTS_TERMINES = ['CLOTUREE', 'RENVOYEE', 'RATEE']
  const ouvertureAcces = new Date(new Date(audience.date_heure).getTime() - 30 * 60 * 1000)
  const audienceTerminee = STATUTS_TERMINES.includes(audience.statut)
  const pasEncoreOuvert = !audienceTerminee && new Date() < ouvertureAcces

  if (audienceTerminee || pasEncoreOuvert) {
    return (
      <div className="max-w-md mx-auto text-center">
        <h1 className="font-display text-2xl text-navy-900 mb-1">Vérification d'identité</h1>
        <p className="text-sm text-slate-600 mb-8">
          Avant de rejoindre l'audience de {audience.dossier?.parties}
        </p>
        <div className="bg-white border border-slate-200 rounded-md p-6">
          <p className="text-sm text-slate-600">
            {audienceTerminee
              ? 'Cette audience est terminée.'
              : `L'accès à la salle d'attente ouvrira à ${ouvertureAcces.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}.`}
          </p>
        </div>
      </div>
    )
  }

  return (
    <div className="max-w-md mx-auto">
      <h1 className="font-display text-2xl text-navy-900 text-center mb-1">
        Vérification d'identité
      </h1>
      <p className="text-sm text-slate-600 text-center mb-8">
        Avant de rejoindre l'audience de {audience.dossier?.parties}
      </p>

      <div className="bg-white border border-slate-200 rounded-md p-6">
        {erreur && (
          <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>
        )}

        {confirme ? (
          <div className="text-center">
            <Loader2 size={28} className="mx-auto text-navy-700 mb-3 animate-spin" />
            <p className="text-sm font-medium text-navy-900 mb-1">Identité vérifiée</p>
            <p className="text-sm text-slate-600 mb-4">
              Vous patientez dans la salle d'attente virtuelle. Le juge vous fera entrer sous peu.
            </p>
            <button
              onClick={() => navigate(`/justiciable/audiences/${id}/rejoindre`)}
              className="text-sm text-navy-900 underline"
            >
              Entrer dans la salle d'audience
            </button>
          </div>
        ) : !cniEnvoyee ? (
          <form onSubmit={handleEnvoyerCni} className="text-center">
            <IdCard size={32} className="mx-auto text-navy-700 mb-3" />
            <p className="text-sm font-medium text-navy-900 mb-1">Étape 1 sur 2 : votre pièce d'identité</p>
            <p className="text-sm text-slate-600 mb-4">
              Prenez en photo le recto de votre carte nationale d'identité, numéro bien lisible.
            </p>
            <input
              type="file"
              accept="image/jpeg,image/png"
              capture="environment"
              onChange={(e) => setPhotoCni(e.target.files?.[0] ?? null)}
              className="w-full text-sm text-slate-600 mb-4 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm"
            />
            <button
              type="submit"
              disabled={!photoCni || envoiCni}
              className="w-full bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 disabled:opacity-40 transition-colors"
            >
              {envoiCni ? 'Envoi...' : 'Envoyer la photo'}
            </button>
          </form>
        ) : !codeEnvoye ? (
          <div className="text-center">
            <ShieldCheck size={32} className="mx-auto text-navy-700 mb-3" />
            <p className="text-sm font-medium text-navy-900 mb-1">Étape 2 sur 2 : code de vérification</p>
            <p className="text-sm text-slate-600 mb-4">
              Un code à 6 chiffres sera envoyé par SMS au numéro associé à votre compte.
            </p>
            <button
              onClick={handleEnvoyerCode}
              disabled={envoi}
              className="bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 disabled:opacity-60 transition-colors"
            >
              {envoi ? 'Envoi...' : 'Recevoir mon code par SMS'}
            </button>
          </div>
        ) : (
          <form onSubmit={handleVerifierCode} className="text-center">
            <p className="text-sm text-slate-600 mb-3">
              Un code a été envoyé par SMS au numéro associé à votre compte.
            </p>
            <input
              value={code}
              onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
              placeholder="Code à 6 chiffres"
              inputMode="numeric"
              maxLength={6}
              autoFocus
              className="w-full text-center tracking-widest border border-slate-200 rounded px-3 py-2 text-sm mb-4 focus:outline-none focus:ring-2 focus:ring-navy-700"
            />
            <button
              type="submit"
              disabled={code.length !== 6 || verification}
              className="w-full bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 disabled:opacity-40 transition-colors mb-3"
            >
              {verification ? 'Vérification...' : 'Valider le code'}
            </button>
            <button
              type="button"
              onClick={handleEnvoyerCode}
              disabled={envoi}
              className="text-xs text-navy-700 hover:underline disabled:opacity-60"
            >
              {envoi ? 'Envoi...' : 'Recevoir un nouveau code'}
            </button>
          </form>
        )}
      </div>
    </div>
  )
}
