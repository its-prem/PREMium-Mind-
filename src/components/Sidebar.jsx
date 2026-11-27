import React, { useEffect, useRef, useState } from 'react';
import { isSoundOn, playClick, playPiano, setSoundOn } from '../lib/uiSounds';
import CatAvatar from './CatAvatar';
import { useSiteTheme } from '../lib/siteTheme';
import './Sidebar.css';

const STORE_CATEGORIES = [
  { id: 'notes', label: 'Notes', icon: 'document-text-outline' },
  { id: 'ebooks', label: 'E-Books', icon: 'book-outline' },
  { id: 'resume', label: 'Resume Templates', icon: 'briefcase-outline' },
  { id: 'important', label: 'Important Questions', icon: 'help-circle-outline' },
];

export default function Sidebar({
  isOpen = false,
  onClose,
  user = null,
  isAdmin = false,
  onLoginDemo,
  onLogout,
  onSelectCategory,
  onNavigate,
  onPhotoChange,
}) {
  const [storeOpen, setStoreOpen] = useState(false);
  const [customAvatar, setCustomAvatar] = useState(null);
  const [soundOn, setSoundOnState] = useState(() => isSoundOn());
  const [night, toggleNight] = useSiteTheme();
  const photoInputRef = useRef(null);

  const toggleSound = () => {
    const next = !soundOn;
    setSoundOn(next);
    setSoundOnState(next);
    if (next) playClick();
  };

  // Menu sounds (event delegation): a piano note per item on hover/touch,
  // and a tick on click.
  const MENU_ITEM = '.pm-sb-nav-btn, .pm-sb-sub-item';
  const menuItemOf = (e) => e.target.closest?.(MENU_ITEM);

  const hoveredItem = useRef(null);

  const handleMenuHover = (e) => {
    const item = menuItemOf(e);
    if (!item) {
      hoveredItem.current = null;
      return;
    }
    if (item === hoveredItem.current) return; // still inside the same item
    hoveredItem.current = item;

    // Number only the items the user can see (skip a collapsed store list).
    const items = Array.from(e.currentTarget.querySelectorAll(MENU_ITEM)).filter(
      (el) => {
        const acc = el.closest('.pm-sb-acc');
        return !el.closest('.pm-sb-acc-panel') || acc?.classList.contains('is-expanded');
      }
    );
    playPiano(Math.max(0, items.indexOf(item)));
  };

  useEffect(() => {
    if (!isOpen) return;

    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && onClose) onClose();
    };
    window.addEventListener('keydown', handleKeyDown);

    return () => {
      document.body.style.overflow = prevOverflow;
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [isOpen, onClose]);

  const handleCategoryClick = (e, catId) => {
    e.preventDefault();
    if (onClose) onClose();
    if (onSelectCategory) onSelectCategory(catId);

    const storeSection = document.getElementById('our-store');
    if (storeSection) {
      storeSection.scrollIntoView({ behavior: 'smooth' });
    } else if (onNavigate) {
      onNavigate('/#our-store');
    }
  };

  const handleSectionScroll = (e, targetId, routePath) => {
    e.preventDefault();
    if (onClose) onClose();

    if (targetId === 'top') {
      if (onNavigate && window.location.pathname !== '/') {
        onNavigate('/');
        return;
      }
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }

    const section = document.getElementById(targetId);
    if (section) {
      section.scrollIntoView({ behavior: 'smooth' });
    } else if (onNavigate && routePath) {
      onNavigate(routePath);
    }
  };

  const handleFileChange = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (ev) => {
      setCustomAvatar(ev.target.result);
      if (onPhotoChange) onPhotoChange(ev.target.result, file);
    };
    reader.readAsDataURL(file);
  };

  const displayName = user?.name || 'PREMium Learner';
  const uploadedPhoto = customAvatar || user?.photoURL || null;

  return (
    <>
      {/* Blurred Backdrop */}
      <div
        className={`pm-sb-backdrop ${isOpen ? 'is-open' : ''}`}
        onClick={onClose}
        aria-hidden="true"
      />

      {/* Red, Black & White Executive Drawer */}
      <aside
        className={`pm-sb-drawer ${isOpen ? 'is-open' : ''}`}
        aria-label="Account & Navigation Drawer"
        aria-hidden={!isOpen}
      >
        {/* Matte Black & Red Wave Header */}
        <div className="pm-sb-wave-header">
          {/* Top Bar: Close Button Only */}
          <div className="pm-sb-topbar">
            <button
              type="button"
              className="pm-sb-close-circle"
              onClick={onClose}
              aria-label="Close Sidebar"
            >
              <ion-icon name="close-outline"></ion-icon>
            </button>
          </div>

          {/* Horizontal Profile Row */}
          <div className="pm-sb-horiz-profile">
            {/* Circular Avatar with Sonar Rings & Animated Eyes Emoji */}
            <div
              className="pm-sb-wave-avatar-box"
              onClick={() => photoInputRef.current?.click()}
            >
              <span className="pm-wave-ring ring-outer" aria-hidden="true" />
              <span className="pm-wave-ring ring-inner" aria-hidden="true" />
              <span className="pm-wave-orbit" aria-hidden="true" />

              {uploadedPhoto ? (
                <img
                  src={uploadedPhoto}
                  alt={displayName}
                  className="pm-sb-circle-img"
                />
              ) : (
                <CatAvatar active={isOpen} />
              )}

              <span className="pm-sb-cam-badge" title="Upload photo">
                <ion-icon name="camera"></ion-icon>
              </span>
            </div>

            <input
              ref={photoInputRef}
              type="file"
              accept="image/*"
              style={{ display: 'none' }}
              onChange={handleFileChange}
            />

            {/* Right: User Info / Login CTA */}
            <div className="pm-sb-horiz-info">
              {user ? (
                <>
                  <h3 className="pm-sb-horiz-name">{user.name}</h3>
                  <p className="pm-sb-horiz-email">{user.email}</p>
                </>
              ) : (
                <>
                  <h3 className="pm-sb-horiz-name">Welcome to PREMium Mind</h3>
                  <p className="pm-sb-horiz-sub">
                    Access your notes, PYQs &amp; courses
                  </p>
                  <button
                    type="button"
                    className="pm-sb-compact-login"
                    onClick={() => {
                      if (onLoginDemo) onLoginDemo();
                      else if (onNavigate) onNavigate('/login');
                    }}
                  >
                    <ion-icon name="log-in-outline"></ion-icon>
                    <span>Login / Sign Up</span>
                  </button>
                </>
              )}
            </div>
          </div>

          {/* Bottom Smooth SVG Wave */}
          <svg
            className="pm-sb-bottom-wave"
            viewBox="0 0 360 28"
            preserveAspectRatio="none"
            aria-hidden="true"
          >
            <path
              d="M0,10 C95,28 245,0 360,16 L360,28 L0,28 Z"
              fill="#f4f4f5"
            />
          </svg>
        </div>

        {/* Scrollable Navigation Menu */}
        <div className="pm-sb-scroll-area">
          <div className="pm-sb-divider-label">
            <span>QUICK MENU</span>
            <button
              type="button"
              className="pm-sb-sound-toggle"
              onClick={toggleSound}
              aria-label={soundOn ? 'Turn menu sounds off' : 'Turn menu sounds on'}
              aria-pressed={soundOn}
              title={soundOn ? 'Sounds on' : 'Sounds off'}
            >
              <ion-icon
                name={soundOn ? 'volume-high-outline' : 'volume-mute-outline'}
              ></ion-icon>
            </button>
            {/* night mode: a small moon next to the sound button */}
            <button
              type="button"
              className="pm-sb-sound-toggle pm-sb-night-toggle"
              onClick={toggleNight}
              aria-label={night ? 'Switch to day mode' : 'Switch to night mode'}
              aria-pressed={night}
              title={night ? 'Day mode' : 'Night mode'}
            >
              <ion-icon name={night ? 'sunny-outline' : 'moon-outline'}></ion-icon>
            </button>
          </div>

          <ul
            className="pm-sb-nav-list"
            onPointerOver={handleMenuHover}
            onPointerLeave={() => {
              hoveredItem.current = null;
            }}
          >
            <li className="pm-sb-row" style={{ '--delay': '0.05s' }}>
              <a
                href="#top"
                className="pm-sb-nav-btn"
                onClick={(e) => handleSectionScroll(e, 'top', '/')}
              >
                <span className="pm-sb-icon-circle">
                  <ion-icon name="home-outline"></ion-icon>
                </span>
                <span className="pm-sb-nav-text">Home</span>
                <ion-icon
                  name="chevron-forward-outline"
                  className="pm-sb-arrow-right"
                ></ion-icon>
              </a>
            </li>

            {/* Click-to-Expand Store Accordion */}
            <li
              className={`pm-sb-row pm-sb-acc ${
                storeOpen ? 'is-expanded' : ''
              }`}
              style={{ '--delay': '0.1s' }}
            >
              <button
                type="button"
                className={`pm-sb-nav-btn ${storeOpen ? 'is-active' : ''}`}
                onClick={() => setStoreOpen((prev) => !prev)}
                aria-expanded={storeOpen}
              >
                <span className="pm-sb-icon-circle">
                  <ion-icon name="bag-handle-outline"></ion-icon>
                </span>
                <span className="pm-sb-nav-text">PREMium Store</span>
                <ion-icon
                  name="chevron-down-outline"
                  className="pm-sb-acc-chevron"
                ></ion-icon>
              </button>

              <div className="pm-sb-acc-panel">
                <div className="pm-sb-acc-inner">
                  {STORE_CATEGORIES.map((cat) => (
                    <a
                      key={cat.id}
                      href={`#${cat.id}`}
                      className="pm-sb-sub-item"
                      onClick={(e) => handleCategoryClick(e, cat.id)}
                    >
                      <span className="pm-sb-sub-dot" />
                      <span>{cat.label}</span>
                    </a>
                  ))}
                </div>
              </div>
            </li>

            <li className="pm-sb-row" style={{ '--delay': '0.15s' }}>
              <a
                href="/lecture"
                className="pm-sb-nav-btn"
                onClick={(e) => {
                  e.preventDefault();
                  if (onClose) onClose();
                  if (onNavigate) onNavigate('/lecture');
                }}
              >
                <span className="pm-sb-icon-circle">
                  <ion-icon name="play-circle-outline"></ion-icon>
                </span>
                <span className="pm-sb-nav-text">My Courses</span>
                <span className="pm-sb-pro-pill">PRO</span>
              </a>
            </li>

            <li className="pm-sb-row" style={{ '--delay': '0.18s' }}>
              <a
                href="/app-download"
                className="pm-sb-nav-btn"
                onClick={(e) => {
                  e.preventDefault();
                  if (onClose) onClose();
                  if (onNavigate) onNavigate('/app-download');
                }}
              >
                <span className="pm-sb-icon-circle">
                  <ion-icon name="phone-portrait-outline"></ion-icon>
                </span>
                <span className="pm-sb-nav-text">App Version</span>
                <ion-icon
                  name="chevron-forward-outline"
                  className="pm-sb-arrow-right"
                ></ion-icon>
              </a>
            </li>

            <li className="pm-sb-row" style={{ '--delay': '0.2s' }}>
              <a
                href="#footer-contact"
                className="pm-sb-nav-btn"
                onClick={(e) =>
                  handleSectionScroll(e, 'footer-contact', '/contact')
                }
              >
                <span className="pm-sb-icon-circle">
                  <ion-icon name="chatbubble-ellipses-outline"></ion-icon>
                </span>
                <span className="pm-sb-nav-text">Contact &amp; Help</span>
                <ion-icon
                  name="chevron-forward-outline"
                  className="pm-sb-arrow-right"
                ></ion-icon>
              </a>
            </li>

            {(isAdmin || user?.email === 'premkumar@premiummind.in') && (
              <li className="pm-sb-row" style={{ '--delay': '0.24s' }}>
                <a
                  href="https://premind.diplomawallah.in/admin_panel.php"
                  className="pm-sb-nav-btn pm-sb-admin-row"
                >
                  <span className="pm-sb-icon-circle">
                    <ion-icon name="settings-outline"></ion-icon>
                  </span>
                  <span className="pm-sb-nav-text">Admin Panel</span>
                </a>
              </li>
            )}

            <li className="pm-sb-row" style={{ '--delay': '0.28s' }}>
              <a
                href="/termcondition"
                className="pm-sb-nav-btn"
                onClick={(e) => {
                  e.preventDefault();
                  if (onClose) onClose();
                  if (onNavigate) onNavigate('/termcondition');
                }}
              >
                <span className="pm-sb-icon-circle">
                  <ion-icon name="shield-checkmark-outline"></ion-icon>
                </span>
                <span className="pm-sb-nav-text">Privacy Policy</span>
                <ion-icon
                  name="chevron-forward-outline"
                  className="pm-sb-arrow-right"
                ></ion-icon>
              </a>
            </li>
          </ul>
        </div>

        {/* Bottom Action Strip */}
        <div className="pm-sb-bottom-bar">
          {user ? (
            <button
              type="button"
              className="pm-sb-logout-action"
              onClick={() => {
                setCustomAvatar(null);
                if (onLogout) onLogout();
                if (onClose) onClose();
              }}
            >
              <ion-icon name="log-out-outline"></ion-icon>
              <span>Logout Account</span>
            </button>
          ) : (
            <a
              href="https://wa.me/918709911016"
              target="_blank"
              rel="noreferrer"
              className="pm-sb-wa-action"
            >
              <ion-icon name="logo-whatsapp"></ion-icon>
              <span>WhatsApp Support</span>
            </a>
          )}
        </div>
      </aside>
    </>
  );
}