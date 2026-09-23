import { useEffect, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { ArrowLeft, Upload, Check, Trash2, FileText, Loader2 } from 'lucide-react'
import { getDossierById } from '../../services/api/dossiers'
import { listerPieces, deposerPiece, validerPiece, supprimerPiece } from '../../services/api/pieces'

export default function DepotPieces() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [dossier, setDossier] = useState(null)
  const [pieces, setPieces] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    Promise.all([getDossierById(id), listerPieces(id)])
      .then(([d, p]) => {
        setDossier(d)
        setPieces(p)
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

  async function handleValider(idPiece) {
    try {
      const updated = await validerPiece(idPiece)
      setPieces((list) => list.map((p) => (p.id_piece === idPiece ? updated : p)))
    } catch {
      setErreur('Impossible de valider cette pièce.')
    }
  }

  async function handleSupprimer(idPiece) {
    try {
      await supprimerPiece(idPiece)
      setPieces((list) => list.filter((p) => p.id_piece !== idPiece))
    } catch {
      setErreur('Impossible de supprimer cette pièce.')
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
        <Link to="/greffier/dossiers" className="text-sm text-navy-900 underline">
          Retour à la liste
        </Link>
      </div>
    )
  }

  return (
    <div>
      <button
        onClick={() => navigate(`/greffier/dossiers/${id}`)}
        className="flex items-center gap-1 text-sm text-slate-600 hover:text-navy-900 mb-4"
      >
        <ArrowLeft size={14} />
        Retour au dossier
      </button>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>}

      <h1 className="font-display text-2xl text-navy-900">Pièces du dossier</h1>
      <p className="text-sm text-slate-600 mt-1">{dossier.numero} — {dossier.parties}</p>

      <label className="mt-6 flex items-center justify-center gap-2 border-2 border-dashed border-slate-200 rounded-md py-8 text-sm text-slate-600 cursor-pointer hover:border-navy-700 hover:text-navy-900 transition-colors">
        <Upload size={18} />
        Déposer un fichier (PDF, image, audio)
        <input type="file" className="hidden" onChange={handleUpload} />
      </label>

      <div className="mt-6 bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
        {pieces.map((p) => (
          <div key={p.id_piece} className="flex items-center justify-between px-4 py-3">
            <div className="flex items-center gap-3">
              <FileText size={18} className="text-slate-400" />
              <div>
                <p className="text-sm font-medium text-navy-900">{p.nom}</p>
                <p className="text-xs text-slate-400">Déposé le {p.date_depot}</p>
              </div>
            </div>

            <div className="flex items-center gap-3">
              {p.valide ? (
                <span className="flex items-center gap-1 text-xs text-success-700 font-medium">
                  <Check size={14} />
                  Validée
                </span>
              ) : (
                <>
                  <button
                    onClick={() => handleValider(p.id_piece)}
                    className="text-xs text-navy-900 font-medium hover:underline"
                  >
                    Valider
                  </button>
                  <button
                    onClick={() => handleSupprimer(p.id_piece)}
                    className="text-slate-400 hover:text-danger-700"
                    title="Supprimer (possible uniquement avant validation)"
                  >
                    <Trash2 size={15} />
                  </button>
                </>
              )}
            </div>
          </div>
        ))}

        {pieces.length === 0 && (
          <p className="px-4 py-8 text-center text-sm text-slate-400">
            Aucune pièce déposée pour ce dossier.
          </p>
        )}
      </div>
    </div>
  )
}
