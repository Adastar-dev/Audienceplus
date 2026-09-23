import apiClient from './client'

export async function listerTribunaux() {
  const { data } = await apiClient.get('/tribunaux')
  return data
}

export async function creerTribunal(payload) {
  const { data } = await apiClient.post('/tribunaux', payload)
  return data
}
