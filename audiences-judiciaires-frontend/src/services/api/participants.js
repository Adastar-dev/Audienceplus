import apiClient from './client'

export async function listerParticipants(idAudience) {
  const { data } = await apiClient.get(`/audiences/${idAudience}/participants`)
  return data
}

export async function marquerPresent(idAudience, idParticipation) {
  const { data } = await apiClient.post(
    `/audiences/${idAudience}/participants/${idParticipation}/marquer-present`,
  )
  return data
}

export async function marquerAbsent(idAudience, idParticipation) {
  const { data } = await apiClient.post(
    `/audiences/${idAudience}/participants/${idParticipation}/marquer-absent`,
  )
  return data
}

// Admission dans la salle d'attente virtuelle (distinct de la presence physique).
export async function admettreParticipant(idAudience, idParticipation) {
  const { data } = await apiClient.post(
    `/audiences/${idAudience}/participants/${idParticipation}/admettre`,
  )
  return data
}

export async function refuserParticipant(idAudience, idParticipation) {
  const { data } = await apiClient.post(
    `/audiences/${idAudience}/participants/${idParticipation}/refuser`,
  )
  return data
}
