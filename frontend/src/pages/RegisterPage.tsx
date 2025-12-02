import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { useAuth } from '../auth/AuthContext'
import { getErrorMessage } from '../utils/errors'

const schema = z.object({
  email: z.string().email(),
  password: z.string().min(8),
})

export const RegisterPage = () => {
  const { register, login } = useAuth()
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
      setErrors('Please choose a valid email and an 8+ character password.')
      return
    }

    setErrors(null)
    setLoading(true)
    try {
      await register(parsed.data.email, parsed.data.password)
      await login(parsed.data.email, parsed.data.password)
      navigate('/providers')
    } catch (error) {
      setErrors(getErrorMessage(error, 'Registration failed.'))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="mx-auto max-w-lg">
      <div className="card space-y-6">
        <div className="space-y-2 text-center">
          <p className="subtle-text uppercase tracking-[0.2em]">Join the network</p>
          <h1 className="text-3xl font-semibold text-slate-900">Create your free account</h1>
          <p className="text-sm text-slate-500">
            Book appointments, manage docks, and collaborate with marinas across the globe.
          </p>
        </div>
        {errors && (
          <div className="rounded-xl border border-red-100 bg-red-50 p-3 text-sm text-red-700">
            {errors}
          </div>
        )}
        <form className="space-y-4" onSubmit={handleSubmit}>
          <div className="space-y-2">
            <label className="text-sm font-medium text-slate-700" htmlFor="register-email">
              Email
            </label>
            <input
              id="register-email"
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
            <label className="text-sm font-medium text-slate-700" htmlFor="register-password">
              Password
            </label>
            <input
              id="register-password"
              className="input-field"
              type="password"
              name="password"
              autoComplete="new-password"
              value={form.password}
              onChange={handleChange}
              required
            />
            <p className="text-xs text-slate-500">Use at least 8 characters.</p>
          </div>
          <button className="btn-primary w-full" type="submit" disabled={loading}>
            {loading ? 'Creating account…' : 'Create account'}
          </button>
        </form>
        <p className="text-center text-sm text-slate-500">
          Already have an account?{' '}
          <Link className="text-brand hover:text-brand-600" to="/login">
            Sign in
          </Link>
        </p>
      </div>
    </div>
  )
}

