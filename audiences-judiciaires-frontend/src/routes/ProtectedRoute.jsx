import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

/**
 * Protège un groupe de routes.
 * - Si non connecté → redirige vers /connexion
 * - Si `allowedRoles` est fourni et que le rôle de l'utilisateur n'y figure pas
 *   → redirige vers son propre tableau de bord (pas d'accès refusé silencieux)
 */
export default function ProtectedRoute({ allowedRoles }) {
  const { isAuthenticated, isLoading, role } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return null // ou un loader plein écran si besoin
  }

  if (!isAuthenticated) {
    return <Navigate to="/connexion" state={{ from: location }} replace />
  }

  if (allowedRoles && !allowedRoles.includes(role)) {
    return <Navigate to={`/${role.toLowerCase()}`} replace />
  }

  return <Outlet />
}
