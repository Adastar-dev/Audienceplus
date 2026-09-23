import { useEffect, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { ArrowLeft, Send, FileDown, Loader2, AlertTriangle } from 'lucide-react'
import { getAudienceById } from '../../services/api/audiences'
import { getPV, enregistrerBrouillon, transmettrePV } from '../../services/api/procesVerbaux'
import { StatutPV } from '../../constants/enums'
import PVStatusBadge from '../../components/ui/PVStatusBadge'

export default function RedactionPV() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [audience, setAudience] = useState(null)
  const [contenu, setContenu] = useState('')
  const [statut, setStatut] = useState(StatutPV.EN_COURS)
  const [commentaireRejet, setCommentaireRejet] = useState(null)
  const [chargement, setChargement] = useState(true)
  const [enCours, setEnCours] = useState(false)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    Promise.all([getAudienceById(id), getPV(id)])
      .then(([audienceData, pv]) => {
        setAudience(audienceData)
        if (pv) {
          setContenu(pv.contenu ?? '')
          setStatut(pv.statut)
          setCommentaireRejet(pv.commentaire_rejet ?? null)
        }
      })
      .catch(() => setErreur('Impossible de charger cette audience.'))
      .finally(() => setChargement(false))
  }, [id])

  async function handleSauvegarder() {
    setEnCours(true)
    try {
      await enregistrerBrouillon(id, contenu)
    } catch {
      setErreur("Impossible d'enregistrer le brouillon.")
    } finally {
      setEnCours(false)
    }
  }

  async function handleTransmettre() {
    setEnCours(true)
    try {
      await enregistrerBrouillon(id, contenu)
      const pv = await transmettrePV(id)
      setStatut(pv.statut)
      setCommentaireRejet(null)
    } catch {
      setErreur('Impossible de transmettre le PV.')
    } finally {
      setEnCours(false)
    }
  }

  function handleExporter() {
    const blob = new Blob(
      [`PROCÈS-VERBAL\n\n${audience.dossier?.parties ?? ''}\n${audience.dossier?.numero ?? ''}\n\n${contenu}`],
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

  if (!audience) {
    return (
      <div>
        <p className="text-sm text-slate-600">Audience introuvable.</p>
        <Link to="/greffier/dossiers" className="text-sm text-navy-900 underline">
          Retour aux dossiers
        </Link>
      </div>
    )
  }

  const modifiable = statut === StatutPV.EN_COURS

  return (
    <div>
      <button
        onClick={() => navigate(-1)}
        className="flex items-center gap-1 text-sm text-slate-600 hover:text-navy-900 mb-4"
      >
        <ArrowLeft size={14} />
        Retour
      </button>

      <div className="flex items-center justify-between mb-1">
        <h1 className="font-display text-2xl text-navy-900">Procès-verbal</h1>
        <PVStatusBadge statut={statut} />
      </div>
      <p className="text-sm text-slate-600 mb-6">{audience.dossier?.parties}</p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>}

      {commentaireRejet && (
        <div className="flex items-start gap-2 bg-gold-100 text-navy-900 text-sm rounded-md px-4 py-3 mb-4">
          <AlertTriangle size={16} className="text-gold-600 mt-0.5 shrink-0" />
          <div>
            <p className="font-medium mb-0.5">Le juge a renvoyé ce PV pour correction :</p>
            <p>{commentaireRejet}</p>
          </div>
        </div>
      )}

      <textarea
        value={contenu}
        onChange={(e) => setContenu(e.target.value)}
        disabled={!modifiable}
        rows={14}
        placeholder="Rédigez le procès-verbal de l'audience..."
        className="w-full border border-slate-200 rounded-md p-4 text-sm leading-relaxed focus:outline-none focus:ring-2 focus:ring-navy-700 disabled:bg-slate-50 disabled:text-slate-500"
      />

      <div className="flex items-center gap-3 mt-4">
        {modifiable ? (
          <>
            <button
              onClick={handleSauvegarder}
              disabled={enCours || !contenu.trim()}
              className="border border-slate-200 text-navy-900 text-sm font-medium rounded px-4 py-2 hover:bg-navy-50 disabled:opacity-40 transition-colors"
            >
              Enregistrer le brouillon
            </button>
            <button
              onClick={handleTransmettre}
              disabled={enCours || !contenu.trim()}
              className="flex items-center gap-2 bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 disabled:opacity-40 transition-colors"
            >
              <Send size={15} />
              {commentaireRejet ? 'Retransmettre au juge' : 'Transmettre au juge pour validation'}
            </button>
          </>
        ) : (
          <button
            onClick={handleExporter}
            className="flex items-center gap-2 border border-slate-200 text-navy-900 text-sm font-medium rounded px-4 py-2 hover:bg-navy-50 transition-colors"
          >
            <FileDown size={15} />
            Exporter en PDF
          </button>
        )}

        {statut === StatutPV.EN_VALIDATION && (
          <p className="text-xs text-slate-400">En attente de validation par le juge.</p>
        )}
      </div>
    </div>
  )
}
