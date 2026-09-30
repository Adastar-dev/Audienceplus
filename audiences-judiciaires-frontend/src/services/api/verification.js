import apiClient from './client'

// Vérification publique d'un document (décision, extrait de casier) à partir
// de la référence lue dans son QR code. Aucune connexion requise.
export async function verifierDocument(reference, empreinte) {
  const { data } = await apiClient.get(`/verification/${encodeURIComponent(reference)}`, {
    params: empreinte ? { e: empreinte } : {},
  })
  return data
}
