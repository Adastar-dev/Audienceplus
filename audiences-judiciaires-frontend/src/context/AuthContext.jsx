import { createContext, useContext, useEffect, useState } from 'react'
import * as authApi from '../services/api/auth'

const AuthContext = createContext(null)

const USER_KEY = 'aj_auth_user'
const TOKEN_KEY = 'aj_auth_token'

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    const storedUser = localStorage.getItem(USER_KEY)
    const storedToken = localStorage.getItem(TOKEN_KEY)
    if (storedUser && storedToken) {
      try {
        setUser(JSON.parse(storedUser))
      } catch {
        localStorage.removeItem(USER_KEY)
        localStorage.removeItem(TOKEN_KEY)
      }
    }
    setIsLoading(false)
  }, [])

  // Le token a déjà été retiré de localStorage par l'intercepteur (client.js) -
  // ici on ne fait que synchroniser l'état React pour éviter un user fantôme en mémoire.
  useEffect(() => {
    function handleSessionExpired() {
      setUser(null)
    }
    window.addEventListener('aj:session-expired', handleSessionExpired)
    return () => window.removeEventListener('aj:session-expired', handleSessionExpired)
  }, [])

  function persistSession({ token, user: userData }) {
    localStorage.setItem(TOKEN_KEY, token)
    localStorage.setItem(USER_KEY, JSON.stringify(userData))
    setUser(userData)
  }

  async function login({ identifiant, motDePasse }) {
    const data = await authApi.login({ identifiant, motDePasse })
    persistSession(data)
    return data.user
  }

  async function register(payload) {
    const data = await authApi.register(payload)
    persistSession(data)
    return data.user
  }

  async function logout() {
    try {
      await authApi.logout()
    } catch {
      // le token est peut-être déjà expiré côté serveur - on nettoie quand même localement
    }
    setUser(null)
    localStorage.removeItem(USER_KEY)
    localStorage.removeItem(TOKEN_KEY)
  }

  const value = {
    user,
    role: user?.role ?? null,
    isAuthenticated: Boolean(user),
    isLoading,
    login,
    register,
    logout,
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('useAuth doit être utilisé à l’intérieur d’un <AuthProvider>')
  }
  return ctx
}
