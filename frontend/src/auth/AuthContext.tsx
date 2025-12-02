import type { ReactNode } from 'react'
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react'
import { jwtDecode } from 'jwt-decode'
import axios from 'axios'
import api, { setAuthToken } from '../api/client'
import type { AuthClaims } from '../types'

type AuthContextValue = {
  token: string | null
  claims: AuthClaims | null
  isAuthenticated: boolean
  login: (email: string, password: string) => Promise<void>
  register: (email: string, password: string) => Promise<void>
  logout: () => void
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined)

const TOKEN_KEY = 'harba_token'

const getStoredToken = () => localStorage.getItem(TOKEN_KEY)

export const AuthProvider = ({ children }: { children: ReactNode }) => {
  const storedToken = getStoredToken()
  if (storedToken) {
    setAuthToken(storedToken)
  }

  const [token, setToken] = useState<string | null>(storedToken)
  const [claims, setClaims] = useState<AuthClaims | null>(() => {
    if (!storedToken) return null
    try {
      return jwtDecode<AuthClaims>(storedToken)
    } catch {
      return null
    }
  })

  useEffect(() => {
    setAuthToken(token)
    if (token) {
      localStorage.setItem(TOKEN_KEY, token)
    } else {
      localStorage.removeItem(TOKEN_KEY)
    }
  }, [token])

  const login = useCallback(async (email: string, password: string) => {
    try {
      const response = await api.post('/auth/login', { email, password })
      const jwt = response.data.token as string
      setToken(jwt)
      setClaims(jwtDecode(jwt))
    } catch (error) {
      if (axios.isAxiosError(error)) {
        const message = (error.response?.data as { message?: string })?.message
        throw new Error(message ?? 'Login failed')
      }
      throw error
    }
  }, [])

  const register = useCallback(async (email: string, password: string) => {
    try {
      await api.post('/auth/register', { email, password })
    } catch (error) {
      if (axios.isAxiosError(error)) {
        const message = (error.response?.data as { message?: string })?.message
        throw new Error(message ?? 'Registration failed')
      }
      throw error
    }
  }, [])

  const logout = useCallback(() => {
    setToken(null)
    setClaims(null)
  }, [])

  const value = useMemo(
    () => ({
      token,
      claims,
      isAuthenticated: Boolean(token),
      login,
      register,
      logout,
    }),
    [token, claims, login, register, logout]
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export const useAuth = () => {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('useAuth must be used within AuthProvider')
  }
  return ctx
}

