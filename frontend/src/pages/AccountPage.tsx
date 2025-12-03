import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../auth/AuthContext'
import api from '../api/client'
import type { Provider } from '../types'
import { getErrorMessage } from '../utils/errors'

const fetchMyProvider = async (): Promise<Provider> => {
  const { data } = await api.get<Provider>('/providers/me')
  return data
}

export const AccountPage = () => {
  const { claims } = useAuth()
  const roles = claims?.roles ?? []
  const isProvider = roles.includes('R_PROVIDER')

  const {
    data: provider,
    isLoading,
    error,
  } = useQuery({
    queryKey: ['account', 'provider'],
    queryFn: fetchMyProvider,
    enabled: isProvider,
  })

  const email = claims?.email ?? (claims?.username as string | undefined) ?? 'Unknown'

  return (
    <div className="space-y-6">
      <div className="panel space-y-2">
        <h1 className="page-title">My account</h1>
        <p className="text-sm text-slate-500">Personal details for your login.</p>
      </div>

      <div className="grid gap-6 md:grid-cols-2">
        <div className="panel space-y-2">
          <h2 className="text-base font-semibold text-slate-900">Profile</h2>
          <dl className="space-y-3 text-sm text-slate-600">
            <div>
              <dt className="font-medium text-slate-500">Email</dt>
              <dd className="text-slate-900">{email}</dd>
            </div>
            <div>
              <dt className="font-medium text-slate-500">Roles</dt>
              <dd className="text-slate-900">{roles.join(', ') || 'R_CONSUMER'}</dd>
            </div>
          </dl>
        </div>

        {isProvider && (
          <div className="panel space-y-2">
            <h2 className="text-base font-semibold text-slate-900">Provider</h2>
            {isLoading && <p className="text-sm text-slate-500">Loading provider details…</p>}
            {error && (
              <p className="text-sm text-red-600">
                {getErrorMessage(error, 'Your account is not linked to a provider.')}
              </p>
            )}
            {provider && (
              <dl className="space-y-3 text-sm text-slate-600">
                <div>
                  <dt className="font-medium text-slate-500">Name</dt>
                  <dd className="text-slate-900">{provider.name}</dd>
                </div>
                <div>
                  <dt className="font-medium text-slate-500">Provider ID</dt>
                  <dd className="text-slate-900">#{provider.id}</dd>
                </div>
              </dl>
            )}
          </div>
        )}
      </div>
    </div>
  )
}

