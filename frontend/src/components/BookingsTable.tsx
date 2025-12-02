import type { Booking } from '../types'

type Props = {
  bookings: Booking[]
  onCancel?: (bookingId: number) => void
  canCancel?: (booking: Booking) => boolean
  cancellingIds?: number[]
}

export const BookingsTable = ({ bookings, onCancel, canCancel, cancellingIds = [] }: Props) => {
  if (bookings.length === 0) {
    return <p>No bookings to display.</p>
  }

  return (
    <div className="overflow-x-auto rounded border bg-white shadow">
      <table className="min-w-full divide-y divide-slate-200 text-sm">
        <thead className="bg-slate-50">
          <tr>
            <th className="px-4 py-2 text-left font-medium text-slate-600">Service</th>
            <th className="px-4 py-2 text-left font-medium text-slate-600">Start</th>
            <th className="px-4 py-2 text-left font-medium text-slate-600">End</th>
            <th className="px-4 py-2 text-left font-medium text-slate-600">User</th>
            <th className="px-4 py-2 text-left font-medium text-slate-600">Status</th>
            {onCancel && <th className="px-4 py-2" />}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 bg-white">
          {bookings.map((booking) => (
            <tr key={booking.id}>
              <td className="px-4 py-2">
                <div className="font-medium">{booking.service.name}</div>
                <div className="text-xs text-slate-500 flex flex-col">
                  <span>Service #{booking.service.id}</span>
                  {booking.provider && <span>Provider: {booking.provider.name}</span>}
                </div>
              </td>
              <td className="px-4 py-2">{new Date(booking.startDateTime).toLocaleString()}</td>
              <td className="px-4 py-2">{new Date(booking.endDateTime).toLocaleString()}</td>
              <td className="px-4 py-2">{booking.user.email}</td>
              <td className="px-4 py-2 capitalize">{booking.status}</td>
              {onCancel && (
                <td className="px-4 py-2 text-right">
                  {canCancel?.(booking) && booking.status === 'active' ? (
                    <button
                      className="rounded border border-red-500 px-3 py-1 text-red-600 disabled:opacity-50"
                      onClick={() => onCancel(booking.id)}
                      disabled={cancellingIds.includes(booking.id)}
                    >
                      {cancellingIds.includes(booking.id) ? 'Cancelling…' : 'Cancel'}
                    </button>
                  ) : (
                    <span className="text-xs text-slate-400">—</span>
                  )}
                </td>
              )}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

