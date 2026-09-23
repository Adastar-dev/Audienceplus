import apiClient from './client'

// Même interface que services/mock/dossiers.js — le jour où le backend
// est prêt, il suffit de changer l'import dans les pages concernées.

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

export async function updateStatutDossier(id, statut) {
  const { data } = await apiClient.patch(`/dossiers/${id}`, { statut })
  return data
}

export async function assignerProcureurDossier(id, idProcureur) {
  const { data } = await apiClient.patch(`/dossiers/${id}`, { id_procureur: idProcureur || null })
  return data
}

export async function donnerAvisDossier(id, avis) {
  const { data } = await apiClient.patch(`/dossiers/${id}/avis`, { avis })
  return data
}
