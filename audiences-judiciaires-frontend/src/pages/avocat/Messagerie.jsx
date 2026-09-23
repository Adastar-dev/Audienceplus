import { useEffect, useRef, useState } from 'react'
import { Send, Loader2 } from 'lucide-react'
import { listerMesClients, listerGreffiers } from '../../services/api/utilisateurs'
import { listerConversation, envoyerMessage } from '../../services/api/messages'
import { useAuth } from '../../context/AuthContext'
import { formatMessageDate } from '../../utils/formatMessageDate'

// L'avocat échange avec ses vrais clients (justiciables rattachés à un de
// ses dossiers) et librement avec les greffiers pour la coordination
// administrative. On fusionne les deux listes dans un seul sélecteur de
// correspondant, en indiquant le rôle de chacun.
export default function Messagerie() {
  const { user } = useAuth()
  const [contacts, setContacts] = useState([])
  const [idContact, setIdContact] = useState('')
  const [messages, setMessages] = useState([])
  const [texte, setTexte] = useState('')
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')
  const bottomRef = useRef(null)

  useEffect(() => {
    Promise.all([listerMesClients(), listerGreffiers()])
      .then(([clients, greffiers]) => {
        const liste = [
          ...clients.map((c) => ({ ...c, role: 'Client' })),
          ...greffiers.map((g) => ({ ...g, role: 'Greffier' })),
        ]
        setContacts(liste)
        if (liste.length > 0) setIdContact(liste[0].id_utilisateur)
      })
      .catch(() => setErreur('Impossible de charger la liste de vos contacts.'))
      .finally(() => setChargement(false))
  }, [])

  useEffect(() => {
    if (!idContact) return
    let annule = false

    function charger() {
      listerConversation(idContact)
        .then((data) => {
          if (!annule) setMessages(data)
        })
        .catch(() => {})
    }

    charger()
    const intervalle = setInterval(charger, 4000)
    return () => {
      annule = true
      clearInterval(intervalle)
    }
  }, [idContact])

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  async function envoyer() {
    if (!texte.trim() || !idContact) return
    const contenu = texte
    setTexte('')
    try {
      const nouveau = await envoyerMessage(idContact, contenu)
      setMessages((list) => [...list, nouveau])
    } catch {
      setErreur("Impossible d'envoyer le message.")
    }
  }

  const contactSelectionne = contacts.find((c) => String(c.id_utilisateur) === String(idContact))

  if (chargement) {
    return (
      <div className="flex items-center gap-2 text-sm text-slate-400 py-8 justify-center">
        <Loader2 size={16} className="animate-spin" />
        Chargement...
      </div>
    )
  }

  return (
    <div>
      <h1 className="font-display text-2xl text-navy-900">Messagerie sécurisée</h1>
      <p className="text-sm text-slate-600 mt-1">Conversation privée avec un de vos clients ou un greffier.</p>

      {erreur && <p className="bg-danger-100 text-danger-700 text-sm rounded px-3 py-2 mt-4">{erreur}</p>}

      {contacts.length === 0 && !erreur ? (
        <p className="text-sm text-slate-400 mt-8 text-center">
          Aucun contact disponible pour le moment.
        </p>
      ) : (
        <>
          <div className="mt-4 max-w-xs">
            <select
              value={idContact}
              onChange={(e) => setIdContact(e.target.value)}
              className="w-full border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700 bg-white"
            >
              {contacts.map((c) => (
                <option key={`${c.role}-${c.id_utilisateur}`} value={c.id_utilisateur}>
                  {c.nom} ({c.role})
                </option>
              ))}
            </select>
          </div>

          <div className="mt-4 bg-white border border-slate-200 rounded-md flex flex-col h-[420px]">
            <div className="px-4 py-2.5 border-b border-slate-100 text-sm font-medium text-navy-900">
              {contactSelectionne?.nom} {contactSelectionne && `(${contactSelectionne.role})`}
            </div>
            <div className="flex-1 overflow-y-auto p-4 space-y-3">
              {messages.map((m) => {
                const estMoi = m.id_expediteur === user?.id_utilisateur
                return (
                  <div key={m.id_message} className={`flex ${estMoi ? 'justify-end' : 'justify-start'}`}>
                    <div
                      className={`max-w-xs rounded-md px-3 py-2 text-sm ${
                        estMoi ? 'bg-navy-900 text-white' : 'bg-slate-100 text-navy-900'
                      }`}
                    >
                      <p>{m.contenu}</p>
                      <p className={`text-[11px] mt-1 ${estMoi ? 'text-navy-100' : 'text-slate-400'}`}>
                        {formatMessageDate(m.date_envoi)}
                      </p>
                    </div>
                  </div>
                )
              })}
              {messages.length === 0 && (
                <p className="text-sm text-slate-400 text-center mt-8">Aucun message pour le moment.</p>
              )}
              <div ref={bottomRef} />
            </div>
            <div className="flex items-center gap-2 border-t border-slate-100 p-3">
              <input
                value={texte}
                onChange={(e) => setTexte(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && envoyer()}
                placeholder="Écrire un message..."
                className="flex-1 border border-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
              />
              <button onClick={envoyer} className="bg-navy-900 text-white rounded p-2 hover:bg-navy-800 transition-colors">
                <Send size={16} />
              </button>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
