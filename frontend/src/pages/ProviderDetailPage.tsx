import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../api/client'
import type { Provider, Service, Slot } from '../types'
import { getErrorMessage } from '../utils/errors'
import { useToast } from '../components/ToastProvider'
import { ConfirmDialog } from '../components/ConfirmDialog'
import { useAuth } from '../auth/AuthContext'

const fetchProviders = async (): Promise<Provider[]> => {
  const { data } = await api.get<Provider[]>('/providers')
  return data
}

const fetchMyProviders = async (): Promise<Provider[]> => {
  const { data } = await api.get<Provider>('/providers/me')
  return [data]
}

const fetchServices = async (): Promise<Service[]> => {
  const { data } = await api.get<Service[]>('/services')
  return data
}

const fetchSlots = async (providerId: string, serviceId: number, from: string, to: string) => {
  const { data } = await api.get<{ slots: Slot[] }>(`/providers/${providerId}/slots`, {
    params: { serviceId, from, to },
  })
  return data.slots
}

export const ProviderDetailPage = () => {
  const { providerId = '' } = useParams()
  const navigate = useNavigate()
  const numericProviderId = Number(providerId)
  const queryClient = useQueryClient()
  const { addToast } = useToast()
  const { claims } = useAuth()
  const roles = claims?.roles ?? []
  const isProviderUser = roles.includes('R_PROVIDER')
  const [confirmSlot, setConfirmSlot] = useState<Slot | null>(null)
  const [reservedSlotStart, setReservedSlotStart] = useState<string | null>(null)
  const [bookingInProgress, setBookingInProgress] = useState(false)
  const [creatingHoldFor, setCreatingHoldFor] = useState<string | null>(null)
  const [activeHold, setActiveHold] = useState<{ id: number; slotStart: string } | null>(null)
  const [selectedService, setSelectedService] = useState<number | null>(null)
  const [from, setFrom] = useState(() => new Date().toISOString().slice(0, 10))
  const [to, setTo] = useState(() => {
    const future = new Date()
    future.setDate(future.getDate() + 7)
    return future.toISOString().slice(0, 10)
  })

  const reservedSlotRef = useRef<string | null>(null)

  useEffect(() => {
    if (new Date(to) < new Date(from)) {
      setTo(from)
    }
  }, [from, to])

  useEffect(() => {
    reservedSlotRef.current = reservedSlotStart
  }, [reservedSlotStart])

  const { data: providers, error: providersError, isLoading: providersLoading } = useQuery({
    queryKey: ['providers', isProviderUser ? 'self' : 'all'],
    queryFn: () => (isProviderUser ? fetchMyProviders() : fetchProviders()),
  })

  useEffect(() => {
    if (!isProviderUser || !providers || providers.length === 0) {
      return
    }
    const myProvider = providers[0]
    if (!Number.isNaN(numericProviderId) && myProvider.id !== numericProviderId) {
      navigate(`/providers/${myProvider.id}`, { replace: true })
    }
  }, [isProviderUser, providers, numericProviderId, navigate])

  const provider = providers?.find((p) => p.id === numericProviderId)
  const invalidProvider = Number.isNaN(numericProviderId)

  useEffect(() => {
    if (!isProviderUser || !providers || providers.length === 0) {
      return
    }
    const myProvider = providers[0]
    if (!Number.isNaN(numericProviderId) && myProvider.id !== numericProviderId) {
      navigate(`/providers/${myProvider.id}`, { replace: true })
    }
  }, [isProviderUser, providers, numericProviderId, navigate])

  if (isProviderUser && providersError) {
    return (
      <div className="panel space-y-3 border-red-200 bg-red-50 text-sm text-red-700">
        <h1 className="text-base font-semibold text-red-800">Provider access issue</h1>
        <p>{getErrorMessage(providersError, 'Your account is not linked to a provider.')}</p>
        <p>Please ask an administrator to attach your account to the correct provider.</p>
      </div>
    )
  }

  if (invalidProvider) {
    return <p className="text-red-600">Invalid provider.</p>
  }

  if (!providersLoading && providers && !provider) {
    return <p className="text-red-600">Provider not found.</p>
  }

  const { data: services, isLoading: servicesLoading } = useQuery({
    queryKey: ['services'],
    queryFn: fetchServices,
  })

  const slotsQueryKey = ['slots', providerId, selectedService, from, to] as const

  const { data: slots, isFetching: slotsLoading } = useQuery({
    queryKey: slotsQueryKey,
    queryFn: () => fetchSlots(providerId, selectedService!, from, to),
    enabled: Boolean(providerId && selectedService && from && to),
    staleTime: 0,
    placeholderData: (previousData) => previousData,
    refetchOnWindowFocus: true,
  })

  const releaseReservation = (restoreAvailability = true) => {
    if (!reservedSlotStart) return
    if (restoreAvailability) {
      queryClient.setQueryData<Slot[]>(slotsQueryKey, (old = []) =>
        old.map((slot) =>
          slot.start === reservedSlotStart
            ? { ...slot, available: true, optimistic: false, reservedByMe: false }
            : slot
        )
      )
    } else {
      queryClient.setQueryData<Slot[]>(slotsQueryKey, (old = []) =>
        old.map((slot) =>
          slot.start === reservedSlotStart
            ? { ...slot, reservedByMe: false }
            : slot
        )
      )
    }
    setReservedSlotStart(null)
    setConfirmSlot(null)
  }

  const clearHold = async (callApi = true) => {
    if (!activeHold) {
      return
    }
    if (!callApi) {
      setActiveHold(null)
      return
    }
    try {
      await api.delete(`/bookings/holds/${activeHold.id}`)
    } catch {
      // ignore cleanup failures
    } finally {
      setActiveHold(null)
    }
  }

  const bookSlotMutation = useMutation({
    mutationFn: async ({ slotStart, holdId }: { slotStart: string; holdId?: number | null }) =>
      api.post('/bookings', {
        serviceId: selectedService,
        providerId: numericProviderId,
        startDateTime: slotStart,
        holdId,
      }),
    onMutate: async ({ slotStart }) => {
      await queryClient.cancelQueries({ queryKey: slotsQueryKey })
      const previousSlots = queryClient.getQueryData<Slot[]>(slotsQueryKey)
      queryClient.setQueryData<Slot[]>(slotsQueryKey, (old = []) =>
        old.map((slot) =>
          slot.start === slotStart
            ? { ...slot, available: false, optimistic: true, reservedByMe: true }
            : slot
        )
      )
      return { previousSlots, slotStart }
    },
    onError: (error, variables, context) => {
      if (context?.previousSlots) {
        queryClient.setQueryData(slotsQueryKey, context.previousSlots)
      }
      clearHold()
      if (variables.slotStart === reservedSlotStart) {
        releaseReservation()
      }
      addToast(getErrorMessage(error, 'Could not create booking'))
    },
    onSuccess: () => {
      addToast('Booking created')
      queryClient.invalidateQueries({ queryKey: ['bookings', 'me'] })
    },
    onSettled: async () => {
      setBookingInProgress(false)
      await queryClient.invalidateQueries({ queryKey: slotsQueryKey })
    },
  })

  const serviceOptions = useMemo(() => services ?? [], [services])
  const confirmSlotStart = confirmSlot?.start ?? null
  const holdPendingForConfirm = Boolean(confirmSlotStart && creatingHoldFor === confirmSlotStart)
  const confirmDisabled =
    bookingInProgress ||
    !confirmSlotStart ||
    holdPendingForConfirm ||
    !activeHold ||
    activeHold.slotStart !== confirmSlotStart

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">{provider?.name ?? 'Provider'}</h1>
        <p className="text-sm text-slate-500">
          Browse available services and reserve a slot with this provider.
        </p>
      </div>

      {servicesLoading && <p>Loading services…</p>}
      {serviceOptions.length > 0 ? (
        <div className="grid gap-4 sm:grid-cols-2">
          {serviceOptions.map((service) => (
            <div key={service.id} className="rounded border bg-white p-4 shadow">
                <div className="flex items-center justify-between gap-4">
                  <div>
                    <h3 className="text-lg font-semibold">{service.name}</h3>
                    <p className="text-sm text-slate-500">{service.durationMinutes} minutes</p>
                  </div>
                  <button
                    className="rounded border px-3 py-1 text-sm"
                    onClick={() => setSelectedService(service.id)}
                  >
                    View slots
                  </button>
                </div>
            </div>
          ))}
        </div>
      ) : (
        !servicesLoading && <p>No services yet.</p>
      )}

      {selectedService && (
        <div className="rounded border bg-white p-4 shadow">
          <h2 className="mb-4 text-lg font-semibold">Available slots</h2>
          <div className="mb-4 flex flex-col gap-3 sm:flex-row">
            <label className="text-sm font-medium">
              From
              <input
                type="date"
                className="ml-2 rounded border px-2 py-1"
                value={from}
                onChange={(e) => setFrom(e.target.value)}
              />
            </label>
            <label className="text-sm font-medium">
              To
              <input
                type="date"
                className="ml-2 rounded border px-2 py-1"
                value={to}
                min={from}
                onChange={(e) => setTo(e.target.value)}
              />
            </label>
          </div>
          {slotsLoading && <p>Loading slots…</p>}
          {!slotsLoading && slots && slots.length === 0 && <p>No slots found.</p>}
          <div className="grid gap-3 sm:grid-cols-2">
            {slots?.map((slot) => {
              const disabled =
                !slot.available || slot.optimistic || bookSlotMutation.isPending || creatingHoldFor === slot.start
              return (
                <div
                  key={slot.start}
                  className={`flex items-center justify-between rounded border p-3 ${
                    slot.available && !slot.optimistic
                      ? 'bg-white'
                      : 'bg-slate-100 text-slate-400'
                  }`}
                >
                  <div>
                    <p className="font-medium">{new Date(slot.start).toLocaleString()}</p>
                    <p className="text-sm text-slate-500">
                      Ends {new Date(slot.end).toLocaleTimeString()}
                    </p>
                    {!slot.available && (
                      <p className="text-xs text-red-500">
                        {slot.reservedByMe
                          ? 'Reserved for you'
                          : slot.optimistic
                            ? 'Booking…'
                            : 'Booked'}
                      </p>
                    )}
                  </div>
                  <button
                    className="rounded bg-slate-900 px-3 py-1 text-white disabled:opacity-50"
                    onClick={async () => {
                      if (!selectedService) {
                        addToast('Select a service first')
                        return
                      }
                      if (creatingHoldFor) {
                        return
                      }
                      if (activeHold) {
                        await clearHold()
                        releaseReservation()
                      }

                      const slotStart = slot.start
                      setConfirmSlot(slot)
                      setReservedSlotStart(slotStart)
                      queryClient.setQueryData<Slot[]>(slotsQueryKey, (old = []) =>
                        old.map((s) =>
                          s.start === slotStart
                            ? { ...s, available: false, optimistic: true, reservedByMe: true }
                            : s
                        )
                      )

                      setCreatingHoldFor(slotStart)
                      try {
                        const response = await api.post('/bookings/holds', {
                          providerId: numericProviderId,
                          serviceId: selectedService,
                          startDateTime: slotStart,
                        })
                        if (reservedSlotRef.current === slotStart) {
                          setActiveHold({ id: response.data.id, slotStart })
                        } else {
                          await api.delete(`/bookings/holds/${response.data.id}`)
                        }
                      } catch (error) {
                        addToast(getErrorMessage(error, 'Could not reserve this slot'))
                        releaseReservation()
                        setConfirmSlot(null)
                        await queryClient.invalidateQueries({ queryKey: slotsQueryKey })
                      } finally {
                        setCreatingHoldFor(null)
                      }
                    }}
                    disabled={disabled}
                  >
                    Book
                  </button>
                </div>
              )
            })}
          </div>
        </div>
      )}
      <ConfirmDialog
        open={Boolean(confirmSlot)}
        title="Confirm booking"
        message={
          confirmSlot ? (
            <>
              Book slot starting{' '}
              <strong>{new Date(confirmSlot.start).toLocaleString()}</strong>?
              {holdPendingForConfirm && (
                <p className="mt-2 text-sm text-slate-500">Reserving slot…</p>
              )}
            </>
          ) : null
        }
        confirmLabel={bookingInProgress ? 'Booking…' : 'Yes, book'}
        cancelLabel="No, keep browsing"
        confirmDisabled={confirmDisabled}
        onCancel={async () => {
          await clearHold()
          releaseReservation()
          setBookingInProgress(false)
        }}
        onConfirm={async () => {
          if (!confirmSlot || !activeHold) {
            addToast('Reservation expired. Please select the slot again.')
            releaseReservation()
            return
          }
          try {
            setBookingInProgress(true)
            await bookSlotMutation.mutateAsync({
              slotStart: confirmSlot.start,
              holdId: activeHold.id,
            })
            releaseReservation(false)
            await clearHold(false)
          } finally {
            setBookingInProgress(false)
          }
        }}
      />
    </div>
  )
}

