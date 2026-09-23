import { useEffect, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { ArrowLeft, FileText, Archive, Check, Loader2 } from 'lucide-react'
import { getDossierById, assignerProcureurDossier } from '../../services/api/dossiers'
import { listerProcureurs } from '../../services/api/utilisateurs'
import { StatutDossier, TYPE_AUDIENCE_LABELS } from '../../constants/enums'
import DossierStatusBadge from '../../components/ui/DossierStatusBadge'
import AudienceStatusBadge from '../../components/ui/AudienceStatusBadge'

export default function DossierDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [dossier, setDossier] = useState(null)
  const [procureurs, setProcureurs] = useState([])
  const [enAssignation, setEnAssignation] = useState(false)
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')
  const [archive, setArchive] = useState(false)

  useEffect(() => {
    Promise.all([getDossierById(id), listerProcureurs()])
      .then(([d, p]) => {
        setDossier(d)
        setProcureurs(p)
      })
      .catch(() => setErreur('Dossier introuvable ou serveur inaccessible.'))
      .finally(() => setChargement(false))
  }, [id])

  async function handleAssignerProcureur(idProcureur) {
    setEnAssignation(true)
    try {
      const misAJour = await assignerProcureurDossier(id, idProcureur)
      setDossier((d) => ({ ...d, ...misAJour }))
    } catch {
      setErreur("Impossible d'assigner ce procureur.")
    } finally {
      setEnAssignation(false)
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

  if (erreur || !dossier) {
    return (
      <div>
        <p className="text-sm text-slate-600">{erreur || 'Dossier introuvable.'}</p>
        <Link to="/greffier/dossiers" className="text-sm text-navy-900 underline">
          Retour à la liste
        </Link>
      </div>
    )
  }

  return (
    <div>
      <button
        onClick={() => navigate('/greffier/dossiers')}
        className="flex items-center gap-1 text-sm text-slate-600 hover:text-navy-900 mb-4"
      >
        <ArrowLeft size={14} />
        Retour aux dossiers
      </button>

      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="font-display text-2xl text-navy-900">{dossier.numero}</h1>
          <p className="text-sm text-slate-600 mt-1">{dossier.parties}</p>
        </div>
        <DossierStatusBadge statut={dossier.statut} />
      </div>

      <div className="bg-white border border-slate-200 rounded-md p-6 grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-8 text-sm mb-4">
        <Field label="Type" value={TYPE_AUDIENCE_LABELS[dossier.type] ?? dossier.type} />
        <Field label="Tribunal" value={dossier.tribunal?.nom ?? '—'} />
        <Field
          label="Date de création"
          value={new Date(dossier.date_creation).toLocaleDateString('fr-FR')}
        />
        <Field label="Statut" value={<DossierStatusBadge statut={dossier.statut} />} />
      </div>

      <div className="bg-white border border-slate-200 rounded-md p-5 mb-4">
        <h2 className="font-medium text-navy-900 mb-1">Procureur assigné</h2>
        <p className="text-xs text-slate-400 mb-3">
          Procureur par défaut pour l'avis sur ce dossier. N'importe quel procureur peut tout de
          même consulter le dossier et donner un avis si besoin.
        </p>
        <select
          value={dossier.id_procureur ?? ''}
          onChange={(e) => handleAssignerProcureur(e.target.value || null)}
          disabled={enAssignation}
          className="w-full sm:w-72 border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700 disabled:opacity-60"
        >
          <option value="">— Aucun procureur assigné —</option>
          {procureurs.map((p) => (
            <option key={p.id_utilisateur} value={p.id_utilisateur}>
              {p.nom}
            </option>
          ))}
        </select>
      </div>

      {dossier.avis_procureur && (
        <div className="bg-white border border-slate-200 rounded-md p-5 mb-4">
          <h2 className="font-medium text-navy-900 mb-2">Avis du procureur</h2>
          <p className="text-sm text-slate-600 whitespace-pre-wrap">{dossier.avis_procureur}</p>
          <p className="text-xs text-slate-400 mt-2">
            {dossier.procureur_qui_a_donne_avis?.nom}
            {dossier.avis_procureur_date &&
              ` — ${new Date(dossier.avis_procureur_date).toLocaleDateString('fr-FR')}`}
          </p>
        </div>
      )}

      {dossier.statut === StatutDossier.CLOTURE && (
        <div className="bg-white border border-slate-200 rounded-md p-5 mb-4 flex items-center justify-between">
          <div>
            <h2 className="font-medium text-navy-900 mb-1">Archivage</h2>
            <p className="text-sm text-slate-400">
              {archive ? 'Dossier archivé.' : 'Ce dossier est clôturé et peut être archivé.'}
            </p>
          </div>
          {archive ? (
            <span className="flex items-center gap-1 text-sm text-success-700 font-medium">
              <Check size={15} /> Archivé
            </span>
          ) : (
            <button
              onClick={() => setArchive(true)}
              className="flex items-center gap-2 border border-slate-200 text-navy-900 text-sm font-medium rounded px-4 py-2 hover:bg-navy-50 transition-colors"
            >
              <Archive size={15} />
              Archiver le dossier
            </button>
          )}
        </div>
      )}

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Link
          to={`/greffier/dossiers/${id}/pieces`}
          className="bg-white border border-slate-200 rounded-md p-5 hover:border-navy-700 transition-colors"
        >
          <h2 className="font-medium text-navy-900 mb-2">Pièces déposées</h2>
          {(dossier.pieces ?? []).length === 0 ? (
            <p className="text-sm text-slate-400">Aucune pièce pour le moment.</p>
          ) : (
            <ul className="space-y-1.5">
              {dossier.pieces.slice(0, 3).map((p) => (
                <li key={p.id_piece} className="flex items-center gap-2 text-sm text-slate-600">
                  <FileText size={14} className="text-slate-400" />
                  {p.nom}
                </li>
              ))}
            </ul>
          )}
          <p className="text-xs text-navy-700 mt-3">Gérer les pièces →</p>
        </Link>

        <div className="bg-white border border-slate-200 rounded-md p-5">
          <div className="flex items-center justify-between mb-2">
            <h2 className="font-medium text-navy-900">Audiences</h2>
            <Link to="/greffier/audiences/nouvelle" className="text-xs text-navy-700 hover:underline">
              + Programmer
            </Link>
          </div>
          {(dossier.audiences ?? []).length === 0 ? (
            <p className="text-sm text-slate-400">Aucune audience programmée.</p>
          ) : (
            <ul className="space-y-2">
              {dossier.audiences.map((a) => (
                <li key={a.id_audience} className="flex items-center justify-between text-sm">
                  <span className="text-slate-600">
                    {new Date(a.date_heure).toLocaleDateString('fr-FR')}
                  </span>
                  <div className="flex items-center gap-2">
                    <AudienceStatusBadge statut={a.statut} />
                    <Link to={`/greffier/audiences/${a.id_audience}/emargement`} className="text-xs text-navy-700 hover:underline">
                      Émargement
                    </Link>
                    <Link to={`/greffier/audiences/${a.id_audience}/pv`} className="text-xs text-navy-700 hover:underline">
                      PV
                    </Link>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </div>
  )
}

function Field({ label, value }) {
  return (
    <div>
      <p className="text-slate-400 text-xs mb-0.5">{label}</p>
      <p className="text-navy-900">{value}</p>
    </div>
  )
}
