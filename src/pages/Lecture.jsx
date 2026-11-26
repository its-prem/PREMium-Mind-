import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Navbar from '../components/Navbar';
import Sidebar from '../components/Sidebar';
import { ResumeScaled, SAMPLE_RESUME } from '../components/ResumeDocument';
import { getProduct } from '../data/storeData';
import { usePurchases } from '../lib/purchases';
import { useCatalog } from '../lib/catalog';
import { downloadState } from '../lib/downloadMode';
import {
  clearStoredSession,
  getPdfSession,
  getStoredEmail,
  getStoredName,
  isAppOnlyCourse,
  isNativeApp,
} from '../lib/session';
import '../styles/theme.css';
import './Lecture.css';

const API_URL = 'https://premind.diplomawallah.in/api.php';
const NO_PDF_MSG =
  'PDF abhi upload nahi hui. Admin panel me pdf_file column check karo (uploads/pdfs/...)';

/** Pull a usable "uploads/pdfs/..." path out of the course record. */
function pickPdfPath(course) {
  const candidates = [course.pdf_file, course.pdf_link, course.link]
    .filter(Boolean)
    .map(String);

  for (const raw of candidates) {
    let p = raw.trim();
    if (!p || p === 'null') continue;

    if (/^https?:\/\//i.test(p)) {
      try {
        p = new URL(p).pathname || '';
      } catch {
        continue;
      }
    }

    p = p.replace(/^\/+/, '');
    if (p.startsWith('uploads/pdfs/')) return p;
    if (!p.includes('/') && /\.pdf$/i.test(p)) return `uploads/pdfs/${p}`;
  }
  return '';
}

/** Only allow normal web links (blocks javascript: and similar). */
function safeWebsiteLink(value) {
  const link = String(value || '').trim();
  if (!link || link.toLowerCase() === 'null') return '';
  return /^https?:\/\//i.test(link) ? link : '';
}

function CourseCard({ course, index, onOpen }) {
  const title = course.title || 'Untitled Course';
  const appOnly = isAppOnlyCourse(course);
  const inApp = isNativeApp();
  const [imgSrc, setImgSrc] = useState(course.image || '/small-logo.png');
  const product = course._product; // something bought in the store
  const isResume = product?.category === 'resume';

  let label = 'Continue Learning';
  if (product) label = isResume ? 'Edit Template' : 'Open & Read';
  else if (appOnly && !inApp) label = 'Open in App';
  else if (!pickPdfPath(course) && safeWebsiteLink(course.website_link)) {
    label = 'Open';
  }

  return (
    <article
      className="lc-card"
      style={{ animationDelay: `${Math.min(index * 55, 450)}ms` }}
    >
      <div className="lc-card-img">
        {product && (
          <span className="lc-badge lc-badge-own">
            <ion-icon name="checkmark-circle"></ion-icon> Purchased
          </span>
        )}
        {appOnly && (
          <span className="lc-badge lc-badge-app">
            <ion-icon name="phone-portrait-outline"></ion-icon> App only
          </span>
        )}
        {isResume ? (
          <ResumeScaled
            template={product.template}
            data={SAMPLE_RESUME}
            accent={product.accent}
          />
        ) : (
          <img
            src={imgSrc}
            alt={title}
            loading="lazy"
            onError={() => setImgSrc('/small-logo.png')}
          />
        )}
      </div>

      <h2 className="lc-card-title">{title}</h2>

      <div className="lc-card-actions">
        <button type="button" className="lc-btn-access" onClick={() => onOpen(course)}>
          <ion-icon
            name={isResume ? 'create-outline' : product ? 'book-outline' : 'play-circle-outline'}
          ></ion-icon>
          <span>{label}</span>
        </button>
      </div>
    </article>
  );
}

export default function Lecture() {
  const navigate = useNavigate();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [email] = useState(() => getStoredEmail());
  const [name] = useState(() => getStoredName());
  const [status, setStatus] = useState(email ? 'loading' : 'guest');
  const [courses, setCourses] = useState([]);
  const [notice, setNotice] = useState('');
  const { purchases } = usePurchases();
  const { source: catalogSource } = useCatalog();

  // Items bought in the store on this device (shown first)
  // When the store runs from the server, bought items already arrive in the server list below,
  // so this "built-in products" shortcut is only used as an offline fallback.
  const localCourses = useMemo(
    () =>
      (catalogSource === 'server' ? [] : purchases)
        .map((p) => getProduct(p.productId))
        .filter(Boolean)
        .map((prod) => ({
          id: `local-${prod.id}`,
          title: prod.title,
          image: prod.image,
          _product: prod,
        })),
    [purchases, catalogSource]
  );

  const user = useMemo(
    () => (email ? { name: name || 'User', email } : null),
    [email, name]
  );

  const loadCourses = useCallback(
    async (signal) => {
      if (!email) return;
      setStatus('loading');
      try {
        const query = new URLSearchParams({
          email,
          pdf_session: getPdfSession() || '',
        });
        const res = await fetch(`${API_URL}?${query.toString()}`, { signal });
        const data = await res.json();
        const list = Array.isArray(data) ? data : [];
        setCourses(list.filter((c) => c.is_enrolled == 1));
        setStatus('ready');
      } catch (err) {
        if (err?.name === 'AbortError') return;
        console.error(err);
        setStatus('error');
      }
    },
    [email]
  );

  useEffect(() => {
    const controller = new AbortController();
    loadCourses(controller.signal);
    return () => controller.abort();
  }, [loadCourses]);

  useEffect(() => {
    if (!notice) return undefined;
    const t = setTimeout(() => setNotice(''), 4500);
    return () => clearTimeout(t);
  }, [notice]);

  const openCourse = (course) => {
    const product = course._product;
    if (product) {
      if (product.category === 'resume') {
        navigate(`/resume-editor/${product.id}`);
        return;
      }
      try {
        sessionStorage.setItem('pmWatchReturn', '/lecture');
      } catch {
        /* storage blocked */
      }
      navigate(
        `/watch?${new URLSearchParams({
          src: product.demoSrc || '',
          title: product.title,
        }).toString()}`
      );
      return;
    }

    if (Number(course.has_qa) === 1) {
      navigate(`/qa/${course.id}`); // Important Questions: read the questions on the website
      return;
    }

    const pdfPath = pickPdfPath(course);

    if (isAppOnlyCourse(course) && !isNativeApp()) {
      const next = `lecture?open=${encodeURIComponent(course.id ?? '')}`;
      navigate(`/app-download?from=gate&next=${encodeURIComponent(next)}`);
      return;
    }

    if (pdfPath) {
      const canDownload = downloadState(course).mode === 'instant'; // instant, or the admin's unlock time has passed
      const dlMsg =
        (course.download_msg && String(course.download_msg).trim()) ||
        'Download abhi available nahi hai. Exam se 1 din pehle unlock hoga.';

      try {
        sessionStorage.setItem('pmWatchReturn', '/lecture');
      } catch {
        /* storage blocked */
      }

      const query = new URLSearchParams({
        file: pdfPath,
        download: canDownload ? '1' : '0',
        dlmsg: dlMsg,
        title: course.title || '',
      });
      navigate(`/watch?${query.toString()}`);
      return;
    }

    const link = safeWebsiteLink(course.website_link);
    if (link) {
      window.location.href = link;
      return;
    }

    setNotice(NO_PDF_MSG);
  };

  const handleLogout = () => {
    clearStoredSession();
    setSidebarOpen(false);
    navigate('/');
  };

  const total = localCourses.length + (status === 'ready' ? courses.length : 0);
  const countText =
    status === 'loading'
      ? 'Syncing...'
      : status === 'error'
        ? 'Offline'
        : `${total} ${total === 1 ? 'Item' : 'Items'}`;

  return (
    <div className="lc-page">
      <Navbar
        activeNav="courses"
        onOpenSidebar={() => setSidebarOpen(true)}
        onNavigate={navigate}
      />

      <Sidebar
        isOpen={sidebarOpen}
        onClose={() => setSidebarOpen(false)}
        user={user}
        onLogout={handleLogout}
        onNavigate={navigate}
        onSelectCategory={() => navigate('/')}
      />

      {/* ================= HEADER ================= */}
      <header className="lc-hero">
        <div className="lc-hero-inner">
          <span className="lc-hero-badge">
            <span className="lc-live-dot" /> Student learning portal
          </span>
          <h1>My Learning Library</h1>
          <p>
            Your enrolled premium courses, notes, and study materials ready
            whenever you are.
          </p>
        </div>
      </header>

      {/* ================= SUMMARY BAR ================= */}
      <div className="lc-toolbar">
        <div className="lc-toolbar-inner">
          <div className="lc-toolbar-left">
            <span>Enrolled Materials</span>
            <span className="lc-count-pill">{countText}</span>
          </div>
          <Link to="/#our-store" className="lc-store-link">
            <ion-icon name="bag-add-outline"></ion-icon> Browse Store
          </Link>
        </div>
      </div>

      {/* ================= CONTENT ================= */}
      <main className="lc-grid" aria-live="polite">
        {status === 'loading' && (
          <div className="lc-state">
            <div className="lc-spinner" />
            <p>Syncing your library...</p>
          </div>
        )}

        {status === 'guest' && localCourses.length > 0 && (
          <p className="lc-login-note">
            <ion-icon name="information-circle-outline"></ion-icon>
            <span>
              Ye aapke is device ki khareedari hai. <Link to="/login">Login karo</Link> taaki
              account ke baaki courses bhi dikhein.
            </span>
          </p>
        )}

        {status === 'guest' && localCourses.length === 0 && (
          <div className="lc-empty">
            <div className="lc-empty-icon">
              <ion-icon name="lock-closed-outline"></ion-icon>
            </div>
            <h2>Login Required</h2>
            <p>Apne purchased courses dekhne ke liye pehle login karo.</p>
            <div className="lc-empty-actions">
              <Link to="/login" className="lc-btn-primary">
                <ion-icon name="log-in-outline"></ion-icon> Login / Sign Up
              </Link>
              <Link to="/" className="lc-btn-ghost">
                Home
              </Link>
            </div>
          </div>
        )}

        {status === 'error' && (
          <div className="lc-empty">
            <div className="lc-empty-icon lc-empty-icon-warn">
              <ion-icon name="cloud-offline-outline"></ion-icon>
            </div>
            <h2>Couldn&apos;t load courses</h2>
            <p>Connection check karke dobara try karo.</p>
            <div className="lc-empty-actions">
              <button
                type="button"
                className="lc-btn-primary"
                onClick={() => loadCourses()}
              >
                <ion-icon name="refresh-outline"></ion-icon> Retry
              </button>
            </div>
          </div>
        )}

        {status === 'ready' && total === 0 && (
          <div className="lc-empty">
            <div className="lc-empty-icon">
              <ion-icon name="library-outline"></ion-icon>
            </div>
            <h2>No Courses Yet</h2>
            <p>
              Aapne abhi tak koi premium course enroll nahi kiya. Store explore
              karke apni skills upgrade karo!
            </p>
            <div className="lc-empty-actions">
              <Link to="/#our-store" className="lc-btn-primary">
                <ion-icon name="compass-outline"></ion-icon> Explore Store
              </Link>
            </div>
          </div>
        )}

        {localCourses.map((course, index) => (
          <CourseCard
            key={course.id}
            course={course}
            index={index}
            onOpen={openCourse}
          />
        ))}

        {status === 'ready' &&
          courses.map((course, index) => (
            <CourseCard
              key={course.id ?? `${course.title}-${index}`}
              course={course}
              index={localCourses.length + index}
              onOpen={openCourse}
            />
          ))}
      </main>

      {notice && (
        <div className="lc-toast" role="status">
          <ion-icon name="information-circle-outline"></ion-icon>
          <span>{notice}</span>
        </div>
      )}
    </div>
  );
}
