import { useEffect, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { Loader2, ShieldCheck, ShieldAlert, ShieldX, Search } from 'lucide-react'
import { verifierDocument } from '../../services/api/verification'
import { TYPE_AUDIENCE_LABELS } from '../../constants/enums'

function Ligne({ libelle, valeur }) {
  if (!valeur) return null
  return (
    <div className="flex justify-between gap-4 px-4 py-2.5 text-sm">
      <span className="text-slate-400">{libelle}</span>
      <span className="text-navy-900 text-right">{valeur}</span>
    </div>
  )
}

// Page ouverte en scannant le QR code d'une décision ou d'un extrait de
// casier : indique si la référence correspond à un document délivré par la
// plateforme et, pour une décision, si l'empreinte imprimée est toujours valide.
export default function Verification() {
  const { reference } = useParams()
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const [resultat, setResultat] = useState(null)
  const [chargement, setChargement] = useState(Boolean(reference))
  const [saisie, setSaisie] = useState(reference ?? '')

  useEffect(() => {
    if (!reference) return
    setChargement(true)
    verifierDocument(reference, params.get('e'))
      .then(setResultat)
      .catch((e) => setResultat(e.response?.data ?? { authentique: false, message: 'Vérification impossible pour le moment.' }))
      .finally(() => setChargement(false))
  }, [reference, params])

  function rechercher(e) {
    e.preventDefault()
    if (saisie.trim()) navigate(`/verification/${saisie.trim().toUpperCase()}`)
  }

  const falsifie = resultat?.authentique && resultat.empreinte_conforme === false
  const date = resultat?.date && new Date(resultat.date).toLocaleDateString('fr-FR', { dateStyle: 'long' })

  return (
    <div className="min-h-screen bg-paper px-4 py-10">
      <div className="w-full max-w-md mx-auto">
        <div className="text-center mb-6">
          <p className="text-sm text-slate-600 tracking-wide">République du Sénégal</p>
          <h1 className="font-display text-2xl text-navy-900 mt-1">Vérification d'un document</h1>
          <p className="text-xs text-slate-400 mt-1">Audience+ · prototype académique</p>
        </div>

        <form onSubmit={rechercher} className="flex gap-2 mb-6">
          <input
            value={saisie}
            onChange={(e) => setSaisie(e.target.value)}
            placeholder="Référence (ex. AJ-DEC-2026-000011)"
            className="flex-1 border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
          />
          <button type="submit" className="bg-navy-900 text-white rounded px-3 hover:bg-navy-800" aria-label="Vérifier">
            <Search size={16} />
          </button>
        </form>

        {chargement ? (
          <div className="flex justify-center py-8 text-slate-400">
            <Loader2 size={20} className="animate-spin" />
          </div>
        ) : resultat && (
          <div className="bg-white border border-slate-200 rounded-md overflow-hidden">
            <div
              className={`flex items-center gap-3 px-4 py-4 ${
                !resultat.authentique ? 'bg-danger-100 text-danger-700' : falsifie ? 'bg-gold-100 text-gold-600' : 'bg-success-100 text-success-700'
              }`}
            >
              <span className="shrink-0">{!resultat.authentique ? <ShieldX size={26} /> : falsifie ? <ShieldAlert size={26} /> : <ShieldCheck size={26} />}</span>
              <div>
                <p className="font-medium">
                  {!resultat.authentique
                    ? 'Document inconnu'
                    : falsifie
                      ? 'Document modifié depuis son impression'
                      : 'Document authentique'}
                </p>
                <p className="text-xs opacity-80">
                  {!resultat.authentique
                    ? resultat.message
                    : falsifie
                      ? "La décision enregistrée ne correspond plus à l'empreinte imprimée : demandez une version à jour au greffe."
                      : 'Ce document a bien été délivré par la plateforme Audience+.'}
                </p>
              </div>
            </div>

            {resultat.authentique && (
              <div className="divide-y divide-slate-100">
                <Ligne libelle="Document" valeur={resultat.document} />
                <Ligne libelle="Référence" valeur={resultat.reference} />
                <Ligne libelle="Dossier" valeur={resultat.dossier} />
                <Ligne libelle="Procédure" valeur={TYPE_AUDIENCE_LABELS[resultat.procedure] ?? resultat.procedure} />
                <Ligne libelle="Tribunal" valeur={resultat.tribunal} />
                <Ligne libelle="Titulaire" valeur={resultat.titulaire} />
                <Ligne libelle="Résultat" valeur={resultat.resultat} />
                <Ligne libelle={resultat.type === 'CASIER' ? 'Demandé le' : 'Audience du'} valeur={date} />
                <Ligne libelle="Empreinte" valeur={resultat.empreinte} />
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
