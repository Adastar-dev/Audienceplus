import apiClient from './client'

export async function listerDemandesDistance() {
  const { data } = await apiClient.get('/demandes-distance')
  return data
}

export async function creerDemandeDistance(idAudience, motif) {
  const { data } = await apiClient.post('/demandes-distance', { id_audience: idAudience, motif })
  return data
}

// Avis du greffier (ne tranche pas la demande, un juge doit encore valider).
export async function donnerAvisDemandeDistance(id, avis, commentaire) {
  const { data } = await apiClient.post(`/demandes-distance/${id}/avis`, { avis, commentaire })
  return data
}

// Décision finale, réservée au juge.
export async function approuverDemandeDistance(id) {
  const { data } = await apiClient.post(`/demandes-distance/${id}/approuver`)
  return data
}

export async function refuserDemandeDistance(id, commentaire) {
  const { data } = await apiClient.post(`/demandes-distance/${id}/refuser`, { commentaire })
  return data
}
