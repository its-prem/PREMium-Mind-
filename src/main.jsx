import { StrictMode, Suspense, lazy, useEffect, useState } from 'react'
import { initSiteTheme } from './lib/siteTheme'
import './styles/site-dark.css' // night mode (generated)
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Route, Routes, useLocation } from 'react-router-dom'
import './index.css'
import { installClickSound, installHoverNotes } from './lib/uiSounds'
import MaintenanceGate from './components/MaintenanceGate.jsx'
import { useSiteConfig } from './lib/siteConfig'
import App from './App.jsx'

// Every other page is a separate download that starts only when the page is opened,
// so the home page loads fast. (The home page itself stays in the main file.)
const Lecture = lazy(() => import('./pages/Lecture.jsx'))
const Watch = lazy(() => import('./pages/Watch.jsx'))
const Terms = lazy(() => import('./pages/Terms.jsx'))
const AppDownload = lazy(() => import('./pages/AppDownload.jsx'))
const ResumeEditor = lazy(() => import('./pages/ResumeEditor.jsx'))
const Tools = lazy(() => import('./pages/Tools.jsx'))
const MockTests = lazy(() => import('./pages/MockTests.jsx'))
const Syllabus = lazy(() => import('./pages/Syllabus.jsx'))
const PaymentReturn = lazy(() => import('./pages/PaymentReturn.jsx'))
// Login pulls in the Firebase SDK, so it is loaded only when /login is opened.
const Login = lazy(() => import('./pages/Login.jsx'))
// Important Questions: the text reader and the micro format (A4 boxes) page
const QaReader = lazy(() => import('./pages/QaReader.jsx'))
const MicroSheetPage = lazy(() => import('./pages/MicroSheetPage.jsx'))
// The admin panel (src/admin) is its own download, opened only at /admin
const Admin = lazy(() => import('./admin/Admin.jsx'))

// The ant and the butterfly are decoration: they download after the page has finished loading.
const Butterfly = lazy(() => import('./components/Butterfly.jsx'))
const Ant = lazy(() => import('./components/Ant.jsx'))

function Pets() {
  const { pathname } = useLocation()
  const site = useSiteConfig()
  const [on, setOn] = useState(false)
  useEffect(() => {
    const start = () => setOn(true)
    if ('requestIdleCallback' in window) {
      const id = window.requestIdleCallback(start, { timeout: 4000 })
      return () => window.cancelIdleCallback(id)
    }
    const id = setTimeout(start, 2500)
    return () => clearTimeout(id)
  }, [])
  // no pets in the admin panel and on the reading pages (PDF reader, micro format, questions reader)
  const quiet = ['/admin', '/watch', '/microsheet', '/qa'].some((p) => pathname.startsWith(p))
  if (!on || quiet) return null
  return (
    <Suspense fallback={null}>
      {site.butterfly && <Butterfly />}
      {site.ant && <Ant />}
    </Suspense>
  )
}

initSiteTheme() // night mode: set before the first paint
installClickSound()
// the web app (Install from Chrome): the service worker only in the real site, never while developing
if ('serviceWorker' in navigator && import.meta.env.PROD) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}))
}
installHoverNotes()

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter>
      {/* Shows a maintenance page on every route while public/maintenance.json has "enabled": true */}
      <MaintenanceGate>
        <Pets />
        <Suspense fallback={<div style={{ minHeight: '100vh' }} />}>
          <Routes>
            <Route path="/" element={<App />} />
            <Route path="/lecture" element={<Lecture />} />
            <Route path="/watch" element={<Watch />} />
            <Route path="/termcondition" element={<Terms />} />
            <Route path="/app-download" element={<AppDownload />} />
            <Route path="/tools" element={<Tools />} />
            <Route path="/mock-tests" element={<MockTests />} />
            <Route path="/payment-return" element={<PaymentReturn />} />
            <Route path="/syllabus" element={<Syllabus />} />
            <Route path="/syllabus/:branch" element={<Syllabus />} />
            <Route path="/syllabus/:branch/:subject" element={<Syllabus />} />
            <Route path="/resume-editor/:productId" element={<ResumeEditor />} />
            <Route path="/login" element={<Login />} />
            <Route path="/qa/:id" element={<QaReader />} />
            <Route path="/microsheet/:id" element={<MicroSheetPage />} />
            <Route path="/admin" element={<Admin />} />
            <Route path="/admin/:tab" element={<Admin />} />
            <Route path="*" element={<App />} />
          </Routes>
        </Suspense>
      </MaintenanceGate>
    </BrowserRouter>
  </StrictMode>,
)
