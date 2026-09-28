import { useEffect, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { ShieldCheck, Loader2, Mail } from 'lucide-react'
import { getAudienceById } from '../../services/api/audiences'
import { envoyerOtp, verifierOtp } from '../../services/api/participations'
import { useAuth } from '../../context/AuthContext'

// Salle d'attente commune au justiciable, à l'avocat et au procureur : un code
// à 6 chiffres est envoyé par email.
// Une fois le code confirmé, la personne attend que le greffier ou le juge la
// fasse entrer (contrôlé aussi par le backend avant la délivrance du jeton Jitsi).
// L'identité du compte a déjà été établie à l'inscription (photo de la CNI
// vérifiée par l'administration).
export default function SalleAttente() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { role } = useAuth()
  const base = `/${(role ?? '').toLowerCase()}`
  const [audience, setAudience] = useState(null)
  const [chargement, setChargement] = useState(true)
  const [confirme, setConfirme] = useState(false)
  const [code, setCode] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const [verification, setVerification] = useState(false)
  const [codeEnvoye, setCodeEnvoye] = useState(false)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    getAudienceById(id)
      .then(setAudience)
      .catch(() => setAudience(null))
      .finally(() => setChargement(false))
  }, [id])

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
      <div className="p-6">
        <p className="text-sm text-slate-600">Audience introuvable.</p>
        <Link to={base} className="text-sm text-navy-900 underline">
          Retour
        </Link>
      </div>
    )
  }

  // Doit rester cohérent avec ParticipationController::refuserSiHorsFenetre
  // côté backend, qui reste la source de vérité.
  const STATUTS_TERMINES = ['CLOTUREE', 'RENVOYEE', 'RATEE']
  const ouvertureAcces = new Date(new Date(audience.date_heure).getTime() - 30 * 60 * 1000)
  const audienceTerminee = STATUTS_TERMINES.includes(audience.statut)
  const pasEncoreOuvert = !audienceTerminee && new Date() < ouvertureAcces

  return (
    <div className="min-h-screen bg-slate-50 px-4 py-12">
      <div className="max-w-md mx-auto">
        <h1 className="font-display text-2xl text-navy-900 text-center mb-1">Salle d'attente</h1>
        <p className="text-sm text-slate-600 text-center mb-8">
          Audience du dossier {audience.dossier?.numero} ({audience.dossier?.parties})
        </p>

        <div className="bg-white border border-slate-200 rounded-md p-6">
          {erreur && (
            <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>
          )}

          {audienceTerminee || pasEncoreOuvert ? (
            <p className="text-sm text-slate-600 text-center">
              {audienceTerminee
                ? 'Cette audience est terminée.'
                : `L'accès à la salle d'attente ouvrira à ${ouvertureAcces.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}.`}
            </p>
          ) : confirme ? (
            <div className="text-center">
              <ShieldCheck size={28} className="mx-auto text-success-700 mb-3" />
              <p className="text-sm font-medium text-navy-900 mb-1">Identité confirmée</p>
              <p className="text-sm text-slate-600 mb-4">
                Le greffier ou le juge va vous faire entrer dans la salle d'audience.
              </p>
              <button
                onClick={() => navigate(`${base}/audiences/${id}/rejoindre`)}
                className="bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 transition-colors"
              >
                Patienter devant la salle
              </button>
            </div>
          ) : !codeEnvoye ? (
            <div className="text-center">
              <Mail size={32} className="mx-auto text-navy-700 mb-3" />
              <p className="text-sm text-slate-600 mb-4">
                Pour confirmer votre identité, un code de vérification à 6 chiffres va vous être envoyé
                par email à l'adresse de votre compte.
              </p>
              <button
                onClick={handleEnvoyerCode}
                disabled={envoi}
                className="bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 disabled:opacity-60 transition-colors"
              >
                {envoi ? 'Envoi...' : 'Recevoir mon code'}
              </button>
            </div>
          ) : (
            <form onSubmit={handleVerifierCode} className="text-center">
              <p className="text-sm text-slate-600 mb-3">
                Un code valable 5 minutes vous a été envoyé par email à l'adresse de votre compte.
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

        <p className="text-center mt-4">
          <Link to={base} className="text-xs text-slate-500 hover:underline">
            Retour à mon espace
          </Link>
        </p>
      </div>
    </div>
  )
}
