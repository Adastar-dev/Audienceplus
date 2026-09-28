import axios from 'axios'

const HOTES_LOCAUX = ['localhost', '127.0.0.1']

// Adresse de l'API. Si elle pointe sur localhost alors que la page est ouverte
// depuis une autre machine (un téléphone sur le même Wi-Fi, via l'adresse IP
// du PC), on vise la même machine que la page : sur le téléphone, « localhost »
// désignerait le téléphone lui-même.
function adresseApi() {
  const configuree = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'
  const hotePage = window.location.hostname

  try {
    const url = new URL(configuree)
    if (HOTES_LOCAUX.includes(url.hostname) && !HOTES_LOCAUX.includes(hotePage)) {
      url.hostname = hotePage
      return url.toString().replace(/\/$/, '')
    }
  } catch {
    // Adresse relative ou invalide : utilisée telle quelle.
  }

  return configuree
}

const apiClient = axios.create({
  baseURL: adresseApi(),
  headers: {
    Accept: 'application/json',
  },
})

// Attache le token Sanctum à chaque requête, s'il existe.
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('aj_auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Une réponse 401 signifie une session expirée côté Laravel : on nettoie et on
// signale l'app via un événement custom, plutôt qu'un window.location.href qui
// forcerait un rechargement complet et perdrait l'état React/SPA.
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('aj_auth_token')
      localStorage.removeItem('aj_auth_user')
      window.dispatchEvent(new CustomEvent('aj:session-expired'))
    }
    return Promise.reject(error)
  },
)

export default apiClient
