import { Routes, Route } from 'react-router-dom'
import { useEffect, useState } from 'react'
import { useAuthStore } from './store/authStore'
import { useVersionCheck } from './hooks/useVersionCheck'
import Layout from './components/Layout/Layout'
import AudioPlayerPopup from './components/AudioPlayer/AudioPlayerPopup'
import ConsentModal from './components/Common/ConsentModal'
import ErrorBoundary from './components/Common/ErrorBoundary'
import HomePage from './pages/HomePage'
import AllNewsPage from './pages/AllNewsPage'
import NewsDetailPage from './pages/NewsDetailPage'
import AnalysisPage from './pages/AnalysisPage'
import ProfilePage from './pages/ProfilePage'
import CategoryPage from './pages/CategoryPage'
import AdminPage from './pages/AdminPage'
import PrivacyPage from './pages/PrivacyPage'
import TermsPage from './pages/TermsPage'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import AuthCallback from './pages/AuthCallback'
import NotFoundPage from './pages/NotFoundPage'
import SearchPage from './pages/SearchPage'
import SubscriptionPage from './pages/SubscriptionPage'
import SubscribeSuccessPage from './pages/SubscribeSuccessPage'
import SubscribeErrorPage from './pages/SubscribeErrorPage'
import SubscriptionManagePage from './pages/SubscriptionManagePage'

function App() {
  const [showConsent, setShowConsent] = useState(() => localStorage.getItem('consent_required') === '1')

  useVersionCheck()

  useEffect(() => {
    useAuthStore.getState().initializeAuth()
  }, [])

  const handleConsentAgree = () => {
    localStorage.removeItem('consent_required')
    setShowConsent(false)
  }

  const handleConsentCancel = async () => {
    try {
      const token = localStorage.getItem('access_token')
      if (token) {
        await fetch('/api/auth/withdraw', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'X-Authorization': `Bearer ${token}`,
          },
        })
      }
    } catch { /* ignore */ }
    localStorage.removeItem('consent_required')
    localStorage.removeItem('access_token')
    localStorage.removeItem('refresh_token')
    localStorage.removeItem('user')
    localStorage.removeItem('auth-storage')
    localStorage.removeItem('is_subscribed')
    window.location.href = '/login'
  }

  return (
    <ErrorBoundary>
    <div className="min-h-screen bg-page">
      <AudioPlayerPopup />
      <ConsentModal
        isOpen={showConsent}
        onAgree={handleConsentAgree}
        onCancel={handleConsentCancel}
      />
      <Routes>
        <Route path="/" element={<Layout />}>
          <Route index element={<HomePage />} />
          <Route path="news" element={<AllNewsPage />} />
          <Route path="news/:id" element={<NewsDetailPage />} />
          <Route path="analysis" element={<AnalysisPage />} />
          <Route path="profile" element={<ProfilePage />} />
          <Route path="diplomacy" element={<CategoryPage />} />
          <Route path="economy" element={<CategoryPage />} />
          <Route path="entertainment" element={<CategoryPage />} />
          <Route path="search" element={<SearchPage />} />
          <Route path="subscribe" element={<SubscriptionPage />} />
          <Route path="subscribe/success" element={<SubscribeSuccessPage />} />
          <Route path="subscribe/error" element={<SubscribeErrorPage />} />
          <Route path="subscription/manage" element={<SubscriptionManagePage />} />
        </Route>
        <Route path="/auth/callback" element={<AuthCallback />} />
        <Route path="/admin" element={<AdminPage />} />
        <Route path="/privacy" element={<PrivacyPage />} />
        <Route path="/terms" element={<TermsPage />} />
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
    </div>
    </ErrorBoundary>
  )
}

export default App
