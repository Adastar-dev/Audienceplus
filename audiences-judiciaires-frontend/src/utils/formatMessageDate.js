// Formate l'horodatage d'un message avec la date complète (jour/mois/année
// à HH:mm), toujours, pour qu'on sache exactement quand le message a été
// envoyé sans avoir à deviner à partir d'un "Hier" ou d'une heure seule.
export function formatMessageDate(dateIso) {
  const date = new Date(dateIso)

  const jourMois = date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
  const heure = date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })

  return `${jourMois} à ${heure}`
}
