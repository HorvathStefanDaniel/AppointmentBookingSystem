import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../api/client'
import type { Booking } from '../types'
import { BookingsTable } from '../components/BookingsTable'
import { getErrorMessage } from '../utils/errors'
import { useToast } from '../components/ToastProvider'

const fetchMyBookings = async (): Promise<Booking[]> => {
  const { data } = await api.get<Booking[]>('/bookings/me')
  return data
}

export const MyBookingsPage = () => {
  const queryClient = useQueryClient()
  const { addToast } = useToast()
  const { data, isLoading, error } = useQuery({
    queryKey: ['bookings', 'me'],
    queryFn: fetchMyBookings,
  })

  const cancelMutation = useMutation({
    mutationFn: async (bookingId: number) => {
      await api.delete(`/bookings/${bookingId}`)
      return bookingId
    },
    onMutate: async (bookingId: number) => {
      addToast('Cancelling booking…')
      return bookingId
    },
    onError: (mutationError) => {
      addToast(getErrorMessage(mutationError, 'Could not cancel booking'))
    },
    onSuccess: () => {
      addToast('Booking cancelled')
      queryClient.invalidateQueries({ queryKey: ['bookings', 'me'] })
    },
  })

  const cancellingIds = cancelMutation.variables ? [cancelMutation.variables] : []

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-semibold">My bookings</h1>
      {isLoading && <p>Loading bookings…</p>}
      {error && <p className="text-red-600">{getErrorMessage(error, 'Failed to load bookings.')}</p>}
      {data && (
        <BookingsTable
          bookings={data}
          onCancel={(id) => cancelMutation.mutate(id)}
          canCancel={() => true}
          cancellingIds={cancellingIds}
        />
      )}
    </div>
  )
}

