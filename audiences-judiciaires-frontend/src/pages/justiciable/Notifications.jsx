import { useEffect, useState } from 'react'
import { Bell, Check, Loader2 } from 'lucide-react'
import { listerNotifications, accuserReceptionNotification } from '../../services/api/notifications'

export default function Notifications() {
  const [notifications, setNotifications] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    listerNotifications()
      .then(setNotifications)
      .catch(() => setErreur('Impossible de charger les notifications.'))
      .finally(() => setChargement(false))
  }, [])

  async function accuserReception(id) {
    try {
      const updated = await accuserReceptionNotification(id)
      setNotifications((list) => list.map((n) => (n.id_notification === id ? updated : n)))
    } catch {
      setErreur("Impossible d'accuser réception.")
    }
  }

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Notifications</h1>
      <p className="text-sm text-slate-600 mt-1">Convocations, rappels et décisions.</p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      {chargement ? (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
          <Loader2 size={16} className="animate-spin" />
          Chargement...
        </div>
      ) : (
        <div className="mt-6 bg-white border border-slate-200 rounded-md divide-y divide-slate-100">
          {notifications.map((n) => (
            <div key={n.id_notification} className="flex items-start justify-between px-4 py-3.5">
              <div className="flex items-start gap-3">
                <Bell size={16} className={n.lu ? 'text-slate-300 mt-0.5' : 'text-gold-600 mt-0.5'} />
                <div>
                  <p className="text-sm font-medium text-navy-900">{n.type}</p>
                  <p className="text-sm text-slate-600">{n.message}</p>
                  <p className="text-xs text-slate-400 mt-1">
                    {new Date(n.date_envoi).toLocaleString('fr-FR')}
                  </p>
                </div>
              </div>
              {n.lu ? (
                <span className="flex items-center gap-1 text-xs text-success-700">
                  <Check size={13} /> Reçu
                </span>
              ) : (
                <button
                  onClick={() => accuserReception(n.id_notification)}
                  className="text-xs text-navy-900 font-medium hover:underline whitespace-nowrap"
                >
                  J'ai reçu
                </button>
              )}
            </div>
          ))}
          {notifications.length === 0 && (
            <p className="px-4 py-8 text-center text-sm text-slate-400">Aucune notification.</p>
          )}
        </div>
      )}
    </div>
  )
}
