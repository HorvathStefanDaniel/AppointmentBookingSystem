export type Provider = {
  id: number
  name: string
}

export type Service = {
  id: number
  name: string
  durationMinutes: number
}

export type Slot = {
  start: string
  end: string
  available: boolean
  optimistic?: boolean
  reservedByMe?: boolean
}

export type Booking = {
  id: number
  status: string
  startDateTime: string
  endDateTime: string
  createdAt: string
  cancelledAt?: string | null
  service: {
    id: number
    name: string
  }
  provider: Provider | null
  user: {
    id: number
    email: string
  }
}

export type AuthClaims = {
  email?: string
  username?: string
  roles?: string[]
  providerId?: number
  userId?: number
  exp?: number
  [key: string]: unknown
}

