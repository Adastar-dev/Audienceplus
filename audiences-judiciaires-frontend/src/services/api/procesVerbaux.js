import apiClient from './client'

export async function getPV(idAudience) {
  try {
    const { data } = await apiClient.get(`/audiences/${idAudience}/pv`)
    return data
  } catch (err) {
    if (err.response?.status === 404) return null
    throw err
  }
}

export async function enregistrerBrouillon(idAudience, contenu) {
  const { data } = await apiClient.put(`/audiences/${idAudience}/pv`, { contenu })
  return data
}

// Enregistrement audio de l'audience, transcrit par Whisper (API Groq). Si la
// transcription échoue, l'audio est tout de même conservé par le serveur.
export async function transcrireAudio(idAudience, fichier) {
  const formData = new FormData()
  formData.append('audio', fichier)
  const { data } = await apiClient.post(`/audiences/${idAudience}/pv/transcrire`, formData)
  return data
}

export async function transmettrePV(idAudience) {
  const { data } = await apiClient.post(`/audiences/${idAudience}/pv/transmettre`)
  return data
}

export async function validerPV(idAudience) {
  const { data } = await apiClient.post(`/audiences/${idAudience}/pv/valider`)
  return data
}

export async function rejeterPV(idAudience, commentaire) {
  const { data } = await apiClient.post(`/audiences/${idAudience}/pv/rejeter`, { commentaire })
  return data
}

export async function donnerAvisPV(idAudience, avis) {
  const { data } = await apiClient.post(`/audiences/${idAudience}/pv/avis`, { avis })
  return data
}

export async function contesterPV(idAudience, commentaire) {
  const { data } = await apiClient.post(`/audiences/${idAudience}/pv/contester`, { commentaire })
  return data
}
