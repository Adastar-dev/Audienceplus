import { useEffect, useRef, useState } from 'react'

const HOTES_LOCAUX = ['localhost', '127.0.0.1']

// Le backend annonce « localhost:8443 ». Ouverte depuis un autre appareil du
// réseau (téléphone, second PC, via l'adresse IP du PC), la page doit viser
// cette même machine : sur le téléphone, « localhost » serait le téléphone.
function domaineAccessible(domain) {
  const [hote, port] = domain.split(':')
  const hotePage = window.location.hostname
  if (HOTES_LOCAUX.includes(hote) && !HOTES_LOCAUX.includes(hotePage)) {
    return port ? `${hotePage}:${port}` : hotePage
  }
  return domain
}

function loadJitsiScript(domain) {
  return new Promise((resolve, reject) => {
    if (window.JitsiMeetExternalAPI) {
      resolve()
      return
    }
    const script = document.createElement('script')
    script.src = `https://${domain}/external_api.js`
    script.async = true
    script.onload = resolve
    script.onerror = reject
    document.body.appendChild(script)
  })
}

// domain / roomName / jwt viennent de GET /audiences/{id}/jitsi-jeton
// (voir getJetonJitsi). Sur le Jitsi auto-hébergé, le jeton est obligatoire
// et c'est lui seul qui fait du juge le modérateur. Sans jwt (backend non
// configuré), on retombe sur meet.jit.si où le premier arrivé est modérateur.
//
// moderator=true (juge) active le "lobby" natif de Jitsi : tout
// participant suivant doit "frapper" et attendre
// d'être admis explicitement - c'est le vrai mécanisme d'admission,
// distinct du champ `admis` stocké en base (qui sert de journal/trace,
// pas de barrière d'accès réelle).
export default function JitsiMeetRoom({ domain: domaineAnnonce, roomName, jwt, displayName, moderator = false, onJoined, onLeft, onApiReady }) {
  const domain = domaineAccessible(domaineAnnonce)
  const containerRef = useRef(null)
  const apiRef = useRef(null)
  const [status, setStatus] = useState('loading')
  // Refs pour ne pas recreer la conference a chaque rendu du parent.
  const onJoinedRef = useRef(onJoined)
  onJoinedRef.current = onJoined
  const onLeftRef = useRef(onLeft)
  onLeftRef.current = onLeft
  const onApiReadyRef = useRef(onApiReady)
  onApiReadyRef.current = onApiReady

  useEffect(() => {
    let cancelled = false

    loadJitsiScript(domain)
      .then(() => {
        if (cancelled || !containerRef.current) return
        const api = new window.JitsiMeetExternalAPI(domain, {
          roomName,
          ...(jwt ? { jwt } : {}),
          parentNode: containerRef.current,
          width: '100%',
          height: '100%',
          userInfo: { displayName },
          configOverwrite: {
            prejoinPageEnabled: false,
            disableDeepLinking: true,
          },
          interfaceConfigOverwrite: {
            SHOW_JITSI_WATERMARK: false,
            MOBILE_APP_PROMO: false,
          },
        })
        apiRef.current = api

        // L'admission des participants se fait dans l'application (greffier ou
        // juge) avant la délivrance du jeton : pas de salle d'attente Jitsi en plus.
        api.addEventListener('videoConferenceJoined', () => {
          onJoinedRef.current?.()
        })
        // Déclenché quand on raccroche, ou quand le juge termine la conférence
        // pour tout le monde à la clôture de l'audience.
        api.addEventListener('readyToClose', () => onLeftRef.current?.())
        onApiReadyRef.current?.(api)

        setStatus('ready')
      })
      .catch(() => setStatus('error'))

    return () => {
      cancelled = true
      apiRef.current?.dispose()
    }
  }, [domain, roomName, jwt, displayName, moderator])

  if (status === 'error') {
    return (
      <div className="w-full h-full flex items-center justify-center bg-navy-950 text-navy-100 text-sm">
        Impossible de charger la salle virtuelle. Vérifiez la connexion réseau.
      </div>
    )
  }

  return (
    <div className="relative w-full h-full bg-navy-950">
      {status === 'loading' && (
        <div className="absolute inset-0 flex items-center justify-center text-navy-100 text-sm">
          Connexion à la salle virtuelle...
        </div>
      )}
      <div ref={containerRef} className="w-full h-full" />
    </div>
  )
}
