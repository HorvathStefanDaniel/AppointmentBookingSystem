import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import api from '../api/client'
import type { Provider } from '../types'

const fetchProviders = async (): Promise<Provider[]> => {
  const { data } = await api.get<Provider[]>('/providers')
  return data
}

export const ProvidersPage = () => {
  const { data, isLoading, error } = useQuery({
    queryKey: ['providers'],
    queryFn: fetchProviders,
  })
  const [search, setSearch] = useState('')

  const providers = (data ?? []).filter((provider) =>
    provider.name.toLowerCase().includes(search.toLowerCase())
  )

  return (
    <div className="space-y-6">
      <div className="panel flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p className="subtle-text">Find the perfect marina</p>
          <h1 className="page-title">Discover providers</h1>
        </div>
        <div className="w-full sm:w-80">
          <div className="relative">
            <input
              type="search"
              className="input-field pl-10"
              placeholder="Search providers"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
            <svg
              className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
          </div>
        </div>
      </div>

      {isLoading && <div className="panel text-slate-600">Loading providers…</div>}
      {error && (
        <div className="panel border-red-200 bg-red-50 text-sm text-red-700">
          We couldn't load providers. Please try again.
        </div>
      )}

      <div className="grid gap-6 md:grid-cols-2">
        {providers.map((provider) => (
          <Link
            key={provider.id}
            to={`/providers/${provider.id}`}
            className="card transition hover:-translate-y-1 hover:shadow-2xl"
          >
            <div className="flex items-center justify-between">
              <h2 className="text-xl font-semibold text-slate-900">{provider.name}</h2>
              <span className="tag">Explore</span>
            </div>
            <p className="mt-3 text-sm text-slate-500">
              View services, weekly availability, and upcoming bookings.
            </p>
          </Link>
        ))}
      </div>

      {!isLoading && providers.length === 0 && (
        <div className="panel text-center text-slate-500">
          No providers match your search. Try a different keyword or add a new provider.
        </div>
      )}
    </div>
  )
}

