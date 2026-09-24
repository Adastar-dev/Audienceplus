import apiClient from './client'

// Photo de la CNI : le backend en lit le numéro par OCR et le compare au
// numéro déclaré, à titre indicatif pour le greffier (jamais bloquant).
export async function envoyerPhotoCni(idAudience, fichier) {
  const formData = new FormData()
  formData.append('cni', fichier)
  const { data } = await apiClient.post(`/audiences/${idAudience}/verification-identite/cni`, formData)
  return data
}

export async function envoyerOtp(idAudience) {
  const { data } = await apiClient.post(`/audiences/${idAudience}/verification-identite/otp/envoyer`)
  return data
}

export async function verifierOtp(idAudience, code) {
  const { data } = await apiClient.post(`/audiences/${idAudience}/verification-identite/otp/verifier`, { code })
  return data
}
