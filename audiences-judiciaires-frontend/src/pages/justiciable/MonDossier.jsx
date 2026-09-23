import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Upload, FileText, Loader2 } from 'lucide-react'
import { TYPE_AUDIENCE_LABELS } from '../../constants/enums'
import { listerDossiers, getDossierById } from '../../services/api/dossiers'
import { listerPieces, deposerPiece } from '../../services/api/pieces'
import DossierStatusBadge from '../../components/ui/DossierStatusBadge'
import AudienceStatusBadge from '../../components/ui/AudienceStatusBadge'

// Le dossier est retrouve via le rattachement (parties du dossier) : l'API
// /dossiers ne renvoie que les dossiers auxquels le justiciable est lie, meme
// sans audience ni convocation.
export default function MonDossier() {
  const [dossiers, setDossiers] = useState([])
  const [idSelectionne, setIdSelectionne] = useState(null)
  const [dossier, setDossier] = useState(null)
  const [pieces, setPieces] = useState([])
  const [mesAudiences, setMesAudiences] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    listerDossiers()
      .then((liste) => {
        setDossiers(liste)
        setIdSelectionne(liste[0]?.id_dossier ?? null)
        if (liste.length === 0) setChargement(false)
      })
      .catch(() => {
        setErreur('Impossible de charger votre dossier.')
        setChargement(false)
      })
  }, [])

  useEffect(() => {
    if (!idSelectionne) return
    let annule = false
    setChargement(true)
    Promise.all([getDossierById(idSelectionne), listerPieces(idSelectionne)])
      .then(([d, p]) => {
        if (annule) return
        setDossier(d)
        setPieces(p)
        setMesAudiences(d.audiences ?? [])
        setErreur('')
      })
      .catch(() => !annule && setErreur('Impossible de charger votre dossier.'))
      .finally(() => !annule && setChargement(false))
    return () => {
      annule = true
    }
  }, [idSelectionne])

  async function handleUpload(e) {
    const file = e.target.files?.[0]
    if (!file || !dossier) return
    try {
      const nouvellePiece = await deposerPiece(dossier.id_dossier, file)
      setPieces((list) => [...list, nouvellePiece])
    } catch {
      setErreur('Impossible de déposer cette pièce.')
    }
    e.target.value = ''
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
      <p className="text-sm text-slate-600">
        {erreur || 'Aucun dossier associé à votre compte.'}
      </p>
    )
  }

  return (
    <div>
      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>}

      {dossiers.length > 1 && (
        <div className="flex flex-wrap gap-2 mb-6">
          {dossiers.map((d) => (
            <button
              key={d.id_dossier}
              type="button"
              onClick={() => setIdSelectionne(d.id_dossier)}
              className={`text-xs rounded border px-3 py-1.5 transition-colors ${
                d.id_dossier === idSelectionne
                  ? 'bg-navy-900 text-white border-navy-900'
                  : 'bg-white text-slate-600 border-slate-200 hover:border-navy-700'
              }`}
            >
              {d.numero}
              <span className="ml-1.5 opacity-70">
                {TYPE_AUDIENCE_LABELS[d.type] ?? d.type}
              </span>
            </button>
          ))}
        </div>
      )}

      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="font-display text-2xl text-navy-900">{dossier.numero}</h1>
          <p className="text-sm text-slate-600 mt-1">{dossier.parties}</p>
        </div>
        <DossierStatusBadge statut={dossier.statut} />
      </div>

      <div className="bg-white border border-slate-200 rounded-md p-6 grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-8 text-sm mb-6">
        <div>
          <p className="text-slate-400 text-xs mb-0.5">Type</p>
          <p className="text-navy-900">{TYPE_AUDIENCE_LABELS[dossier.type] ?? dossier.type}</p>
        </div>
        <div>
          <p className="text-slate-400 text-xs mb-0.5">Tribunal</p>
          <p className="text-navy-900">{dossier.tribunal?.nom}</p>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div className="bg-white border border-slate-200 rounded-md p-5">
          <h2 className="font-medium text-navy-900 mb-2">Historique des audiences</h2>
          {mesAudiences.length === 0 ? (
            <p className="text-sm text-slate-400">Aucune audience pour ce dossier.</p>
          ) : (
            <ul className="space-y-2">
              {mesAudiences.map((a) => (
                <li key={a.id_audience} className="flex items-center justify-between text-sm">
                  <span className="text-slate-600">
                    {new Date(a.date_heure).toLocaleDateString('fr-FR')}
                  </span>
                  <AudienceStatusBadge statut={a.statut} />
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="bg-white border border-slate-200 rounded-md p-5">
          <h2 className="font-medium text-navy-900 mb-2">Pièces du dossier</h2>
          <label className="flex items-center justify-center gap-2 border-2 border-dashed border-slate-200 rounded-md py-4 text-xs text-slate-600 cursor-pointer hover:border-navy-700 hover:text-navy-900 transition-colors mb-3">
            <Upload size={14} />
            Déposer une preuve ou un document
            <input type="file" className="hidden" onChange={handleUpload} />
          </label>
          {pieces.length === 0 ? (
            <p className="text-sm text-slate-400">Aucune pièce déposée.</p>
          ) : (
            <ul className="space-y-1.5 text-sm text-slate-600">
              {pieces.map((p) => (
                <li key={p.id_piece} className="flex items-center gap-2">
                  <FileText size={14} className="text-slate-400" />
                  {p.nom}
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>

      <p className="text-xs text-slate-400 mt-6">
        Besoin d'un extrait de casier judiciaire ?{' '}
        <Link to="/justiciable/casier" className="text-navy-900 hover:underline">
          Faire une demande
        </Link>
      </p>
    </div>
  )
}
