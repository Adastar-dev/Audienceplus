import apiClient from './client'

// Service d'authentification réel, prêt à être branché à la place du login
// mocké de AuthContext une fois l'API Laravel/Sanctum disponible.
// Sanctum SPA nécessite normalement un appel préalable à /sanctum/csrf-cookie
// si on utilise l'authentification par cookie plutôt que par token Bearer.

export async function login({ identifiant, motDePasse }) {
  const { data } = await apiClient.post('/login', {
    identifiant,
    mot_de_passe: motDePasse,
  })
  return data // attendu: { token, user: { id_utilisateur, nom, role, ... } }
}

export async function register(payload) {
  const { data } = await apiClient.post('/inscription', payload)
  return data
}

export async function logout() {
  await apiClient.post('/logout')
}

export async function getUtilisateurConnecte() {
  const { data } = await apiClient.get('/utilisateur')
  return data
}
