import { StrictMode, Suspense, lazy } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Route, Routes } from 'react-router-dom'
import './index.css'
import { installClickSound, installHoverNotes } from './lib/uiSounds'
import Butterfly from './components/Butterfly.jsx'
import MaintenanceGate from './components/MaintenanceGate.jsx'
import Ant from './components/Ant.jsx'
import App from './App.jsx'
import Lecture from './pages/Lecture.jsx'
import Watch from './pages/Watch.jsx'
import Terms from './pages/Terms.jsx'
import AppDownload from './pages/AppDownload.jsx'
import ResumeEditor from './pages/ResumeEditor.jsx'
import Tools from './pages/Tools.jsx'
import MockTests from './pages/MockTests.jsx'
import Syllabus from './pages/Syllabus.jsx'
import PaymentReturn from './pages/PaymentReturn.jsx'

// Login pulls in the Firebase SDK, so load it only when /login is opened.
const Login = lazy(() => import('./pages/Login.jsx'))

installClickSound()
installHoverNotes()

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter>
      {/* Shows a maintenance page on every route while public/maintenance.json has "enabled": true */}
      <MaintenanceGate>
      <Butterfly />
      <Ant />
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
        <Route
          path="/login"
          element={
            <Suspense fallback={null}>
              <Login />
            </Suspense>
          }
        />
        <Route path="*" element={<App />} />
      </Routes>
      </MaintenanceGate>
    </BrowserRouter>
  </StrictMode>,
)
