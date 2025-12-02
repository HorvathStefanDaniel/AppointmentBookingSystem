import { useEffect, useMemo, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../api/client'
import type { Provider, Service, Slot } from '../types'
import { getErrorMessage } from '../utils/errors'

const fetchProviders = async (): Promise<Provider[]> => {
  const { data } = await api.get<Provider[]>('/providers')
  return data
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
  const numericProviderId = Number(providerId)
  const queryClient = useQueryClient()

  const [selectedService, setSelectedService] = useState<number | null>(null)
  const [from, setFrom] = useState(() => new Date().toISOString().slice(0, 10))
  const [to, setTo] = useState(() => {
    const future = new Date()
    future.setDate(future.getDate() + 7)
    return future.toISOString().slice(0, 10)
  })

  useEffect(() => {
    if (new Date(to) < new Date(from)) {
      setTo(from)
    }
  }, [from, to])

  const { data: providers } = useQuery({
    queryKey: ['providers'],
    queryFn: fetchProviders,
  })

  const provider = providers?.find((p) => p.id === numericProviderId)
  const invalidProvider = Number.isNaN(numericProviderId)

  if (invalidProvider) {
    return <p className="text-red-600">Invalid provider.</p>
  }

  if (providers && !provider) {
    return <p className="text-red-600">Provider not found.</p>
  }

  const { data: services, isLoading: servicesLoading } = useQuery({
    queryKey: ['services'],
    queryFn: fetchServices,
  })

  const { data: slots, isFetching: slotsLoading } = useQuery({
    queryKey: ['slots', providerId, selectedService, from, to],
    queryFn: () => fetchSlots(providerId, selectedService!, from, to),
    enabled: Boolean(providerId && selectedService && from && to),
  })

  const bookSlotMutation = useMutation({
    mutationFn: async (slotStart: string) =>
      api.post('/bookings', {
        serviceId: selectedService,
        providerId: numericProviderId,
        startDateTime: slotStart,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings', 'me'] })
      queryClient.invalidateQueries({ queryKey: ['slots', providerId, selectedService, from, to] })
    },
  })

  const serviceOptions = useMemo(() => services ?? [], [services])

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
              const disabled = !slot.available || bookSlotMutation.isPending
              return (
                <div
                  key={slot.start}
                  className={`flex items-center justify-between rounded border p-3 ${
                    slot.available ? 'bg-white' : 'bg-slate-100 text-slate-400'
                  }`}
                >
                  <div>
                    <p className="font-medium">{new Date(slot.start).toLocaleString()}</p>
                    <p className="text-sm text-slate-500">
                      Ends {new Date(slot.end).toLocaleTimeString()}
                    </p>
                    {!slot.available && <p className="text-xs text-red-500">Booked</p>}
                  </div>
                  <button
                    className="rounded bg-slate-900 px-3 py-1 text-white disabled:opacity-50"
                    onClick={() => bookSlotMutation.mutate(slot.start)}
                    disabled={disabled}
                  >
                    Book
                  </button>
                </div>
              )
            })}
          </div>
          {bookSlotMutation.isSuccess && (
            <p className="mt-4 rounded bg-green-50 p-2 text-sm text-green-700">
              Booking created!
            </p>
          )}
          {bookSlotMutation.isError && (
            <p className="mt-4 rounded bg-red-50 p-2 text-sm text-red-700">
              {getErrorMessage(bookSlotMutation.error, 'Booking failed')}
            </p>
          )}
        </div>
      )}
    </div>
  )
}

