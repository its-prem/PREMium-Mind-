import React, { Suspense, lazy, useState, useEffect, useRef } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import {
  clearStoredSession,

  getStoredEmail,
  getStoredName,
} from './lib/session';
import './styles/theme.css';
import './App.css';
import Navbar from './components/Navbar';
// The side menu and the footer are not needed for the first paint: they load right after it.
const Sidebar = lazy(() => import('./components/Sidebar'));
const Footer = lazy(() => import('./components/Footer'));
import Store from './components/Store';
import { ResumeScaled, SAMPLE_RESUME } from './components/ResumeDocument';
import { usePurchases } from './lib/purchases';
import { useCatalog } from './lib/catalog';
import CheckoutModal from './components/CheckoutModal';
import { useSiteConfig } from './lib/siteConfig';

const HERO_ROTATING_WORDS = [
  'Handwritten Notes',
  'Solved JUT PYQs',
  'ATS Resumes',
  'Ready Projects',
];

export default function App() {
  const navigate = useNavigate();
  const location = useLocation();
  const site = useSiteConfig();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  // true shortly after the page is ready (or at once if the menu is opened sooner)
  const [lowerReady, setLowerReady] = useState(false);
  useEffect(() => {
    const start = () => setLowerReady(true);
    if ('requestIdleCallback' in window) {
      const id = window.requestIdleCallback(start, { timeout: 2500 });
      return () => window.cancelIdleCallback(id);
    }
    const id = setTimeout(start, 1200);
    return () => clearTimeout(id);
  }, []);
  const [activeNav, setActiveNav] = useState('home');
  // null = show the 4 category cards; otherwise the id of the open category
  const [activeCategory, setActiveCategory] = useState(null);
  const { owns } = usePurchases();
  useCatalog(); // loads the products from the server
  const [user, setUser] = useState(() => {
    const email = getStoredEmail();
    return email ? { name: getStoredName() || 'User', email } : null;
  });
  const [wordIndex, setWordIndex] = useState(0);

  // Scroll Reveal States
  const [storeVisible, setStoreVisible] = useState(false);
  const [ctaVisible, setCtaVisible] = useState(false);
  const storeRef = useRef(null);
  const ctaRef = useRef(null);

  // Sample Preview Modal State
  const [previewItem, setPreviewItem] = useState(null);

  // Checkout Modal State
  const [checkoutItem, setCheckoutItem] = useState(null);

  // Scroll to a section when arriving via a link like /#our-store
  useEffect(() => {
    if (!location.hash) return undefined;
    const id = location.hash.slice(1);
    const timer = setTimeout(() => {
      document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' });
    }, 120);
    return () => clearTimeout(timer);
  }, [location.hash]);

  useEffect(() => {
    const interval = setInterval(() => {
      setWordIndex((prev) => (prev + 1) % HERO_ROTATING_WORDS.length);
    }, 2600);
    return () => clearInterval(interval);
  }, []);

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            if (entry.target === storeRef.current) setStoreVisible(true);
            if (entry.target === ctaRef.current) setCtaVisible(true);
          }
        });
      },
      { threshold: 0 }
    );

    if (storeRef.current) observer.observe(storeRef.current);
    if (ctaRef.current) observer.observe(ctaRef.current);
    return () => observer.disconnect();
  }, []);

  // Opens the checkout for a product (the popup itself is components/CheckoutModal.jsx)
  const openBuyModal = (product) => {
    setCheckoutItem({
      courseId: product.id,
      kind: 'course',
      title: product.title,
      price: product.price,
      mrp: product.oldPrice,
      showTnc: product.showTnc,
      tncText: product.tncText,
      download: product.category === 'important' && !product.hasPdf ? undefined : { allow_download: product.allowDownload, download_at: product.downloadAt },
      openLabel: product.category === 'resume' ? 'Open template editor' : 'Open & read now',
      product,
    });
  };

  // "Open & Read" / "Edit Template" for something the user owns
  const openProduct = (product) => {
    setCheckoutItem(null);
    if (product.openLink && !product.pdfFile && !product.hasQa && !product.hasPdf) {
      window.location.href = product.openLink; // the product has its own page (set in the admin), e.g. D2D PYQ
      return;
    }
    if (product.category === 'resume') {
      navigate(`/resume-editor/${product.id}`);
      return;
    }
    if (product.hasQa) {
      navigate(`/qa/${product.id}`); // Important Questions: read the questions on the website
      return;
    }
    if (product.fromServer && product.hasPdf && !product.pdfFile) {
      // it has a PDF but the server did not send its path: the login token is missing or expired
      window.alert('For your security you need to log in again. Opening the login page.');
      clearStoredSession();
      navigate('/login');
      return;
    }
    // A product bought from the server is read through the secure reader (login token + watermark).
    // The built-in demo products open their sample PDF instead.
    const query = new URLSearchParams(
      product.pdfFile
        ? { file: product.pdfFile, title: product.title }
        : { src: product.demoSrc || '', title: product.title }
    );
    try {
      sessionStorage.setItem('pmWatchReturn', '/lecture');
    } catch {
      /* storage blocked */
    }
    navigate(`/watch?${query.toString()}`);
  };

  return (
    <div className="pm-page-wrapper" id="top">
      <Navbar
        user={user}
        activeNav={activeNav}
        onNavChange={setActiveNav}
        onOpenSidebar={() => {
          setLowerReady(true);
          setSidebarOpen(true);
        }}
        onSelectCategory={(cat) => setActiveCategory(cat === 'all' ? null : cat)}
        onNavigate={navigate}
      />

      {lowerReady && (
        <Suspense fallback={null}>
          <Sidebar
            isOpen={sidebarOpen}
            onClose={() => setSidebarOpen(false)}
            user={user}
            onLoginDemo={() => navigate('/login')}
            onLogout={() => {
              clearStoredSession();
              setUser(null);
            }}
            onSelectCategory={(cat) => setActiveCategory(cat === 'all' ? null : cat)}
            onNavigate={navigate}
          />
        </Suspense>
      )}

      {/* ================= SEAMLESS HERO ================= */}
      <section className="pm-hero">
        <div className="pm-hero-ambient-glow" aria-hidden="true" />
        <div className="pm-hero-grid-overlay" aria-hidden="true" />

        {/* Photo Stage */}
        <div className="pm-hero-photo-stage" aria-hidden="true">
          <img src={site.heroImage || '/mh.jpg'} alt="PREMium Mind" className="pm-hero-clear-img" />
          <div className="pm-hero-seamless-blend" />
        </div>

        {/* Foreground Content */}
        <div className="pm-hero-inner">
          <div className="pm-hero-main-row">
            <div className="pm-hero-left">
              <div className="pm-hero-kicker anim-fade-up-0">
                <span className="pm-kicker-dot" />
                <span>JHARKHAND'S #1 STUDENT HUB</span>
              </div>

              <h1 className="pm-hero-heading">
                <span className="pm-line-mask">
                  <span className="pm-line-reveal line-1">Empowering</span>
                </span>
                <span className="pm-line-mask">
                  <span className="pm-line-reveal line-2 pm-highlight-red">
                    Polytechnic &amp;
                  </span>
                </span>
                <span className="pm-line-mask">
                  <span className="pm-line-reveal line-3">Engineering</span>
                </span>
                <span className="pm-line-mask pm-last-line">
                  <span className="pm-line-reveal line-4 pm-underline-target">
                    Students
                    <svg
                      className="pm-red-brush"
                      viewBox="0 0 220 18"
                      fill="none"
                      preserveAspectRatio="none"
                    >
                      <path
                        className="brush-stroke-main"
                        d="M3 13 C58 3, 152 3, 217 12"
                        stroke="#dc2626"
                        strokeWidth="4.5"
                        strokeLinecap="round"
                      />
                      <path
                        className="brush-stroke-sub"
                        d="M28 16 C82 9, 148 9, 196 15"
                        stroke="#ffffff"
                        strokeWidth="2"
                        strokeLinecap="round"
                      />
                    </svg>
                  </span>
                </span>
              </h1>

              <div className="pm-hero-desc anim-fade-up-1">
                <div className="pm-dynamic-row">
                  <span>Exam-Ready</span>
                  <span className="pm-rotating-word" key={wordIndex}>
                    {HERO_ROTATING_WORDS[wordIndex]}
                  </span>
                </div>
                <p className="pm-sub-tagline">
                  Premium Notes, Courses, PYQs and More — Learn Smart, Grow
                  Better.
                </p>
              </div>

              <div className="pm-hero-btns anim-fade-up-2">
                <a
                  href="#our-store"
                  className="pm-glass-btn-primary"
                  onClick={() => setActiveNav('store')}
                >
                  <ion-icon name="cart-outline"></ion-icon>
                  <span>Visit Store</span>
                  <span className="btn-arrow">→</span>
                </a>

                <a
                  href="#cta-banner"
                  className="pm-glass-btn-secondary"
                  onClick={() => setActiveNav('courses')}
                >
                  <span className="play-glass-circle">
                    <ion-icon name="play"></ion-icon>
                  </span>
                  <span>Explore Courses</span>
                </a>
              </div>
            </div>
          </div>

          {/* 4-Column Floating Trust Bar */}
          <div className="pm-trust-strip anim-fade-up-3">
            <div className="pm-trust-cell">
              <div className="pm-trust-badge">
                <ion-icon name="flash"></ion-icon>
              </div>
              <span>
                Instant
                <br />
                Download
              </span>
            </div>

            <div className="pm-trust-line" />

            <div className="pm-trust-cell">
              <div className="pm-trust-badge">
                <ion-icon name="shield-checkmark"></ion-icon>
              </div>
              <span>
                Secure
                <br />
                Payment
              </span>
            </div>

            <div className="pm-trust-line" />

            <div className="pm-trust-cell">
              <div className="pm-trust-badge">
                <ion-icon name="headset"></ion-icon>
              </div>
              <span>
                24/7
                <br />
                Support
              </span>
            </div>

            <div className="pm-trust-line" />

            <div className="pm-trust-cell">
              <div className="pm-trust-badge">
                <ion-icon name="people"></ion-icon>
              </div>
              <span>
                Trusted by
                <br />
                Students
              </span>
            </div>
          </div>
        </div>
      </section>

      {/* ================= STORE SECTION ================= */}
      <section
        ref={storeRef}
        className={`pm-store-section ${storeVisible ? 'is-revealed' : ''}`}
        id="our-store"
      >
        <div className="pm-container">
          <div className="pm-section-heading">
            <span className="pm-heading-eyebrow">PREMIUM RESOURCES</span>
            <h2>
              EXPLORE OUR <span className="pm-text-accent">PREMium Mind</span>{' '}
              STORE
            </h2>
            <p>Exam-Focused Notes, Important Questions &amp; Career Resources</p>
          </div>

          <Store
            activeCategory={activeCategory}
            onSelectCategory={setActiveCategory}
            owns={owns}
            onPreview={setPreviewItem}
            onBuy={openBuyModal}
            onOpen={openProduct}
          />
        </div>
      </section>

      {/* ================= RED, BLACK & WHITE CTA BANNER ================= */}
      <section
        ref={ctaRef}
        className={`pm-cta-section ${ctaVisible ? 'is-revealed' : ''}`}
        id="cta-banner"
      >
        <div className="pm-container">
          <div className="pm-crimson-banner">
            <div className="pm-banner-glow" aria-hidden="true" />
            <div className="pm-banner-icon">
              <ion-icon name="book-outline"></ion-icon>
            </div>
            <div className="pm-banner-copy">
              <h3>Build Your Skills. Achieve Your Goals.</h3>
              <p>
                Access complete semester bundles, PYQs, and project source
                codes.
              </p>
            </div>
            <a href="#our-store" className="pm-banner-white-btn">
              <span>Explore Store</span>
              <span className="pm-gold-arrow">→</span>
            </a>
          </div>
        </div>
      </section>

      {/* ================= FOOTER ================= */}
      {lowerReady && (
        <Suspense fallback={null}>
          <Footer />
        </Suspense>
      )}

      {/* ================= SAMPLE PAGE 1 PREVIEW MODAL ================= */}
      {previewItem && (
        <div className="pm-modal-overlay" onClick={() => setPreviewItem(null)}>
          <div
            className="pm-modal-card preview-card"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="pm-modal-head">
              <div>
                <h4>{previewItem.title}</h4>
                <small>Free sample • Buy to unlock the full thing</small>
              </div>
              <button type="button" onClick={() => setPreviewItem(null)}>
                ✕
              </button>
            </div>
            <div className="pm-modal-body center">
              {previewItem.category === 'resume' ? (
                <div className="pm-sample-resume">
                  <ResumeScaled
                    template={previewItem.template}
                    data={SAMPLE_RESUME}
                    accent={previewItem.accent}
                  />
                </div>
              ) : (
                <img
                  src={previewItem.sampleImg}
                  alt="Sample Page 1"
                  className="pm-sample-img"
                  onError={(e) => {
                    e.currentTarget.src = '/small-logo.png';
                  }}
                />
              )}
            </div>
            <div className="pm-modal-foot">
              <button
                type="button"
                className="pm-buy-btn"
                onClick={() => {
                  const target = previewItem;
                  setPreviewItem(null);
                  openBuyModal(target);
                }}
              >
                Unlock Full Access @ ₹{previewItem.price}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ================= SECURE CHECKOUT (Cashfree) ================= */}
      {checkoutItem && (
        <CheckoutModal
          item={checkoutItem}
          onClose={() => setCheckoutItem(null)}
          onOpen={(it) => openProduct(it.product)}
        />
      )}
    </div>
  );
}