import apiClient from './client'

export async function listerJuges() {
  const { data } = await apiClient.get('/juges')
  return data
}

export async function listerUtilisateurs() {
  const { data } = await apiClient.get('/utilisateurs')
  return data
}

export async function creerUtilisateur(payload) {
  const { data } = await apiClient.post('/utilisateurs', payload)
  return data
}

export async function updateUtilisateur(id, payload) {
  const { data } = await apiClient.patch(`/utilisateurs/${id}`, payload)
  return data
}

export async function supprimerUtilisateur(id) {
  await apiClient.delete(`/utilisateurs/${id}`)
}

export async function listerGreffiers() {
  const { data } = await apiClient.get('/greffiers')
  return data
}

export async function listerProcureurs() {
  const { data } = await apiClient.get('/procureurs')
  return data
}

export async function verifierIdentiteUtilisateur(id) {
  const { data } = await apiClient.post(`/utilisateurs/${id}/verifier-identite`)
  return data
}

export async function listerMesClients() {
  const { data } = await apiClient.get('/mes-clients')
  return data
}

export async function listerMesAvocats() {
  const { data } = await apiClient.get('/mes-avocats')
  return data
}

export async function listerJusticiables() {
  const { data } = await apiClient.get('/justiciables')
  return data
}

export async function listerAvocats() {
  const { data } = await apiClient.get('/avocats')
  return data
}

