import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { FileText, Video, Loader2 } from 'lucide-react'
import { listerDossiers, donnerAvisDossier } from '../../services/api/dossiers'
import { listerPieces } from '../../services/api/pieces'
import { listerAudiences } from '../../services/api/audiences'
import { getPV, donnerAvisPV } from '../../services/api/procesVerbaux'
import DossierStatusBadge from '../../components/ui/DossierStatusBadge'
import { STATUT_PV_LABELS } from '../../constants/enums'

export default function DossiersProcureur() {
  const [dossiers, setDossiers] = useState([])
  const [audiences, setAudiences] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  const [selection, setSelection] = useState(null)
  const [pieces, setPieces] = useState([])
  const [chargementPieces, setChargementPieces] = useState(false)
  const [avis, setAvis] = useState('')
  const [envoiAvis, setEnvoiAvis] = useState(false)

  const [pv, setPv] = useState(null)
  const [avisPv, setAvisPv] = useState('')
  const [envoiAvisPv, setEnvoiAvisPv] = useState(false)

  useEffect(() => {
    Promise.all([listerDossiers(), listerAudiences()])
      .then(([d, a]) => {
        setDossiers(d)
        setAudiences(a)
      })
      .catch(() => setErreur('Impossible de charger les dossiers.'))
      .finally(() => setChargement(false))
  }, [])

  function selectionner(d) {
    setSelection(d)
    setAvis('')
    setAvisPv('')
    setPv(null)
    setChargementPieces(true)
    listerPieces(d.id_dossier)
      .then(setPieces)
      .catch(() => setPieces([]))
      .finally(() => setChargementPieces(false))

    const audienceDuDossier = audiences.find((a) => a.id_dossier === d.id_dossier)
    if (audienceDuDossier) {
      getPV(audienceDuDossier.id_audience)
        .then(setPv)
        .catch(() => setPv(null))
    }
  }

  const audienceLiee = selection
    ? audiences.find((a) => a.id_dossier === selection.id_dossier)
    : null

  async function envoyerAvisDossier() {
    if (!selection || !avis.trim()) return
    setEnvoiAvis(true)
    try {
      const misAJour = await donnerAvisDossier(selection.id_dossier, avis)
      setSelection(misAJour)
      setDossiers((list) => list.map((d) => (d.id_dossier === misAJour.id_dossier ? misAJour : d)))
      setAvis('')
    } catch {
      setErreur("Impossible d'enregistrer l'avis sur le dossier.")
    } finally {
      setEnvoiAvis(false)
    }
  }

  async function envoyerAvisPv() {
    if (!audienceLiee || !avisPv.trim()) return
    setEnvoiAvisPv(true)
    try {
      const misAJour = await donnerAvisPV(audienceLiee.id_audience, avisPv)
      setPv(misAJour)
      setAvisPv('')
    } catch {
      setErreur("Impossible d'enregistrer l'avis sur le procès-verbal.")
    } finally {
      setEnvoiAvisPv(false)
    }
  }

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Dossiers assignés</h1>
      <p className="text-sm text-slate-600 mt-1">{dossiers.length} dossiers</p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      {chargement ? (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
          <Loader2 size={16} className="animate-spin" />
          Chargement...
        </div>
      ) : (
        <div className="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div className="bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
            {dossiers.map((d) => (
              <button
                key={d.id_dossier}
                onClick={() => selectionner(d)}
                className={`w-full flex items-center justify-between px-4 py-3.5 text-left hover:bg-navy-50 ${selection?.id_dossier === d.id_dossier ? 'bg-navy-50' : ''}`}
              >
                <div>
                  <p className="text-sm font-medium text-navy-900">{d.numero}</p>
                  <p className="text-xs text-slate-400">{d.parties}</p>
                </div>
                <DossierStatusBadge statut={d.statut} />
              </button>
            ))}
          </div>

          <div className="space-y-4">
            {selection ? (
              <>
                {audienceLiee && (
                  <Link
                    to={`/procureur/audiences/${audienceLiee.id_audience}/rejoindre`}
                    className="flex items-center gap-2 bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 transition-colors w-fit"
                  >
                    <Video size={15} />
                    Rejoindre l'audience à distance
                  </Link>
                )}

                <div className="bg-white border border-slate-200 rounded-md p-5">
                  <h2 className="font-medium text-navy-900 mb-2">Pièces du dossier</h2>
                  {chargementPieces ? (
                    <Loader2 size={14} className="animate-spin text-slate-400" />
                  ) : pieces.length === 0 ? (
                    <p className="text-sm text-slate-400">Aucune pièce déposée.</p>
                  ) : (
                    <ul className="space-y-1.5 text-sm text-slate-600">
                      {pieces.map((p) => (
                        <li key={p.id_piece} className="flex items-center gap-2">
                          <FileText size={14} className="text-slate-400" />
                          {p.type_fichier}
                        </li>
                      ))}
                    </ul>
                  )}
                </div>

                <div className="bg-white border border-slate-200 rounded-md p-5">
                  <h2 className="font-medium text-navy-900 mb-3">Donner un avis sur le dossier</h2>
                  <p className="text-xs text-slate-400 mb-3">
                    Rôle consultatif requis pour certains types de dossiers (adoption, rectification d'acte).
                  </p>
                  <textarea
                    value={avis}
                    onChange={(e) => setAvis(e.target.value)}
                    rows={4}
                    placeholder="Avis et observations..."
                    className="w-full border border-slate-200 rounded px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-navy-700"
                  />
                  <button
                    onClick={envoyerAvisDossier}
                    disabled={!avis.trim() || envoiAvis}
                    className="bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 disabled:opacity-40 transition-colors"
                  >
                    {envoiAvis ? 'Envoi...' : "Transmettre l'avis"}
                  </button>
                  {selection.avis_procureur && (
                    <p className="text-xs text-success-700 mt-2">
                      Avis déjà transmis pour ce dossier
                      {selection.avis_procureur_date &&
                        ` le ${new Date(selection.avis_procureur_date).toLocaleDateString('fr-FR')}`}
                      .
                    </p>
                  )}
                </div>

                {audienceLiee && pv && (
                  <div className="bg-white border border-slate-200 rounded-md p-5">
                    <div className="flex items-center justify-between mb-3">
                      <h2 className="font-medium text-navy-900">Donner un avis sur le procès-verbal</h2>
                      <span className="text-xs text-slate-400">{STATUT_PV_LABELS[pv.statut]}</span>
                    </div>
                    <textarea
                      value={avisPv}
                      onChange={(e) => setAvisPv(e.target.value)}
                      rows={4}
                      placeholder="Avis sur le déroulement de l'audience, la qualification retenue..."
                      className="w-full border border-slate-200 rounded px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-navy-700"
                    />
                    <button
                      onClick={envoyerAvisPv}
                      disabled={!avisPv.trim() || envoiAvisPv}
                      className="bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 disabled:opacity-40 transition-colors"
                    >
                      {envoiAvisPv ? 'Envoi...' : "Transmettre l'avis"}
                    </button>
                    {pv.avis_procureur && (
                      <p className="text-xs text-success-700 mt-2">
                        Avis déjà transmis sur ce PV
                        {pv.avis_procureur_date &&
                          ` le ${new Date(pv.avis_procureur_date).toLocaleDateString('fr-FR')}`}
                        .
                      </p>
                    )}
                  </div>
                )}
                {audienceLiee && !pv && (
                  <p className="text-xs text-slate-400 px-1">Aucun procès-verbal rédigé pour l'instant sur ce dossier.</p>
                )}
              </>
            ) : (
              <p className="text-sm text-slate-400">Sélectionnez un dossier à gauche.</p>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
