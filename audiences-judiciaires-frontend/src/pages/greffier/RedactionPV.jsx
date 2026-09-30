import { useEffect, useRef, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { ArrowLeft, Send, FileDown, Loader2, AlertTriangle, AudioLines, Mic, Square } from 'lucide-react'
import { getAudienceById } from '../../services/api/audiences'
import { getPV, enregistrerBrouillon, transmettrePV, transcrireAudio } from '../../services/api/procesVerbaux'
import { StatutAudience, StatutPV } from '../../constants/enums'
import PVStatusBadge from '../../components/ui/PVStatusBadge'

export default function RedactionPV() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [audience, setAudience] = useState(null)
  const [contenu, setContenu] = useState('')
  const [statut, setStatut] = useState(StatutPV.EN_COURS)
  const [commentaireRejet, setCommentaireRejet] = useState(null)
  const [chargement, setChargement] = useState(true)
  const [enCours, setEnCours] = useState(false)
  const [erreur, setErreur] = useState('')
  // Transcription automatique de l'enregistrement de l'audience (Whisper).
  const [audio, setAudio] = useState(null)
  const [transcription, setTranscription] = useState('')
  const [messageTranscription, setMessageTranscription] = useState('')
  const [transcriptionEnCours, setTranscriptionEnCours] = useState(false)
  // Enregistrement direct du micro du greffier pendant l'audience.
  const [enregistrement, setEnregistrement] = useState(false)
  const [secondes, setSecondes] = useState(0)
  const enregistreurRef = useRef(null)
  const morceauxRef = useRef([])

  useEffect(() => {
    Promise.all([getAudienceById(id), getPV(id)])
      .then(([audienceData, pv]) => {
        setAudience(audienceData)
        if (pv) {
          setContenu(pv.contenu ?? '')
          setStatut(pv.statut)
          setCommentaireRejet(pv.commentaire_rejet ?? null)
          setTranscription(pv.transcription_brute ?? '')
        }
      })
      .catch(() => setErreur('Impossible de charger cette audience.'))
      .finally(() => setChargement(false))
  }, [id])

  async function handleSauvegarder() {
    setEnCours(true)
    try {
      await enregistrerBrouillon(id, contenu)
    } catch {
      setErreur("Impossible d'enregistrer le brouillon.")
    } finally {
      setEnCours(false)
    }
  }

  async function handleTransmettre() {
    setEnCours(true)
    try {
      await enregistrerBrouillon(id, contenu)
      const pv = await transmettrePV(id)
      setStatut(pv.statut)
      setCommentaireRejet(null)
    } catch {
      setErreur('Impossible de transmettre le PV.')
    } finally {
      setEnCours(false)
    }
  }

  // Le texte transcrit n'est jamais copié d'office dans le PV : le greffier le
  // relit, l'insère puis le corrige (la transcription reste un brouillon).
  async function handleTranscrire(fichier = audio) {
    if (!fichier) return
    setErreur('')
    setMessageTranscription('')
    setTranscriptionEnCours(true)
    try {
      const resultat = await transcrireAudio(id, fichier)
      if (resultat.transcription_disponible) {
        setTranscription(resultat.pv.transcription_brute ?? '')
      } else {
        setMessageTranscription(resultat.message)
      }
      setAudio(null)
    } catch (err) {
      setErreur(
        err.response?.data?.errors
          ? Object.values(err.response.data.errors).flat().join(' ')
          : err.response?.data?.message || "Impossible d'envoyer l'enregistrement.",
      )
    } finally {
      setTranscriptionEnCours(false)
    }
  }

  // Le navigateur enregistre le micro (MediaRecorder, format webm/opus) ; à
  // l'arrêt, le fichier part directement à la transcription.
  async function demarrerEnregistrement() {
    setErreur('')
    if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
      setErreur("Ce navigateur ne permet pas d'enregistrer ici (une connexion sécurisée est nécessaire) : utilisez un fichier audio.")
      return
    }
    try {
      const flux = await navigator.mediaDevices.getUserMedia({ audio: true })
      const enregistreur = new MediaRecorder(flux)
      morceauxRef.current = []
      enregistreur.ondataavailable = (e) => e.data.size > 0 && morceauxRef.current.push(e.data)
      enregistreur.onstop = () => {
        flux.getTracks().forEach((piste) => piste.stop())
        const type = enregistreur.mimeType || 'audio/webm'
        const extension = type.includes('ogg') ? 'ogg' : type.includes('mp4') ? 'm4a' : 'webm'
        const fichier = new File(morceauxRef.current, `enregistrement-audience-${id}.${extension}`, { type })
        handleTranscrire(fichier)
      }
      enregistreur.start(1000)
      enregistreurRef.current = enregistreur
      setSecondes(0)
      setEnregistrement(true)
    } catch {
      setErreur("Accès au micro refusé : autorisez le micro dans le navigateur, ou envoyez un fichier audio.")
    }
  }

  function arreterEnregistrement() {
    enregistreurRef.current?.stop()
    enregistreurRef.current = null
    setEnregistrement(false)
  }

  // Chronomètre pendant l'enregistrement.
  useEffect(() => {
    if (!enregistrement) return
    const timer = setInterval(() => setSecondes((s) => s + 1), 1000)
    return () => clearInterval(timer)
  }, [enregistrement])

  // Quitter la page arrête le micro sans envoyer l'enregistrement.
  useEffect(() => () => {
    const enregistreur = enregistreurRef.current
    if (enregistreur) {
      enregistreur.onstop = () => enregistreur.stream.getTracks().forEach((piste) => piste.stop())
      enregistreur.stop()
    }
  }, [])

  function insererTranscription() {
    setContenu((c) => (c.trim() ? `${c}\n\n${transcription}` : transcription))
  }

  // Texte brut (.txt) : le PV validé figure aussi, mis en page, en annexe de la
  // décision PDF générée par le serveur.
  function handleExporter() {
    const blob = new Blob(
      [`PROCÈS-VERBAL\n\n${audience.dossier?.parties ?? ''}\n${audience.dossier?.numero ?? ''}\n\n${contenu}`],
      { type: 'text/plain;charset=utf-8' },
    )
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `PV_audience_${id}.txt`
    a.click()
    URL.revokeObjectURL(url)
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
        <p className="text-sm text-slate-600">Audience introuvable.</p>
        <Link to="/greffier/dossiers" className="text-sm text-navy-900 underline">
          Retour aux dossiers
        </Link>
      </div>
    )
  }

  const modifiable = statut === StatutPV.EN_COURS
  // L'enregistrement en direct n'a de sens que pendant l'audience (présentiel,
  // hybride ou en ligne) ; un fichier peut être envoyé à tout moment.
  const audienceEnCours = audience.statut === StatutAudience.EN_COURS

  return (
    <div>
      <button
        onClick={() => navigate(-1)}
        className="flex items-center gap-1 text-sm text-slate-600 hover:text-navy-900 mb-4"
      >
        <ArrowLeft size={14} />
        Retour
      </button>

      <div className="flex items-center justify-between mb-1">
        <h1 className="font-display text-2xl text-navy-900">Procès-verbal</h1>
        <PVStatusBadge statut={statut} />
      </div>
      <p className="text-sm text-slate-600 mb-6">{audience.dossier?.parties}</p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mb-4">{erreur}</p>}

      {commentaireRejet && (
        <div className="flex items-start gap-2 bg-gold-100 text-navy-900 text-sm rounded-md px-4 py-3 mb-4">
          <AlertTriangle size={16} className="text-gold-600 mt-0.5 shrink-0" />
          <div>
            <p className="font-medium mb-0.5">Le juge a renvoyé ce PV pour correction :</p>
            <p>{commentaireRejet}</p>
          </div>
        </div>
      )}

      {modifiable && (
        <div className="bg-white border border-slate-200 rounded-md p-4 mb-4">
          <div className="flex items-center gap-2 mb-2">
            <AudioLines size={16} className="text-navy-700" />
            <h2 className="text-sm font-medium text-navy-900">Transcription de l'enregistrement</h2>
          </div>
          <p className="text-xs text-slate-400 mb-3">
            Pendant l'audience, enregistrez les débats avec le micro de cet ordinateur ; vous pouvez aussi envoyer un
            fichier audio (mp3, wav, m4a, ogg, webm ou mp4, 25 Mo maximum). Le texte est transcrit automatiquement pour
            servir de base au procès-verbal.
          </p>
          {!audienceEnCours && !enregistrement && (
            <p className="text-xs text-slate-500 mb-3">
              L'enregistrement en direct est disponible quand le juge a ouvert l'audience.
            </p>
          )}
          {(audienceEnCours || enregistrement) && (
          <div className="flex flex-wrap items-center gap-3 mb-3">
            {enregistrement ? (
              <button
                onClick={arreterEnregistrement}
                className="flex items-center gap-2 bg-danger-700 text-white text-sm font-medium rounded px-4 py-2 hover:opacity-90 transition-opacity"
              >
                <Square size={14} />
                Arrêter et transcrire
              </button>
            ) : (
              <button
                onClick={demarrerEnregistrement}
                disabled={transcriptionEnCours}
                className="flex items-center gap-2 bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 disabled:opacity-40 transition-colors"
              >
                <Mic size={15} />
                Enregistrer l'audience
              </button>
            )}
            {enregistrement && (
              <span className="flex items-center gap-2 text-sm text-danger-700">
                <span className="w-2 h-2 rounded-full bg-danger-700 animate-pulse" />
                Enregistrement en cours · {String(Math.floor(secondes / 60)).padStart(2, '0')}:
                {String(secondes % 60).padStart(2, '0')}
              </span>
            )}
          </div>
          )}
          <div className="flex flex-wrap items-center gap-3">
            <input
              type="file"
              accept="audio/*,.mp3,.wav,.m4a,.ogg,.webm,.mp4"
              onChange={(e) => setAudio(e.target.files?.[0] ?? null)}
              disabled={enregistrement}
              className="text-sm text-slate-600 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm"
            />
            <button
              onClick={() => handleTranscrire()}
              disabled={!audio || transcriptionEnCours || enregistrement}
              className="flex items-center gap-2 border border-slate-200 text-navy-900 text-sm font-medium rounded px-4 py-2 hover:bg-navy-50 disabled:opacity-40 transition-colors"
            >
              {transcriptionEnCours && <Loader2 size={15} className="animate-spin" />}
              {transcriptionEnCours ? 'Transcription...' : 'Transcrire le fichier'}
            </button>
          </div>
          {messageTranscription && (
            <p className="bg-gold-100 text-navy-900 text-sm rounded px-3 py-2 mt-3">{messageTranscription}</p>
          )}
          {transcription && (
            <div className="mt-3">
              <p className="text-xs text-slate-400 mb-1">Texte transcrit (à relire avant de l'insérer) :</p>
              <p className="text-sm text-slate-600 whitespace-pre-wrap bg-slate-50 rounded p-3 max-h-48 overflow-y-auto">
                {transcription}
              </p>
              <button
                onClick={insererTranscription}
                className="text-sm text-navy-900 font-medium hover:underline mt-2"
              >
                Insérer dans le procès-verbal
              </button>
            </div>
          )}
        </div>
      )}

      <textarea
        value={contenu}
        onChange={(e) => setContenu(e.target.value)}
        disabled={!modifiable}
        rows={14}
        placeholder="Rédigez le procès-verbal de l'audience..."
        className="w-full border border-slate-200 rounded-md p-4 text-sm leading-relaxed focus:outline-none focus:ring-2 focus:ring-navy-700 disabled:bg-slate-50 disabled:text-slate-500"
      />

      <div className="flex items-center gap-3 mt-4">
        {modifiable ? (
          <>
            <button
              onClick={handleSauvegarder}
              disabled={enCours || !contenu.trim()}
              className="border border-slate-200 text-navy-900 text-sm font-medium rounded px-4 py-2 hover:bg-navy-50 disabled:opacity-40 transition-colors"
            >
              Enregistrer le brouillon
            </button>
            <button
              onClick={handleTransmettre}
              disabled={enCours || !contenu.trim()}
              className="flex items-center gap-2 bg-navy-900 text-white text-sm font-medium rounded px-4 py-2 hover:bg-navy-800 disabled:opacity-40 transition-colors"
            >
              <Send size={15} />
              {commentaireRejet ? 'Retransmettre au juge' : 'Transmettre au juge pour validation'}
            </button>
          </>
        ) : (
          <button
            onClick={handleExporter}
            className="flex items-center gap-2 border border-slate-200 text-navy-900 text-sm font-medium rounded px-4 py-2 hover:bg-navy-50 transition-colors"
          >
            <FileDown size={15} />
            Exporter le texte
          </button>
        )}

        {statut === StatutPV.EN_VALIDATION && (
          <p className="text-xs text-slate-400">En attente de validation par le juge.</p>
        )}
      </div>
    </div>
  )
}
