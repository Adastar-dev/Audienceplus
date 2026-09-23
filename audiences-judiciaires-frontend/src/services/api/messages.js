import apiClient from './client'

export async function listerConversation(idUtilisateur) {
  const { data } = await apiClient.get('/messages', { params: { avec: idUtilisateur } })
  return data
}

export async function envoyerMessage(idDestinataire, contenu) {
  const { data } = await apiClient.post('/messages', { id_destinataire: idDestinataire, contenu })
  return data
}
