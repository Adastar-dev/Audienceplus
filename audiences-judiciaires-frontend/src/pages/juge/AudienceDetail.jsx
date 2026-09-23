import { useEffect, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { ArrowLeft, Gavel, FileText, Loader2, Scale } from 'lucide-react'
import {
  getAudienceById,
  ouvrirAudience,
  fermerAudience,
  renvoyerAudience,
} from '../../services/api/audiences'
import { getPV } from '../../services/api/procesVerbaux'
import { updateStatutDossier } from '../../services/api/dossiers'
import { StatutAudience, StatutPV } from '../../constants/enums'
import AudienceStatusBadge from '../../components/ui/AudienceStatusBadge'
import PVStatusBadge from '../../components/ui/PVStatusBadge'

export default function AudienceDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [audience, setAudience] = useState(null)
  const [pv, setPv] = useState(null)
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')
  const [showDecision, setShowDecision] = useState(false)
  const [decision, setDecision] = useState(null)

  useEffect(() => {
    Promise.all([getAudienceById(id), getPV(id)])
      .then(([audienceData, pvData]) => {
        setAudience(audienceData)
        setPv(pvData)
      })
      .catch(() => setErreur('Audience introuvable.'))
      .finally(() => setChargement(false))
  }, [id])

  async function handleOuvrir() {
    try {
      const updated = await ouvrirAudience(id)
      setAudience((a) => ({ ...a, statut: updated.statut }))
    } catch {
      setErreur("Impossible d'ouvrir l'audience.")
    }
  }

  // "Mise en délibéré" n'a pas de statut dédié dans le schéma actuel
  // (StatutAudience: PROGRAMMEE/EN_COURS/CLOTUREE/RENVOYEE). On ferme
  // l'audience comme pour un jugement rendu, sans changer le statut du
  // dossier - approximation à revoir si un vrai statut "en délibéré"
  // est ajouté au modèle plus tard.
  async function enregistrerDecision(type) {
    try {
      if (type === 'renvoi') {
        const updated = await renvoyerAudience(id)
        setAudience((a) => ({ ...a, statut: updated.statut }))
      } else if (type === 'jugement') {
        const updated = await fermerAudience(id)
        await updateStatutDossier(audience.id_dossier, 'JUGE')
        setAudience((a) => ({ ...a, statut: updated.statut }))
      } else if (type === 'delibere') {
        const updated = await fermerAudience(id)
        setAudience((a) => ({ ...a, statut: updated.statut }))
      }
      setDecision(type)
      setShowDecision(false)
    } catch {
      setErreur("Impossible d'enregistrer la décision.")
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

  if (!audience) {
    return (
      <div>
        <p className="text-sm text-slate-600">{erreur || 'Audience introuvable.'}</p>
        <Link to="/juge/audiences" className="text-sm text-navy-900 underline">
          Retour au calendrier
        </Link>
      </div>
    )
  }

  const estOuvrable = audience.statut === StatutAudience.PROGRAMMEE
  const pieces = audience.dossier?.pieces ?? []

  return (
    <div>
      <button
        onClick={() => navigate('/juge/audiences')}
        className="flex items-center gap-1 text-sm text-slate-600 hover:text-navy-900 mb-4"
      >
        <ArrowLeft size={14} />
        Retour au calendrier
      </button>

      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="font-display text-2xl text-navy-900">{audience.dossier?.parties}</h1>
          <p className="text-sm text-slate-600 mt-1">{audience.dossier?.numero}</p>
        </div>
        <AudienceStatusBadge statut={audience.statut} />
      </div>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>}

      <div className="bg-white border border-slate-200 rounded-md p-6 mb-6">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-8 text-sm mb-6">
          <div>
            <p className="text-slate-400 text-xs mb-0.5">Date et heure</p>
            <p className="text-navy-900">
              {new Date(audience.date_heure).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' })}
            </p>
          </div>
          <div>
            <p className="text-slate-400 text-xs mb-0.5">Juge</p>
            <p className="text-navy-900">{audience.juge?.nom ?? '—'}</p>
          </div>
        </div>

        {estOuvrable ? (
          <button
            onClick={handleOuvrir}
            className="flex items-center gap-2 bg-gold-600 text-white text-sm font-medium rounded px-4 py-2 hover:opacity-90 transition-opacity"
          >
            <Gavel size={16} />
            Ouvrir l'audience
          </button>
        ) : audience.statut === StatutAudience.EN_COURS ? (
          <div className="flex items-center gap-3">
            <span className="text-sm text-success-700 font-medium">Audience en cours</span>
            <button
              onClick={() => navigate(`/juge/audiences/${id}/salle`)}
              className="bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 transition-colors"
            >
              Rejoindre la salle virtuelle
            </button>
          </div>
        ) : (
          <p className="text-sm text-slate-400">Cette audience n'est plus modifiable.</p>
        )}
      </div>

      <div className="bg-white border border-slate-200 rounded-md p-5 mb-6">
        <h2 className="font-medium text-navy-900 mb-3">Décision</h2>
        {decision ? (
          <p className="text-sm text-success-700 font-medium capitalize">
            Décision enregistrée :{' '}
            {decision === 'renvoi' ? 'renvoi' : decision === 'delibere' ? 'mise en délibéré' : 'jugement rendu'}
          </p>
        ) : showDecision ? (
          <div className="flex flex-wrap gap-2">
            <button onClick={() => enregistrerDecision('jugement')} className="border border-slate-200 text-sm rounded px-3 py-1.5 hover:bg-slate-50">
              Jugement rendu
            </button>
            <button onClick={() => enregistrerDecision('renvoi')} className="border border-slate-200 text-sm rounded px-3 py-1.5 hover:bg-slate-50">
              Renvoi
            </button>
            <button onClick={() => enregistrerDecision('delibere')} className="border border-slate-200 text-sm rounded px-3 py-1.5 hover:bg-slate-50">
              Mise en délibéré
            </button>
          </div>
        ) : (
          <button
            onClick={() => setShowDecision(true)}
            disabled={audience.statut !== StatutAudience.EN_COURS}
            className="flex items-center gap-2 border border-slate-200 text-slate-600 text-sm font-medium rounded px-4 py-2 hover:bg-slate-50 disabled:opacity-40 transition-colors"
          >
            <Scale size={15} />
            Décider (jugement / renvoi / mise en délibéré)
          </button>
        )}
      </div>

      {audience.dossier?.avis_procureur && (
        <div className="bg-white border border-slate-200 rounded-md p-5 mb-6">
          <h2 className="font-medium text-navy-900 mb-2">Avis du procureur</h2>
          <p className="text-sm text-slate-600 whitespace-pre-wrap">{audience.dossier.avis_procureur}</p>
          <p className="text-xs text-slate-400 mt-2">
            {audience.dossier.procureur_qui_a_donne_avis?.nom}
            {audience.dossier.avis_procureur_date &&
              ` — ${new Date(audience.dossier.avis_procureur_date).toLocaleDateString('fr-FR')}`}
          </p>
        </div>
      )}

      <div className="bg-white border border-slate-200 rounded-md p-5 mb-6">
        <h2 className="font-medium text-navy-900 mb-2">Pièces déposées</h2>
        {pieces.length === 0 ? (
          <p className="text-sm text-slate-400">Aucune pièce déposée pour ce dossier.</p>
        ) : (
          <ul className="space-y-1.5">
            {pieces.map((p) => (
              <li key={p.id_piece} className="flex items-center gap-2 text-sm text-slate-600">
                <FileText size={14} className="text-slate-400" />
                {p.nom}
              </li>
            ))}
          </ul>
        )}
      </div>

      {pv && (
        <Link
          to={`/juge/audiences/${id}/pv`}
          className="flex items-center justify-between bg-white border border-slate-200 rounded-md p-5 hover:border-navy-700 transition-colors"
        >
          <div>
            <h2 className="font-medium text-navy-900 mb-1">Procès-verbal</h2>
            <p className="text-sm text-slate-400">
              {pv.statut === StatutPV.EN_VALIDATION
                ? 'Transmis par le greffier, en attente de votre validation'
                : 'Consulter le PV de cette audience'}
            </p>
          </div>
          <PVStatusBadge statut={pv.statut} />
        </Link>
      )}
    </div>
  )
}
