import apiClient from './client'

export async function listerDemandes() {
  const { data } = await apiClient.get('/casier-judiciaire')
  return data
}

export async function demanderCasier() {
  const { data } = await apiClient.post('/casier-judiciaire')
  return data
}
