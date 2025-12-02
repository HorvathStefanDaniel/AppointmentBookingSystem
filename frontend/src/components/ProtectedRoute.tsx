import { Navigate } from 'react-router-dom'
import type { ReactNode } from 'react'
import { useAuth } from '../auth/AuthContext'

type Props = {
  children: ReactNode
  requireRoles?: string[]
}

export const ProtectedRoute = ({ children, requireRoles }: Props) => {
  const { isAuthenticated, claims } = useAuth()

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  if (requireRoles && requireRoles.length > 0) {
    const roles = claims?.roles ?? []
    const hasRole = requireRoles.some((role) => roles.includes(role))
    if (!hasRole) {
      return <Navigate to="/providers" replace />
    }
  }

  return <>{children}</>
}

