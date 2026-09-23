import { useEffect, useRef, useState } from 'react'

const JITSI_DOMAIN = 'meet.jit.si'

function loadJitsiScript() {
  return new Promise((resolve, reject) => {
    if (window.JitsiMeetExternalAPI) {
      resolve()
      return
    }
    const script = document.createElement('script')
    script.src = `https://${JITSI_DOMAIN}/external_api.js`
    script.async = true
    script.onload = resolve
    script.onerror = reject
    document.body.appendChild(script)
  })
}

// roomName doit être unique par audience et suffisamment peu devinable,
// vu que meet.jit.si est un serveur public — à remplacer par un serveur
// Jitsi auto-hébergé en prod si besoin d'un vrai contrôle d'accès.
//
// moderator=true (juge, premier arrivé dans la salle) active le "lobby"
// natif de Jitsi : tout participant suivant doit "frapper" et attendre
// d'être admis explicitement - c'est le vrai mécanisme d'admission,
// distinct du champ `admis` stocké en base (qui sert de journal/trace,
// pas de barrière d'accès réelle).
export default function JitsiMeetRoom({ roomName, displayName, moderator = false }) {
  const containerRef = useRef(null)
  const apiRef = useRef(null)
  const [status, setStatus] = useState('loading')

  useEffect(() => {
    let cancelled = false

    loadJitsiScript()
      .then(() => {
        if (cancelled || !containerRef.current) return
        const api = new window.JitsiMeetExternalAPI(JITSI_DOMAIN, {
          roomName: `aj-${roomName}`,
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

        if (moderator) {
          // Le lobby ne peut être activé qu'une fois la conférence rejointe
          // (le juge doit être reconnu comme modérateur côté Jitsi).
          api.addEventListener('videoConferenceJoined', () => {
            api.executeCommand('toggleLobby', true)
          })
        }

        setStatus('ready')
      })
      .catch(() => setStatus('error'))

    return () => {
      cancelled = true
      apiRef.current?.dispose()
    }
  }, [roomName, displayName, moderator])

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
