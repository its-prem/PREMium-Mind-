import React, { useEffect, useState, useRef } from 'react';
import './Navbar.css';

const STORE_CATEGORIES = [
  {
    id: 'notes',
    title: 'Notes',
    desc: 'Handwritten, unit-wise',
    icon: 'document-text-outline',
  },
  {
    id: 'ebooks',
    title: 'E-Books',
    desc: 'Complete syllabus guides',
    icon: 'book-outline',
  },
  {
    id: 'resume',
    title: 'Resume Templates',
    desc: 'Editable, ATS-ready',
    icon: 'briefcase-outline',
  },
  {
    id: 'important',
    title: 'Important Questions',
    desc: 'Exam-focused expected Qs',
    icon: 'help-circle-outline',
  },
];

export default function Navbar({
  activeNav = 'home',
  onNavChange,
  onOpenSidebar,
  onSelectCategory,
  onNavigate,
}) {
  const [scrolled, setScrolled] = useState(false);
  const [storeOpen, setStoreOpen] = useState(false);
  const [logoSrc, setLogoSrc] = useState('/PM-LOGO-TEXT.png');
  const dropdownRef = useRef(null);

  useEffect(() => {
    const handleScroll = () => {
      setScrolled(window.scrollY > 14);
    };
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  // Close dropdown on outside click or Escape key
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
        setStoreOpen(false);
      }
    };
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') setStoreOpen(false);
    };

    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, []);

  const handleNavClick = (e, tabName, targetId) => {
    e.preventDefault();
    setStoreOpen(false);
    if (onNavChange) onNavChange(tabName);

    if (targetId === 'top') {
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }

    const section = document.getElementById(targetId);
    if (section) {
      section.scrollIntoView({ behavior: 'smooth' });
    } else if (onNavigate) {
      onNavigate(`/#${targetId}`);
    }
  };

  const handleCategorySelect = (e, catId) => {
    e.preventDefault();
    setStoreOpen(false);
    if (onNavChange) onNavChange('store');
    if (onSelectCategory) onSelectCategory(catId);

    const storeSection = document.getElementById('our-store');
    if (storeSection) {
      storeSection.scrollIntoView({ behavior: 'smooth' });
    } else if (onNavigate) {
      onNavigate('/#our-store');
    }
  };

  const handleAppVersion = (e) => {
    e.preventDefault();
    setStoreOpen(false);
    if (onNavChange) onNavChange('app');
    if (onNavigate) onNavigate('/app-download');
  };

  const handleMyCourses = (e) => {
    e.preventDefault();
    setStoreOpen(false);
    if (onNavChange) onNavChange('courses');
    if (onNavigate) onNavigate('/lecture');
  };

  return (
    <header className={`pm-edge-header ${scrolled ? 'is-scrolled' : ''}`}>
      <div className="pm-edge-inner">
        {/* Left: Brand Logo */}
        <a
          href="#top"
          className="pm-edge-brand"
          onClick={(e) => handleNavClick(e, 'home', 'top')}
        >
          <img
            src={logoSrc}
            alt="PREMium Mind"
            className="pm-edge-logo"
            onError={() => setLogoSrc('/small-logo.png')}
          />
        </a>

        {/* Center: Clean Nav Links */}
        <nav className="pm-edge-nav" aria-label="Primary Navigation">
          <a
            href="#top"
            className={`pm-edge-link ${activeNav === 'home' ? 'active' : ''}`}
            onClick={(e) => handleNavClick(e, 'home', 'top')}
          >
            <ion-icon name="home-outline"></ion-icon>
            <span>Home</span>
          </a>

          {/* Click-Only Store Dropdown */}
          <div
            ref={dropdownRef}
            className={`pm-edge-dropdown ${storeOpen ? 'open' : ''}`}
          >
            <button
              type="button"
              className={`pm-edge-link ${
                activeNav === 'store' || storeOpen ? 'active' : ''
              }`}
              onClick={() => setStoreOpen((prev) => !prev)}
              aria-expanded={storeOpen}
            >
              <ion-icon name="bag-handle-outline"></ion-icon>
              <span>PREMium Store</span>
              <ion-icon
                name="chevron-down-outline"
                className="pm-caret"
              ></ion-icon>
            </button>

            <div className="pm-edge-menu">
              <div className="pm-menu-top">
                <span>Browse Store</span>
                <span className="pm-menu-badge">NEW</span>
              </div>

              {STORE_CATEGORIES.map((item) => (
                <a
                  key={item.id}
                  href={`#${item.id}`}
                  className="pm-menu-item"
                  onClick={(e) => handleCategorySelect(e, item.id)}
                >
                  <div className="pm-menu-icon">
                    <ion-icon name={item.icon}></ion-icon>
                  </div>
                  <div className="pm-menu-copy">
                    <strong>{item.title}</strong>
                    <small>{item.desc}</small>
                  </div>
                </a>
              ))}
            </div>
          </div>

          <a
            href="/lecture"
            className={`pm-edge-link ${
              activeNav === 'courses' ? 'active' : ''
            }`}
            onClick={handleMyCourses}
          >
            <ion-icon name="play-circle-outline"></ion-icon>
            <span>My Courses</span>
          </a>

          <a
            href="/app-download"
            className={`pm-edge-link ${activeNav === 'app' ? 'active' : ''}`}
            onClick={handleAppVersion}
          >
            <ion-icon name="phone-portrait-outline"></ion-icon>
            <span>App Version</span>
          </a>

          <a
            href="#footer-contact"
            className={`pm-edge-link ${
              activeNav === 'contact' ? 'active' : ''
            }`}
            onClick={(e) => handleNavClick(e, 'contact', 'footer-contact')}
          >
            <ion-icon name="chatbubble-ellipses-outline"></ion-icon>
            <span>Contact</span>
          </a>
        </nav>

        {/* Right: Cool Animated Emoji Profile Pill + Menu Button */}
        <div className="pm-edge-right">
          <button
            type="button"
            className="pm-burger-box"
            onClick={onOpenSidebar}
            aria-label="Open Navigation Menu"
          >
            <span className="b-line top" />
            <span className="b-line bottom" />
          </button>
        </div>
      </div>
    </header>
  );
}