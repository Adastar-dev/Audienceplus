import { useEffect, useState } from 'react'
import { FileCheck2, QrCode, FileDown, Loader2 } from 'lucide-react'
import { listerDemandes, demanderCasier, telechargerCasier } from '../../services/api/casierJudiciaire'
import { ResultatCasier } from '../../constants/enums'
import Badge from '../../components/ui/Badge'

export default function CasierJudiciaire() {
  const [demandes, setDemandes] = useState([])
  const [chargement, setChargement] = useState(true)
  const [enCours, setEnCours] = useState(false)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    listerDemandes()
      .then(setDemandes)
      .catch(() => setErreur('Impossible de charger vos demandes.'))
      .finally(() => setChargement(false))
  }, [])

  async function handleDemander() {
    setEnCours(true)
    try {
      const nouvelle = await demanderCasier()
      setDemandes((list) => [nouvelle, ...list])
    } catch (err) {
      setErreur(err.response?.data?.message || 'Impossible de créer la demande.')
    } finally {
      setEnCours(false)
    }
  }

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Casier judiciaire</h1>
      <p className="text-sm text-slate-600 mt-1">
        Chaque extrait porte une référence unique, reprise dans son QR code.
      </p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      <button
        onClick={handleDemander}
        disabled={enCours}
        className="mt-6 flex items-center gap-2 bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 disabled:opacity-60 transition-colors"
      >
        {enCours ? <Loader2 size={15} className="animate-spin" /> : <FileCheck2 size={15} />}
        {enCours ? 'Traitement en cours...' : 'Demander un extrait de casier judiciaire'}
      </button>

      {chargement ? (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
          <Loader2 size={16} className="animate-spin" />
          Chargement...
        </div>
      ) : (
        <div className="mt-6 space-y-3">
          {demandes.map((d) => (
            <div
              key={d.id_casier}
              className="bg-white border border-slate-200 rounded-md p-5 flex flex-wrap items-center gap-4"
            >
              {d.qr_image && <img src={d.qr_image} alt={`QR code ${d.qr_code}`} className="w-20 h-20" />}
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-navy-900">
                  Demande du {new Date(d.date_demandee).toLocaleDateString('fr-FR')}
                </p>
                <div className="flex items-center gap-2 mt-1.5">
                  <Badge tone={d.resultat === ResultatCasier.VIERGE ? 'success' : 'danger'}>
                    {d.resultat === ResultatCasier.VIERGE ? 'Casier vierge' : 'Casier non vierge'}
                  </Badge>
                  <a
                    href={`/verification/${d.qr_code}`}
                    target="_blank"
                    rel="noreferrer"
                    title="Page publique de vérification, ouverte aussi en scannant le QR code"
                    className="flex items-center gap-1 text-xs text-slate-400 hover:text-navy-900 hover:underline"
                  >
                    <QrCode size={13} />
                    {d.qr_code}
                  </a>
                </div>
              </div>
              <button
                onClick={() => telechargerCasier(d).catch(() => setErreur("Impossible de télécharger l'extrait."))}
                className="flex items-center gap-1.5 text-sm text-navy-900 hover:underline"
              >
                <FileDown size={14} />
                Télécharger le PDF
              </button>
            </div>
          ))}

          {demandes.length === 0 && (
            <p className="text-sm text-slate-400">Aucune demande effectuée pour le moment.</p>
          )}
        </div>
      )}
    </div>
  )
}
