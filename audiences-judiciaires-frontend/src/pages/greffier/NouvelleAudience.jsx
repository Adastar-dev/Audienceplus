import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ArrowLeft, Loader2, CalendarClock } from 'lucide-react'
import { listerDossiers } from '../../services/api/dossiers'
import { listerJuges } from '../../services/api/utilisateurs'
import { creerAudience, listerAudiences } from '../../services/api/audiences'
import { StatutAudience } from '../../constants/enums'
import AudienceStatusBadge from '../../components/ui/AudienceStatusBadge'

export default function NouvelleAudience() {
  const navigate = useNavigate()
  const [dossiers, setDossiers] = useState([])
  const [juges, setJuges] = useState([])
  const [audiencesExistantes, setAudiencesExistantes] = useState([])
  const [chargement, setChargement] = useState(true)
  const [enCours, setEnCours] = useState(false)
  const [erreur, setErreur] = useState('')
  const [idDossier, setIdDossier] = useState('')
  const [idJuge, setIdJuge] = useState('')
  const [date, setDate] = useState('')
  const [heure, setHeure] = useState('09:00')

  useEffect(() => {
    Promise.all([listerDossiers(), listerJuges(), listerAudiences()])
      .then(([dossiersData, jugesData, audiencesData]) => {
        setDossiers(dossiersData)
        setJuges(jugesData)
        setAudiencesExistantes(audiencesData)
        if (dossiersData.length > 0) setIdDossier(dossiersData[0].id_dossier)
        if (jugesData.length > 0) setIdJuge(jugesData[0].id_utilisateur)
      })
      .catch(() => setErreur('Impossible de charger les dossiers ou les juges.'))
      .finally(() => setChargement(false))
  }, [])

  const planningJuge = useMemo(() => {
    if (!idJuge) return []
    return audiencesExistantes
      .filter((a) => String(a.id_juge) === String(idJuge) && a.statut !== StatutAudience.CLOTUREE)
      .sort((a, b) => a.date_heure.localeCompare(b.date_heure))
  }, [audiencesExistantes, idJuge])

  const jugeSelectionne = juges.find((j) => String(j.id_utilisateur) === String(idJuge))

  const conflitDetecte =
    date &&
    heure &&
    planningJuge.some((a) => a.date_heure === `${date}T${heure}:00`)

  async function handleSubmit(e) {
    e.preventDefault()
    setErreur('')
    setEnCours(true)
    try {
      const audience = await creerAudience({
        id_dossier: idDossier,
        id_juge: idJuge || null,
        date_heure: `${date} ${heure}:00`,
      })
      navigate(`/greffier/dossiers/${audience.id_dossier ?? idDossier}`)
    } catch {
      setErreur("Impossible de programmer l'audience.")
    } finally {
      setEnCours(false)
    }
  }

  return (
    <div className="max-w-2xl">
      <button
        onClick={() => navigate(-1)}
        className="flex items-center gap-1 text-sm text-slate-600 hover:text-navy-900 mb-4"
      >
        <ArrowLeft size={14} />
        Retour
      </button>

      <h1 className="font-display text-2xl text-navy-900">Programmer une audience</h1>
      <p className="text-sm text-slate-600 mt-1">
        Une convocation sera envoyée automatiquement aux parties.
      </p>

      {chargement ? (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
          <Loader2 size={16} className="animate-spin" />
          Chargement...
        </div>
      ) : (
        <div className="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
          <form onSubmit={handleSubmit} className="bg-white border border-slate-200 rounded-md p-6 space-y-4 h-fit">
            {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2">{erreur}</p>}

            <div>
              <label className="block text-sm font-medium text-slate-600 mb-1.5">Dossier</label>
              <select
                value={idDossier}
                onChange={(e) => setIdDossier(e.target.value)}
                className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
              >
                {dossiers.map((d) => (
                  <option key={d.id_dossier} value={d.id_dossier}>
                    {d.numero} — {d.parties}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-600 mb-1.5">Juge</label>
              <select
                value={idJuge}
                onChange={(e) => setIdJuge(e.target.value)}
                className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
              >
                {juges.map((j) => (
                  <option key={j.id_utilisateur} value={j.id_utilisateur}>
                    {j.nom}
                  </option>
                ))}
              </select>
            </div>

            <p className="text-xs text-slate-400">
              L'audience se tient en présentiel. Un justiciable pourra demander une autorisation d'y assister à distance.
            </p>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-slate-600 mb-1.5">Date</label>
                <input
                  type="date"
                  value={date}
                  onChange={(e) => setDate(e.target.value)}
                  required
                  className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-600 mb-1.5">Heure</label>
                <input
                  type="time"
                  value={heure}
                  onChange={(e) => setHeure(e.target.value)}
                  required
                  className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
                />
              </div>
            </div>

            {conflitDetecte && (
              <p className="bg-gold-100 text-navy-900 text-sm rounded px-3 py-2">
                ⚠ Ce juge a déjà une audience à ce créneau exact.
              </p>
            )}

            <button
              type="submit"
              disabled={enCours}
              className="bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 disabled:opacity-60 transition-colors"
            >
              {enCours ? 'Programmation...' : 'Programmer et convoquer les parties'}
            </button>
          </form>

          <div className="bg-white border border-slate-200 rounded-md p-5 h-fit">
            <div className="flex items-center gap-2 mb-3">
              <CalendarClock size={16} className="text-navy-700" />
              <h2 className="font-medium text-navy-900">
                Planning de {jugeSelectionne?.nom ?? 'ce juge'}
              </h2>
            </div>

            {planningJuge.length === 0 ? (
              <p className="text-sm text-slate-400">Aucune audience programmée pour ce juge.</p>
            ) : (
              <ul className="space-y-2">
                {planningJuge.map((a) => (
                  <li key={a.id_audience} className="flex items-center justify-between text-sm">
                    <div>
                      <p className="text-navy-900">
                        {new Date(a.date_heure).toLocaleString('fr-FR', {
                          dateStyle: 'medium',
                          timeStyle: 'short',
                        })}
                      </p>
                      <p className="text-xs text-slate-400">{a.dossier?.numero}</p>
                    </div>
                    <AudienceStatusBadge statut={a.statut} />
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
