import apiClient from './client'

export async function envoyerOtp(idAudience) {
  const { data } = await apiClient.post(`/audiences/${idAudience}/verification-identite/otp/envoyer`)
  return data
}

export async function verifierOtp(idAudience, code) {
  const { data } = await apiClient.post(`/audiences/${idAudience}/verification-identite/otp/verifier`, { code })
  return data
}
