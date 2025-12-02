import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { useAuth } from '../auth/AuthContext'
import { getErrorMessage } from '../utils/errors'

const schema = z.object({
  email: z.email(),
  password: z.string().min(6),
})

export const LoginPage = () => {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [form, setForm] = useState({ email: '', password: '' })
  const [errors, setErrors] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = event.target
    setForm((prev) => ({ ...prev, [name]: value }))
  }

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault()
    const parsed = schema.safeParse(form)
    if (!parsed.success) {
      setErrors('Please provide a valid email and password.')
      return
    }

    setErrors(null)
    setLoading(true)
    try {
      await login(parsed.data.email, parsed.data.password)
      navigate('/providers')
    } catch (error) {
      setErrors(getErrorMessage(error, 'Login failed. Please try again.'))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="mx-auto max-w-lg">
      <div className="card space-y-6">
        <div className="space-y-2 text-center">
          <p className="subtle-text uppercase tracking-[0.2em]">Welcome back</p>
          <h1 className="text-3xl font-semibold text-slate-900">Sign in to continue</h1>
          <p className="text-sm text-slate-500">
            Access your upcoming bookings and manage your providers from one place.
          </p>
        </div>
        {errors && (
          <div className="rounded-xl border border-red-100 bg-red-50 p-3 text-sm text-red-700">
            {errors}
          </div>
        )}
        <form className="space-y-4" onSubmit={handleSubmit}>
          <div className="space-y-2">
            <label className="text-sm font-medium text-slate-700" htmlFor="email">
              Email
            </label>
            <input
              id="email"
              className="input-field"
              type="email"
              name="email"
              autoComplete="email"
              value={form.email}
              onChange={handleChange}
              required
            />
          </div>
          <div className="space-y-2">
            <label className="text-sm font-medium text-slate-700" htmlFor="password">
              Password
            </label>
            <input
              id="password"
              className="input-field"
              type="password"
              name="password"
              autoComplete="current-password"
              value={form.password}
              onChange={handleChange}
              required
            />
          </div>
          <button className="btn-primary w-full" type="submit" disabled={loading}>
            {loading ? 'Signing you in…' : 'Login'}
          </button>
        </form>
        <p className="text-center text-sm text-slate-500">
          Need an account?{' '}
          <Link className="text-brand hover:text-brand-600" to="/register">
            Create an account
          </Link>
        </p>
      </div>
    </div>
  )
}

