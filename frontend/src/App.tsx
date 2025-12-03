import { Route, Routes, Navigate } from 'react-router-dom'
import { Layout } from './components/Layout'
import { LoginPage } from './pages/LoginPage'
import { RegisterPage } from './pages/RegisterPage'
import { ProvidersPage } from './pages/ProvidersPage'
import { ProviderDetailPage } from './pages/ProviderDetailPage'
import { MyBookingsPage } from './pages/MyBookingsPage'
import { ProviderBookingsPage } from './pages/ProviderBookingsPage'
import { AdminBookingsPage } from './pages/AdminBookingsPage'
import { AccountPage } from './pages/AccountPage'
import { ProtectedRoute } from './components/ProtectedRoute'

function App() {
  return (
    <Routes>
      <Route element={<Layout />}>
        <Route index element={<Navigate to="/providers" replace />} />
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route
          path="/providers"
          element={
            <ProtectedRoute>
              <ProvidersPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/providers/:providerId"
          element={
            <ProtectedRoute>
              <ProviderDetailPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/account"
          element={
            <ProtectedRoute>
              <AccountPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/bookings/me"
          element={
            <ProtectedRoute>
              <MyBookingsPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/bookings/provider"
          element={
            <ProtectedRoute requireRoles={['R_PROVIDER']}>
              <ProviderBookingsPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/bookings/admin"
          element={
            <ProtectedRoute requireRoles={['R_ADMIN']}>
              <AdminBookingsPage />
            </ProtectedRoute>
          }
        />
      </Route>
    </Routes>
  )
}

export default App
