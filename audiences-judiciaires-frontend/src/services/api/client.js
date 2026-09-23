import axios from 'axios'

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api',
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
