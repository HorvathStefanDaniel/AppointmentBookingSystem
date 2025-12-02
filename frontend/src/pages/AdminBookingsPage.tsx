import { useQuery } from '@tanstack/react-query'
import api from '../api/client'
import type { Booking } from '../types'
import { BookingsTable } from '../components/BookingsTable'
import { getErrorMessage } from '../utils/errors'

const fetchAllBookings = async (): Promise<Booking[]> => {
  const { data } = await api.get<Booking[]>('/bookings')
  return data
}

export const AdminBookingsPage = () => {
  const { data, isLoading, error } = useQuery({
    queryKey: ['bookings', 'admin'],
    queryFn: fetchAllBookings,
  })

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-semibold">All bookings</h1>
      {isLoading && <p>Loading…</p>}
      {error && <p className="text-red-600">{getErrorMessage(error, 'Failed to load bookings.')}</p>}
      {data && <BookingsTable bookings={data} />}
    </div>
  )
}

