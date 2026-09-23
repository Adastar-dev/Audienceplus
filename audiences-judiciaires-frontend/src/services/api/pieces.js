import apiClient from './client'

export async function listerPieces(idDossier) {
  const { data } = await apiClient.get(`/dossiers/${idDossier}/pieces`)
  return data
}

// Le backend attend un vrai fichier (multipart), pas juste des metadonnees.
export async function deposerPiece(idDossier, fichier) {
  const formData = new FormData()
  formData.append('fichier', fichier)
  const { data } = await apiClient.post(`/dossiers/${idDossier}/pieces`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function validerPiece(idPiece) {
  const { data } = await apiClient.post(`/pieces/${idPiece}/valider`)
  return data
}

export async function supprimerPiece(idPiece) {
  await apiClient.delete(`/pieces/${idPiece}`)
}
