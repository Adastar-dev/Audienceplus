import { useEffect, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { ArrowLeft, Upload, RotateCcw, Video, Loader2 } from 'lucide-react'
import { getDossierById } from '../../services/api/dossiers'
import { listerPieces, deposerPiece } from '../../services/api/pieces'
import { listerAudiences } from '../../services/api/audiences'
import { listerConvocations, demanderReport } from '../../services/api/convocations'
import DossierStatusBadge from '../../components/ui/DossierStatusBadge'

export default function DossierDetailAvocat() {
  const { id } = useParams()
  const navigate = useNavigate()

  const [dossier, setDossier] = useState(null)
  const [pieces, setPieces] = useState([])
  const [audienceLiee, setAudienceLiee] = useState(null)
  const [convocationLiee, setConvocationLiee] = useState(null)
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  const [showReport, setShowReport] = useState(false)
  const [motif, setMotif] = useState('')
  const [reportEnvoye, setReportEnvoye] = useState(false)

  useEffect(() => {
    Promise.all([getDossierById(id), listerPieces(id), listerAudiences(), listerConvocations()])
      .then(([d, p, audiences, convocations]) => {
        setDossier(d)
        setPieces(p)
        const aud = audiences.find((a) => a.id_dossier === Number(id))
        setAudienceLiee(aud ?? null)
        if (aud) {
          setConvocationLiee(convocations.find((c) => c.id_audience === aud.id_audience) ?? null)
        }
      })
      .catch(() => setErreur('Impossible de charger ce dossier.'))
      .finally(() => setChargement(false))
  }, [id])

  async function handleUpload(e) {
    const file = e.target.files?.[0]
    if (!file) return
    try {
      const nouvellePiece = await deposerPiece(id, file)
      setPieces((list) => [...list, nouvellePiece])
    } catch {
      setErreur('Impossible de déposer cette pièce.')
    }
    e.target.value = ''
  }

  async function handleDemanderReport() {
    if (!motif.trim() || !convocationLiee) return
    try {
      await demanderReport(convocationLiee.id_convocation, motif)
      setReportEnvoye(true)
    } catch {
      setErreur('Impossible de transmettre la demande de report.')
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

  if (!dossier) {
    return (
      <div>
        <p className="text-sm text-slate-600">Dossier introuvable.</p>
        <Link to="/avocat/dossiers" className="text-sm text-navy-900 underline">Retour</Link>
      </div>
    )
  }

  return (
    <div>
      <button onClick={() => navigate('/avocat/dossiers')} className="flex items-center gap-1 text-sm text-slate-600 hover:text-navy-900 mb-4">
        <ArrowLeft size={14} /> Retour à mes dossiers
      </button>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>}

      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="font-display text-2xl text-navy-900">{dossier.numero}</h1>
          <p className="text-sm text-slate-600 mt-1">{dossier.parties}</p>
        </div>
        <DossierStatusBadge statut={dossier.statut} />
      </div>

      {audienceLiee && (
        <Link
          to={`/avocat/audiences/${audienceLiee.id_audience}/rejoindre`}
          className="flex items-center gap-2 bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 transition-colors w-fit mb-4"
        >
          <Video size={15} />
          Rejoindre l'audience à distance
        </Link>
      )}

      <div className="bg-white border border-slate-200 rounded-md p-5 mb-4">
        <h2 className="font-medium text-navy-900 mb-3">Pièces et conclusions</h2>
        <label className="flex items-center justify-center gap-2 border-2 border-dashed border-slate-200 rounded-md py-6 text-sm text-slate-600 cursor-pointer hover:border-navy-700 hover:text-navy-900 transition-colors mb-3">
          <Upload size={16} />
          Déposer une pièce ou des conclusions
          <input type="file" className="hidden" onChange={handleUpload} />
        </label>
        <ul className="space-y-1.5 text-sm text-slate-600">
          {pieces.map((p) => <li key={p.id_piece}>{p.nom}</li>)}
        </ul>
      </div>

      {convocationLiee && (
        <div className="bg-white border border-slate-200 rounded-md p-5">
          <h2 className="font-medium text-navy-900 mb-3">Demander un report</h2>
          {reportEnvoye ? (
            <p className="text-sm text-success-700">Demande de report transmise au greffier.</p>
          ) : showReport ? (
            <div>
              <textarea
                value={motif}
                onChange={(e) => setMotif(e.target.value)}
                rows={3}
                placeholder="Motif et justificatif du report..."
                className="w-full border border-slate-200 rounded px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-navy-700"
              />
              <button
                onClick={handleDemanderReport}
                disabled={!motif.trim()}
                className="bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 disabled:opacity-40 transition-colors"
              >
                Envoyer la demande
              </button>
            </div>
          ) : (
            <button
              onClick={() => setShowReport(true)}
              className="flex items-center gap-2 border border-slate-200 text-slate-600 text-sm font-medium rounded px-4 py-2 hover:bg-slate-50 transition-colors"
            >
              <RotateCcw size={15} /> Demander un report
            </button>
          )}
        </div>
      )}
    </div>
  )
}
