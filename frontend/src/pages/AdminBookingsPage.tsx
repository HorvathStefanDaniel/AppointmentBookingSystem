import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import api from '../api/client'
import type { Booking } from '../types'
import { BookingsTable } from '../components/BookingsTable'
import { getErrorMessage } from '../utils/errors'
import { useToast } from '../components/ToastProvider'

const fetchAllBookings = async (): Promise<Booking[]> => {
  const { data } = await api.get<Booking[]>('/bookings')
  return data
}

export const AdminBookingsPage = () => {
  const queryClient = useQueryClient()
  const { addToast } = useToast()
  const queryKey = ['bookings', 'admin'] as const
  const [pendingCancelIds, setPendingCancelIds] = useState<number[]>([])
  const { data, isLoading, error } = useQuery({
    queryKey,
    queryFn: fetchAllBookings,
    staleTime: 0,
    placeholderData: (previous) => previous,
    refetchOnWindowFocus: true,
  })

  const cancelMutation = useMutation({
    mutationFn: async (bookingId: number) => {
      await api.delete(`/bookings/${bookingId}`)
      return bookingId
    },
    onMutate: async (bookingId: number) => {
      addToast('Cancelling booking…')
      setPendingCancelIds((prev) => [...prev, bookingId])
      await queryClient.cancelQueries({ queryKey })
      const previous = queryClient.getQueryData<Booking[]>(queryKey)
      queryClient.setQueryData<Booking[]>(queryKey, (old = []) =>
        old.map((booking) =>
          booking.id === bookingId ? { ...booking, status: 'cancelled' } : booking
        )
      )
      return { previous, bookingId }
    },
    onError: (mutationError, _id, context) => {
      if (context?.previous) {
        queryClient.setQueryData(queryKey, context.previous)
      }
      if (context?.bookingId) {
        setPendingCancelIds((prev) => prev.filter((id) => id !== context.bookingId))
      }
      addToast(getErrorMessage(mutationError, 'Could not cancel booking'))
    },
    onSuccess: (_data, _variables, context) => {
      addToast('Booking cancelled')
      if (context?.bookingId) {
        setPendingCancelIds((prev) => prev.filter((id) => id !== context.bookingId))
      }
      queryClient.invalidateQueries({ queryKey })
    },
    onSettled: (_data, _error, _variables, context) => {
      if (context?.bookingId) {
        setPendingCancelIds((prev) => prev.filter((id) => id !== context.bookingId))
      }
      queryClient.invalidateQueries({ queryKey })
    },
  })

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-semibold">All bookings</h1>
      {isLoading && <p>Loading…</p>}
      {error && <p className="text-red-600">{getErrorMessage(error, 'Failed to load bookings.')}</p>}
      {data && (
        <BookingsTable
          bookings={data}
          onCancel={(id) => cancelMutation.mutate(id)}
          canCancel={(booking) => booking.status === 'active'}
          cancellingIds={pendingCancelIds}
        />
      )}
    </div>
  )
}

