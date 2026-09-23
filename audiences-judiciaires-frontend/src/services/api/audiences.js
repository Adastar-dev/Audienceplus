import apiClient from './client'

export async function listerAudiences() {
  const { data } = await apiClient.get('/audiences')
  return data
}

export async function getAudienceById(id) {
  const { data } = await apiClient.get(`/audiences/${id}`)
  return data
}

export async function ouvrirAudience(id) {
  const { data } = await apiClient.post(`/audiences/${id}/ouvrir`)
  return data
}

export async function fermerAudience(id) {
  const { data } = await apiClient.post(`/audiences/${id}/fermer`)
  return data
}

export async function renvoyerAudience(id) {
  const { data } = await apiClient.post(`/audiences/${id}/renvoyer`)
  return data
}

export async function deciderAudience(id, type, motif) {
  const { data } = await apiClient.post(`/audiences/${id}/decider`, { type, motif })
  return data
}

// Enregistre la décision du juge et sort l'audience de l'état "en cours".
// Un jugement est d'abord enregistré (type_decision) puis l'audience fermée :
// c'est la fermeture qui passe le dossier en JUGE et notifie les parties.
export async function cloturerAudience(id, decision) {
  if (decision === 'jugement') {
    await deciderAudience(id, 'JUGEMENT')
    return fermerAudience(id)
  }
  return deciderAudience(id, decision === 'renvoi' ? 'RENVOI' : 'DELIBERE')
}

export async function creerAudience(payload) {
  const { data } = await apiClient.post('/audiences', payload)
  return data
}

export async function getJetonJitsi(id) {
  const { data } = await apiClient.get(`/audiences/${id}/jitsi-jeton`)
  return data
}

export async function signalerJugeConnecte(id) {
  const { data } = await apiClient.post(`/audiences/${id}/juge-connecte`)
  return data
}

// fetch + keepalive plutôt qu'axios : la requête doit partir même quand
// l'onglet est en train de se fermer (événement pagehide).
export function signalerJugeDeconnecte(id) {
  const token = localStorage.getItem('aj_auth_token')
  fetch(`${apiClient.defaults.baseURL}/audiences/${id}/juge-deconnecte`, {
    method: 'POST',
    keepalive: true,
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  }).catch(() => {})
}
