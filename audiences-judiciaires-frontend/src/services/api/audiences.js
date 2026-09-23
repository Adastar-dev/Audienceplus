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

export async function creerAudience(payload) {
  const { data } = await apiClient.post('/audiences', payload)
  return data
}
