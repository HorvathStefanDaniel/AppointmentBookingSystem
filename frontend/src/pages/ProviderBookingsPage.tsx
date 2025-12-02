import { useQuery } from '@tanstack/react-query'
import api from '../api/client'
import type { Booking } from '../types'
import { BookingsTable } from '../components/BookingsTable'
import { useAuth } from '../auth/AuthContext'
import { getErrorMessage } from '../utils/errors'

const fetchProviderBookings = async (providerId: number): Promise<Booking[]> => {
  const { data } = await api.get<Booking[]>(`/bookings/providers/${providerId}`)
  return data
}

export const ProviderBookingsPage = () => {
  const { claims } = useAuth()
  const providerId = claims?.providerId

  const { data, isLoading, error } = useQuery({
    queryKey: ['bookings', 'provider', providerId],
    queryFn: () => fetchProviderBookings(providerId!),
    enabled: typeof providerId === 'number',
  })

  if (!providerId) {
    return <p>You are not assigned to a provider.</p>
  }

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-semibold">Provider bookings</h1>
      {isLoading && <p>Loading…</p>}
      {error && <p className="text-red-600">{getErrorMessage(error, 'Failed to load bookings.')}</p>}
      {data && <BookingsTable bookings={data} />}
    </div>
  )
}

