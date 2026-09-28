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

// Envoi multipart : l'inscription contient la photo de la CNI.
export async function register(payload) {
  const formData = new FormData()
  Object.entries(payload).forEach(([cle, valeur]) => {
    if (valeur !== null && valeur !== undefined && valeur !== '') formData.append(cle, valeur)
  })
  const { data } = await apiClient.post('/inscription', formData)
  return data
}

// Photos de la CNI (recto et verso) d'un compte créé par l'administrateur
// (première connexion).
export async function deposerPhotoCni(recto, verso) {
  const formData = new FormData()
  formData.append('cni_photo', recto)
  formData.append('cni_verso', verso)
  const { data } = await apiClient.post('/mon-identite/cni', formData)
  return data
}

// Informations du compte connecté (page « Mon compte »).
export async function getMonCompte() {
  const { data } = await apiClient.get('/utilisateur')
  return data
}

// Complète une information manquante du profil (téléphone, n° de CNI).
export async function completerCompte(champs) {
  const { data } = await apiClient.patch('/mon-compte', champs)
  return data
}

export async function logout() {
  await apiClient.post('/logout')
}
