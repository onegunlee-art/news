import { useEffect } from 'react'
import { Outlet } from 'react-router-dom'
import Header from './Header'
import Footer from './Footer'
import { useViewSettingsStore } from '../../store/viewSettingsStore'

export default function Layout() {
  const { fontSize, grayscale } = useViewSettingsStore()

  useEffect(() => {
    const html = document.documentElement
    html.setAttribute('data-font-size', fontSize)
    html.setAttribute('data-grayscale', grayscale ? 'true' : 'false')
  }, [fontSize, grayscale])

  return (
    <div className="flex flex-col min-h-screen">
      <Header />
      <main className="flex-1">
        <Outlet />
      </main>
      <Footer />
    </div>
  )
}
