import { useEffect, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { ArrowLeft, PenLine, FileDown, Loader2, Undo2 } from 'lucide-react'
import { getAudienceById } from '../../services/api/audiences'
import { getPV, validerPV, rejeterPV } from '../../services/api/procesVerbaux'
import { signerDocument, getSignaturesDocument } from '../../services/api/signatures'
import { StatutPV } from '../../constants/enums'
import { useAuth } from '../../context/AuthContext'
import PVStatusBadge from '../../components/ui/PVStatusBadge'

export default function ValidationPV() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { user } = useAuth()
  const [audience, setAudience] = useState(null)
  const [pv, setPv] = useState(null)
  const [signatures, setSignatures] = useState([])
  const [chargement, setChargement] = useState(true)
  const [enCours, setEnCours] = useState(false)
  const [erreur, setErreur] = useState('')
  const [showRejet, setShowRejet] = useState(false)
  const [commentaire, setCommentaire] = useState('')

  useEffect(() => {
    Promise.all([getAudienceById(id), getPV(id)])
      .then(async ([audienceData, pvData]) => {
        setAudience(audienceData)
        setPv(pvData)
        if (pvData) {
          const sigs = await getSignaturesDocument({
            typeDocument: 'PROCES_VERBAL',
            idDocumentSigne: pvData.id_pv,
          })
          setSignatures(sigs)
        }
      })
      .catch(() => setErreur('Impossible de charger le PV.'))
      .finally(() => setChargement(false))
  }, [id])

  async function handleSigner() {
    setEnCours(true)
    try {
      await signerDocument({ typeDocument: 'PROCES_VERBAL', idDocumentSigne: pv.id_pv })
      const pvValide = await validerPV(id)
      setPv(pvValide)
      const sigs = await getSignaturesDocument({
        typeDocument: 'PROCES_VERBAL',
        idDocumentSigne: pv.id_pv,
      })
      setSignatures(sigs)
    } catch {
      setErreur('Impossible de valider/signer le PV.')
    } finally {
      setEnCours(false)
    }
  }

  async function handleRejeter() {
    if (!commentaire.trim()) return
    setEnCours(true)
    try {
      const pvRejete = await rejeterPV(id, commentaire)
      setPv(pvRejete)
      setShowRejet(false)
      setCommentaire('')
    } catch {
      setErreur('Impossible de renvoyer le PV.')
    } finally {
      setEnCours(false)
    }
  }

  function handleExporter() {
    const blob = new Blob(
      [`PROCÈS-VERBAL (validé)\n\n${audience.dossier?.parties ?? ''}\n\n${pv.contenu}`],
      { type: 'application/pdf' },
    )
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `PV_audience_${id}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  }

  if (chargement) {
    return (
      <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
        <Loader2 size={16} className="animate-spin" />
        Chargement...
      </div>
    )
  }

  if (!audience || !pv) {
    return (
      <div>
        <p className="text-sm text-slate-600">Aucun PV transmis pour cette audience.</p>
        <Link to="/juge/audiences" className="text-sm text-navy-900 underline">
          Retour au calendrier
        </Link>
      </div>
    )
  }

  const dejaSigne = signatures.some((s) => s.id_utilisateur === user?.id_utilisateur)

  return (
    <div>
      <button
        onClick={() => navigate(`/juge/audiences/${id}`)}
        className="flex items-center gap-1 text-sm text-slate-600 hover:text-navy-900 mb-4"
      >
        <ArrowLeft size={14} />
        Retour à l'audience
      </button>

      <div className="flex items-center justify-between mb-1">
        <h1 className="font-display text-2xl text-navy-900">Procès-verbal à valider</h1>
        <PVStatusBadge statut={pv.statut} />
      </div>
      <p className="text-sm text-slate-600 mb-6">{audience.dossier?.parties}</p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>}

      <div className="bg-white border border-slate-200 rounded-md p-5 text-sm leading-relaxed text-slate-700">
        {pv.contenu}
      </div>

      {signatures.length > 0 && (
        <div className="mt-4 bg-navy-50 border border-navy-100 rounded-md p-4 text-sm">
          <p className="text-navy-900 font-medium mb-1">Signé électroniquement par :</p>
          <ul className="text-slate-600 space-y-0.5">
            {signatures.map((s) => (
              <li key={s.id_signature}>
                {s.utilisateur?.nom} — {new Date(s.date_signature).toLocaleString('fr-FR')}
              </li>
            ))}
          </ul>
        </div>
      )}

      {pv.statut === StatutPV.EN_VALIDATION && !dejaSigne && (
        <>
          {showRejet ? (
            <div className="mt-6 bg-white border border-slate-200 rounded-md p-5">
              <label className="block text-sm font-medium text-slate-600 mb-1.5">
                Corrections attendues
              </label>
              <textarea
                value={commentaire}
                onChange={(e) => setCommentaire(e.target.value)}
                rows={4}
                placeholder="Expliquez au greffier ce qui doit être corrigé..."
                className="w-full border border-slate-200 rounded px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-navy-700"
              />
              <div className="flex items-center gap-3">
                <button
                  onClick={handleRejeter}
                  disabled={enCours || !commentaire.trim()}
                  className="bg-danger-700 text-white text-sm font-medium rounded px-4 py-2 hover:opacity-90 disabled:opacity-40 transition-opacity"
                >
                  Renvoyer au greffier
                </button>
                <button
                  onClick={() => setShowRejet(false)}
                  className="text-sm text-slate-600 hover:text-navy-900"
                >
                  Annuler
                </button>
              </div>
            </div>
          ) : (
            <div className="flex items-center gap-3 mt-6">
              <button
                onClick={handleSigner}
                disabled={enCours}
                className="flex items-center gap-2 bg-gold-600 text-white text-sm font-medium rounded px-4 py-2 hover:opacity-90 disabled:opacity-60 transition-opacity"
              >
                <PenLine size={15} />
                {enCours ? 'Signature...' : 'Valider et signer électroniquement'}
              </button>
              <button
                onClick={() => setShowRejet(true)}
                className="flex items-center gap-2 border border-slate-200 text-slate-600 text-sm font-medium rounded px-4 py-2 hover:bg-slate-50 transition-colors"
              >
                <Undo2 size={15} />
                Rejeter avec commentaire
              </button>
            </div>
          )}
        </>
      )}

      {pv.statut === StatutPV.CLOTURE && (
        <div className="mt-6">
          <button
            onClick={handleExporter}
            className="flex items-center gap-2 border border-slate-200 text-navy-900 text-sm font-medium rounded px-4 py-2 hover:bg-navy-50 transition-colors"
          >
            <FileDown size={15} />
            Exporter le PV en PDF
          </button>
        </div>
      )}
    </div>
  )
}
