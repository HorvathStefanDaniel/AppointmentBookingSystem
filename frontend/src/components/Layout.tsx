import { Link, Outlet } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

export const Layout = () => {
  const { isAuthenticated, claims, logout } = useAuth()
  const roles = claims?.roles ?? []

  return (
    <div className="app-shell">
      <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/85 backdrop-blur-md">
        <div className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-4">
          <Link to="/" className="flex items-center gap-2 text-lg font-semibold text-slate-900">
            <span className="text-2xl text-brand">Harba</span>
            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">
              Booking
            </span>
          </Link>
          <nav className="flex flex-wrap items-center gap-4 text-sm font-medium text-slate-600">
            <Link className="transition hover:text-slate-900" to="/providers">
              Providers
            </Link>
            {isAuthenticated && (
              <Link className="transition hover:text-slate-900" to="/bookings/me">
                My bookings
              </Link>
            )}
            {roles.includes('R_PROVIDER') && (
              <Link className="transition hover:text-slate-900" to="/bookings/provider">
                Provider bookings
              </Link>
            )}
            {roles.includes('R_ADMIN') && (
              <Link className="transition hover:text-slate-900" to="/bookings/admin">
                All bookings
              </Link>
            )}
          </nav>
          <div className="flex items-center gap-3">
            {isAuthenticated ? (
              <>
                <span className="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-600">
                  {claims?.email}
                </span>
                <button className="btn-secondary" onClick={logout}>
                  Logout
                </button>
              </>
            ) : (
              <div className="flex gap-3">
                <Link className="btn-secondary" to="/login">
                  Login
                </Link>
                <Link className="btn-primary" to="/register">
                  Get started
                </Link>
              </div>
            )}
          </div>
        </div>
      </header>
      <main className="flex-1">
        <div className="page-section space-y-6">
          <Outlet />
        </div>
      </main>
    </div>
  )
}

