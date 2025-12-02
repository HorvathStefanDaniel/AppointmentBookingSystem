import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../api/client'
import type { Booking } from '../types'
import { BookingsTable } from '../components/BookingsTable'
import { getErrorMessage } from '../utils/errors'

const fetchMyBookings = async (): Promise<Booking[]> => {
  const { data } = await api.get<Booking[]>('/bookings/me')
  return data
}

export const MyBookingsPage = () => {
  const queryClient = useQueryClient()
  const { data, isLoading, error } = useQuery({
    queryKey: ['bookings', 'me'],
    queryFn: fetchMyBookings,
  })

  const cancelMutation = useMutation({
    mutationFn: async (bookingId: number) => {
      await api.delete(`/bookings/${bookingId}`)
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['bookings', 'me'] }),
  })

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-semibold">My bookings</h1>
      {isLoading && <p>Loading bookings…</p>}
      {error && <p className="text-red-600">{getErrorMessage(error, 'Failed to load bookings.')}</p>}
      {cancelMutation.isError && (
        <p className="text-sm text-red-600">
          {getErrorMessage(cancelMutation.error, 'Could not cancel booking')}
        </p>
      )}
      {data && (
        <BookingsTable
          bookings={data}
          onCancel={(id) => cancelMutation.mutate(id)}
          canCancel={() => true}
        />
      )}
    </div>
  )
}

