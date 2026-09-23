import { useEffect, useState } from 'react'
import { FileDown, Loader2, MessageSquareWarning } from 'lucide-react'
import { listerAudiences } from '../../services/api/audiences'
import { getPV, contesterPV } from '../../services/api/procesVerbaux'
import { StatutPV } from '../../constants/enums'
import AudienceStatusBadge from '../../components/ui/AudienceStatusBadge'

// NOTE : le backend n'a pas (encore) de vrai export PDF cote serveur
// (ProcesVerbal.exporterPDF() du diagramme de classes n'est pas implemente) -
// on genere donc ici un fichier texte simple a partir du contenu reel du PV,
// en attendant un vrai generateur PDF.
export default function HistoriqueAudiences() {
  const [audiences, setAudiences] = useState([])
  const [pvParAudience, setPvParAudience] = useState({})
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  const [contestationOuverte, setContestationOuverte] = useState(null)
  const [commentaire, setCommentaire] = useState('')
  const [envoiContestation, setEnvoiContestation] = useState(false)

  useEffect(() => {
    listerAudiences()
      .then(async (list) => {
        setAudiences(list)
        const entries = await Promise.all(
          list.map(async (a) => [a.id_audience, await getPV(a.id_audience)]),
        )
        setPvParAudience(Object.fromEntries(entries))
      })
      .catch(() => setErreur('Impossible de charger les audiences.'))
      .finally(() => setChargement(false))
  }, [])

  function telecharger(a, pv) {
    const blob = new Blob(
      [`PROCÈS-VERBAL\n\n${a.dossier?.parties}\n${a.dossier?.numero}\n\n${pv.contenu}`],
      { type: 'text/plain' },
    )
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `PV_${a.dossier?.numero}.txt`
    link.click()
    URL.revokeObjectURL(url)
  }

  function ouvrirContestation(idAudience) {
    setContestationOuverte(idAudience)
    setCommentaire('')
  }

  async function envoyerContestation(idAudience) {
    if (!commentaire.trim()) return
    setEnvoiContestation(true)
    try {
      const pvMisAJour = await contesterPV(idAudience, commentaire)
      setPvParAudience((map) => ({ ...map, [idAudience]: pvMisAJour }))
      setContestationOuverte(null)
      setCommentaire('')
    } catch {
      setErreur("Impossible d'envoyer la contestation.")
    } finally {
      setEnvoiContestation(false)
    }
  }

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Historique des audiences</h1>
      <p className="text-sm text-slate-600 mt-1">{audiences.length} audiences suivies</p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      {chargement ? (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
          <Loader2 size={16} className="animate-spin" />
          Chargement...
        </div>
      ) : (
        <div className="mt-6 bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
          {audiences.map((a) => {
            const pv = pvParAudience[a.id_audience]
            return (
              <div key={a.id_audience} className="px-4 py-3.5">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-navy-900">{a.dossier?.parties}</p>
                    <p className="text-xs text-slate-400">
                      {a.dossier?.numero} — {new Date(a.date_heure).toLocaleDateString('fr-FR')}
                    </p>
                  </div>
                  <div className="flex items-center gap-3">
                    <AudienceStatusBadge statut={a.statut} />
                    {(pv?.statut === StatutPV.CLOTURE || pv?.statut === StatutPV.CONTESTE) && (
                      <button
                        onClick={() => telecharger(a, pv)}
                        className="flex items-center gap-1 text-xs text-navy-900 font-medium hover:underline"
                      >
                        <FileDown size={13} />
                        PV
                      </button>
                    )}
                    {pv?.statut === StatutPV.CLOTURE && (
                      <button
                        onClick={() => ouvrirContestation(a.id_audience)}
                        className="flex items-center gap-1 text-xs text-danger-700 font-medium hover:underline"
                      >
                        <MessageSquareWarning size={13} />
                        Contester
                      </button>
                    )}
                    {pv?.statut === StatutPV.CONTESTE && (
                      <span className="text-xs text-danger-700 font-medium">Contesté</span>
                    )}
                  </div>
                </div>

                {pv?.statut === StatutPV.CONTESTE && pv.contestation_avocat && (
                  <p className="text-xs text-slate-500 mt-2 bg-danger-100/50 rounded px-3 py-2">
                    Votre contestation : {pv.contestation_avocat}
                  </p>
                )}

                {contestationOuverte === a.id_audience && (
                  <div className="mt-3 bg-slate-50 border border-slate-200 rounded p-3">
                    <textarea
                      value={commentaire}
                      onChange={(e) => setCommentaire(e.target.value)}
                      rows={3}
                      placeholder="Motif de la contestation du procès-verbal..."
                      className="w-full border border-slate-200 rounded px-3 py-2 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-navy-700"
                    />
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => envoyerContestation(a.id_audience)}
                        disabled={!commentaire.trim() || envoiContestation}
                        className="bg-danger-700 text-white text-xs font-medium rounded px-3 py-1.5 hover:opacity-90 disabled:opacity-40 transition-opacity"
                      >
                        {envoiContestation ? 'Envoi...' : 'Confirmer la contestation'}
                      </button>
                      <button
                        onClick={() => setContestationOuverte(null)}
                        className="text-xs text-slate-500 hover:underline"
                      >
                        Annuler
                      </button>
                    </div>
                  </div>
                )}
              </div>
            )
          })}
          {audiences.length === 0 && (
            <p className="px-4 py-8 text-center text-sm text-slate-400">Aucune audience.</p>
          )}
        </div>
      )}
    </div>
  )
}
