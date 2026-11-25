import React, { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Navbar from '../components/Navbar';
import Sidebar from '../components/Sidebar';
import {
  clearStoredSession,
  getStoredEmail,
  getStoredName,
} from '../lib/session';
import '../styles/theme.css';
import './Terms.css';

const SUPPORT_PHONE_DISPLAY = '+91 8709911016';
const SUPPORT_WHATSAPP = 'https://wa.me/918709911016';

const SECTIONS = [
  {
    id: 'acceptance',
    icon: 'checkmark-done-outline',
    title: 'Acceptance of Terms',
    body: [
      'By using our services, you confirm that you accept these terms and agree to abide by them. If you do not agree, please refrain from using our website.',
    ],
  },
  {
    id: 'services',
    icon: 'cloud-download-outline',
    title: 'Services & Digital Goods',
    body: [
      'We provide educational content, resume templates, and resources. All purchases are for personal use only and must not be resold or distributed without written permission.',
    ],
  },
  {
    id: 'payments',
    icon: 'card-outline',
    title: 'Payments',
    body: [
      'All payments are processed securely via third-party gateways. Once a digital product is purchased, it is delivered instantly to your account or email.',
    ],
  },
  {
    id: 'refund',
    icon: 'return-down-back-outline',
    title: 'Refund Policy',
    body: [
      'Because our products are digital and delivered instantly, we generally do not offer refunds. However, exceptions are made for duplicate payments or technical errors during the transaction.',
    ],
  },
  {
    id: 'responsibilities',
    icon: 'person-circle-outline',
    title: 'User Responsibilities',
    list: [
      'You must provide accurate information during registration.',
      'You are responsible for maintaining the confidentiality of your account.',
      'Sharing course access or premium materials with others is strictly prohibited.',
    ],
  },
];

export default function Terms() {
  const navigate = useNavigate();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [email] = useState(() => getStoredEmail());
  const [name] = useState(() => getStoredName());

  const user = useMemo(
    () => (email ? { name: name || 'User', email } : null),
    [email, name]
  );

  useEffect(() => {
    window.scrollTo(0, 0);
  }, []);

  const handleLogout = () => {
    clearStoredSession();
    setSidebarOpen(false);
    navigate('/');
  };

  return (
    <div className="tc-page">
      <Navbar
        activeNav=""
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

      {/* ================= HERO ================= */}
      <header className="tc-hero">
        <div className="tc-hero-inner">
          <span className="tc-hero-badge">
            <ion-icon name="shield-checkmark-outline"></ion-icon> Privacy &amp;
            Policy
          </span>
          <h1>Terms &amp; Conditions</h1>
          <p>Professional standards &amp; user agreements</p>
        </div>
      </header>

      {/* ================= CONTENT ================= */}
      <main className="tc-card">
        <p className="tc-intro">
          Welcome to <strong>PREMium Mind</strong>. By accessing or using our
          website, you agree to comply with and be bound by the following terms
          and conditions.
        </p>

        <nav className="tc-toc" aria-label="Sections">
          {SECTIONS.map((s, i) => (
            <a key={s.id} href={`#${s.id}`}>
              <span>{i + 1}</span> {s.title}
            </a>
          ))}
          <a href="#contact">
            <span>{SECTIONS.length + 1}</span> Contact Us
          </a>
        </nav>

        {SECTIONS.map((s, i) => (
          <section key={s.id} id={s.id} className="tc-section">
            <div className="tc-section-head">
              <span className="tc-num">{i + 1}</span>
              <h2>{s.title}</h2>
              <ion-icon name={s.icon}></ion-icon>
            </div>
            {s.body?.map((text) => (
              <p key={text}>{text}</p>
            ))}
            {s.list && (
              <ul>
                {s.list.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            )}
          </section>
        ))}

        <section id="contact" className="tc-section tc-contact">
          <div className="tc-section-head">
            <span className="tc-num">{SECTIONS.length + 1}</span>
            <h2>Contact Us</h2>
            <ion-icon name="chatbubble-ellipses-outline"></ion-icon>
          </div>
          <p>
            For any queries regarding these terms, please contact our support
            team via WhatsApp at <strong>{SUPPORT_PHONE_DISPLAY}</strong> or
            use the help form on our home page.
          </p>
          <div className="tc-contact-actions">
            <a
              href={SUPPORT_WHATSAPP}
              target="_blank"
              rel="noreferrer"
              className="tc-btn-wa"
            >
              <ion-icon name="logo-whatsapp"></ion-icon> WhatsApp Support
            </a>
            <Link to="/#footer-contact" className="tc-btn-ghost">
              Help form
            </Link>
          </div>
        </section>
      </main>

      <footer className="tc-footer">
        <p>
          &copy; {new Date().getFullYear()} <strong>PREMium Mind</strong>
          <span>•</span>
          <Link to="/">Back to Home</Link>
        </p>
      </footer>
    </div>
  );
}
