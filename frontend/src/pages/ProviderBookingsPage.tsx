import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../api/client'
import type { Booking } from '../types'
import { BookingsTable } from '../components/BookingsTable'
import { useAuth } from '../auth/AuthContext'
import { getErrorMessage } from '../utils/errors'
import { useToast } from '../components/ToastProvider'

const fetchProviderBookings = async (providerId: number): Promise<Booking[]> => {
  const { data } = await api.get<Booking[]>(`/bookings/providers/${providerId}`)
  return data
}

export const ProviderBookingsPage = () => {
  const { claims } = useAuth()
  const providerId = claims?.providerId
  const queryClient = useQueryClient()
  const { addToast } = useToast()

  const queryKey = ['bookings', 'provider', providerId] as const

  const { data, isLoading, error } = useQuery({
    queryKey,
    queryFn: () => fetchProviderBookings(providerId!),
    enabled: typeof providerId === 'number',
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
      queryClient.invalidateQueries({ queryKey })
    },
  })

  const cancellingIds = cancelMutation.variables ? [cancelMutation.variables] : []

  if (!providerId) {
    return <p>You are not assigned to a provider.</p>
  }

  const handleCancel = (booking: Booking) => {
    if (!window.confirm('Are you sure you want to cancel this booking?')) {
      return
    }
    cancelMutation.mutate(booking.id)
  }

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-semibold">Provider bookings</h1>
      {isLoading && <p>Loading…</p>}
      {error && <p className="text-red-600">{getErrorMessage(error, 'Failed to load bookings.')}</p>}
      {data && (
        <BookingsTable
          bookings={data}
          onCancel={(id) => {
            const booking = data.find((b) => b.id === id)
            if (booking) {
              handleCancel(booking)
            }
          }}
          canCancel={(booking) =>
            booking.status === 'active' &&
            (booking.user.id === claims?.userId || Boolean(claims?.roles?.includes('R_ADMIN')))
          }
          cancellingIds={cancellingIds}
        />
      )}
    </div>
  )
}

