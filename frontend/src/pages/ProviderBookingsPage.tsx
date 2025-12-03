import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import api from '../api/client'
import type { Booking } from '../types'
import { BookingsTable } from '../components/BookingsTable'
import { useAuth } from '../auth/AuthContext'
import { getErrorMessage } from '../utils/errors'
import { useToast } from '../components/ToastProvider'

const fetchProviderBookings = async (): Promise<Booking[]> => {
  const { data } = await api.get<Booking[]>('/bookings/providers/me')
  return data
}

export const ProviderBookingsPage = () => {
  const { claims } = useAuth()
  const roles = claims?.roles ?? []
  const isAdmin = roles.includes('R_ADMIN')
  const isProviderRole = roles.includes('R_PROVIDER')
  const queryClient = useQueryClient()
  const { addToast } = useToast()
  const [pendingCancelIds, setPendingCancelIds] = useState<number[]>([])

  const queryKey = ['bookings', 'provider', 'self'] as const

  const { data, isLoading, error } = useQuery({
    queryKey,
    queryFn: fetchProviderBookings,
    enabled: isProviderRole,
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

  const canCancelBooking = useMemo(() => {
    return (booking: Booking) => {
      if (booking.status !== 'active') {
        return false
      }
      const isOwnBooking = booking.user.id === (claims?.userId ?? null)
      return isAdmin || isOwnBooking || isProviderRole
    }
  }, [claims?.userId, isAdmin, isProviderRole])

  if (!isProviderRole) {
    return (
      <div className="space-y-2">
        <p>You do not have provider permissions.</p>
        <p className="text-sm text-slate-500">Ask an administrator to assign your account the provider role.</p>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-semibold">Provider bookings</h1>
      {isLoading && <p>Loading…</p>}
      {error && <p className="text-red-600">{getErrorMessage(error, 'Failed to load bookings.')}</p>}
      {data && (
        <BookingsTable
          bookings={data}
          onCancel={(id) => cancelMutation.mutate(id)}
          canCancel={canCancelBooking}
          cancellingIds={pendingCancelIds}
        />
      )}
    </div>
  )
}

