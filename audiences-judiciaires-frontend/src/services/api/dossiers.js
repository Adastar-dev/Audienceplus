import apiClient from './client'

export async function listerDossiers() {
  const { data } = await apiClient.get('/dossiers')
  return data
}

export async function getDossierById(id) {
  const { data } = await apiClient.get(`/dossiers/${id}`)
  return data
}

export async function creerDossier(payload) {
  const { data } = await apiClient.post('/dossiers', payload)
  return data
}

export async function assignerProcureurDossier(id, idProcureur) {
  const { data } = await apiClient.patch(`/dossiers/${id}`, { id_procureur: idProcureur || null })
  return data
}

export async function archiverDossier(id) {
  const { data } = await apiClient.post(`/dossiers/${id}/archiver`)
  return data
}

export async function donnerAvisDossier(id, avis) {
  const { data } = await apiClient.patch(`/dossiers/${id}/avis`, { avis })
  return data
}
