import { useEffect } from 'react'
import { Outlet, useLocation, useSearchParams } from 'react-router-dom'
import Header from './Header'
import Footer from './Footer'
// PWA 임시 비활성화(진단용): InstallPrompt 숨김
// import InstallPrompt from '../PWA/InstallPrompt'
import { useViewSettingsStore } from '../../store/viewSettingsStore'

export default function Layout() {
  const { fontSize, theme } = useViewSettingsStore()
  const location = useLocation()
  const [searchParams] = useSearchParams()
  const hideFooter =
    location.pathname === '/search' && !(searchParams.get('q')?.trim())

  useEffect(() => {
    const html = document.documentElement
    html.setAttribute('data-font-size', fontSize)
    html.setAttribute('data-theme', theme)
    try {
      localStorage.setItem('view-theme', theme)
    } catch { /* ignore */ }
  }, [fontSize, theme])

  return (
    <div className="flex flex-col min-h-screen bg-page">
      <Header />
      <main className="flex-1 min-h-0 flex flex-col">
        <Outlet />
      </main>
      {!hideFooter && <Footer />}
      {/* <InstallPrompt /> */}
    </div>
  )
}
