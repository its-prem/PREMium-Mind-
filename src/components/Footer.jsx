import React, { useState, useEffect, useRef } from "react";
import { Link } from "react-router-dom";
import "./Footer.css";

const PHONE_DISPLAY = "+91 87099 11016";
const PHONE_RAW = "918709911016";

export default function Footer() {
  const [logoSrc, setLogoSrc] = useState("/PM-LOGO-TEXT.png");
  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [message, setMessage] = useState("");
  const [sent, setSent] = useState(false);
  const [copied, setCopied] = useState(false);
  const [isVisible, setIsVisible] = useState(false);
  const footerRef = useRef(null);

  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setIsVisible(true);
          observer.disconnect();
        }
      },
      { threshold: 0.1 }
    );
    if (footerRef.current) observer.observe(footerRef.current);
    return () => observer.disconnect();
  }, []);

  const scrollToTop = (e) => {
    e.preventDefault();
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const handleCopyNumber = (e) => {
    e.preventDefault();
    if (navigator.clipboard) {
      navigator.clipboard.writeText(`+${PHONE_RAW}`);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    const cleanName = name.trim();
    const cleanPhone = phone.trim();
    const cleanMsg = message.trim();

    if (!cleanName || cleanPhone.length < 10 || !cleanMsg) return;

    const waText =
      `Hello PREMium Mind! 👋%0A%0A` +
      `*Name:* ${encodeURIComponent(cleanName)}%0A` +
      `*Phone:* ${encodeURIComponent(cleanPhone)}%0A` +
      `*Message:* ${encodeURIComponent(cleanMsg)}`;

    setSent(true);
    setTimeout(() => {
      setSent(false);
      setName("");
      setPhone("");
      setMessage("");
    }, 2400);

    window.open(`https://wa.me/${PHONE_RAW}?text=${waText}`, "_blank");
  };

  const directWa = `https://wa.me/${PHONE_RAW}?text=${encodeURIComponent(
    "Hello PREMium Mind! I need help with your notes / courses."
  )}`;

  return (
    <footer
      ref={footerRef}
      className={`pm-rbw-footer ${isVisible ? "pm-visible" : ""}`}
      id="footer-contact"
    >
      {/* Top Animated Red Laser Line */}
      <div className="pm-rbw-beam" aria-hidden="true" />

      {/* Subtle Dark Grid & Soft Red Ambient Glow */}
      <div className="pm-rbw-bg" aria-hidden="true">
        <div className="pm-rbw-glow-1" />
        <div className="pm-rbw-glow-2" />
        <div className="pm-rbw-grid" />
      </div>

      <div className="pm-rbw-container pm-rbw-main">
        {/* LEFT: Brand, Contact Cards & Social Dock */}
        <div className="pm-rbw-left">
          <div className="pm-rbw-badge">
            <span className="pm-rbw-dot" />
            <span>STUDENT SUPPORT DESK</span>
          </div>

          <a
            href="#top"
            className="pm-rbw-logo-box"
            onClick={scrollToTop}
            aria-label="PREMium Mind Home"
          >
            <img
              src={logoSrc}
              alt="PREMium Mind"
              className="pm-rbw-logo"
              onError={() => setLogoSrc("/small-logo.png")}
            />
          </a>

          <p className="pm-rbw-desc">
            Exam-ready handwritten notes for Polytechnic &amp; Engineering students,
            solved PYQs, ATS resumes and ready-to-submit projects.
          </p>

          {/* Direct Contact Cards */}
          <div className="pm-rbw-contacts">
            <div className="pm-rbw-contact-card">
              <a href={`tel:+${PHONE_RAW}`} className="pm-rbw-c-main">
                <span className="pm-rbw-c-icon">
                  <ion-icon name="call-outline"></ion-icon>
                </span>
                <div className="pm-rbw-c-info">
                  <span className="pm-rbw-c-label">Course Counsellor</span>
                  <span className="pm-rbw-c-val">{PHONE_DISPLAY}</span>
                </div>
              </a>
              <button
                type="button"
                className={`pm-rbw-copy ${copied ? "copied" : ""}`}
                onClick={handleCopyNumber}
                title="Copy Number"
                aria-label="Copy phone number"
              >
                <ion-icon
                  name={copied ? "checkmark-outline" : "copy-outline"}
                ></ion-icon>
              </button>
            </div>

            <a
              href={directWa}
              target="_blank"
              rel="noreferrer"
              className="pm-rbw-contact-card pm-rbw-wa-card"
            >
              <div className="pm-rbw-c-main">
                <span className="pm-rbw-c-icon pm-rbw-icon-white">
                  <ion-icon name="logo-whatsapp"></ion-icon>
                </span>
                <div className="pm-rbw-c-info">
                  <span className="pm-rbw-c-label">Instant Help</span>
                  <span className="pm-rbw-c-val">Chat on WhatsApp</span>
                </div>
              </div>
              <span className="pm-rbw-arrow">
                <ion-icon name="arrow-forward-outline"></ion-icon>
              </span>
            </a>
          </div>

          {/* Social Media Icons */}
          <div className="pm-rbw-socials">
            <a
              href={directWa}
              target="_blank"
              rel="noreferrer"
              className="pm-rbw-soc"
              aria-label="WhatsApp"
              title="WhatsApp"
            >
              <ion-icon name="logo-whatsapp"></ion-icon>
            </a>
            <a
              href="#our-store"
              className="pm-rbw-soc"
              aria-label="YouTube"
              title="YouTube"
            >
              <ion-icon name="logo-youtube"></ion-icon>
            </a>
            <a
              href="#our-store"
              className="pm-rbw-soc"
              aria-label="Instagram"
              title="Instagram"
            >
              <ion-icon name="logo-instagram"></ion-icon>
            </a>
            <a
              href="#our-store"
              className="pm-rbw-soc"
              aria-label="Telegram"
              title="Telegram"
            >
              <ion-icon name="paper-plane-outline"></ion-icon>
            </a>
          </div>
        </div>

        {/* RIGHT: Sleek Matte Black & Red Form Card */}
        <div className="pm-rbw-right" id="help-form">
          <div className="pm-rbw-form-card">
            <div className="pm-rbw-form-head">
              <div>
                <h3>Send Us a Message</h3>
                <p>Any doubt or notes query? Message us directly on WhatsApp</p>
              </div>
              <span className="pm-rbw-head-icon">
                <ion-icon name="logo-whatsapp"></ion-icon>
              </span>
            </div>

            <form onSubmit={handleSubmit} className="pm-rbw-form">
              <div className="pm-rbw-field-row">
                <div className="pm-rbw-field">
                  <label htmlFor="pm-rbw-name">Full Name *</label>
                  <input
                    id="pm-rbw-name"
                    type="text"
                    required
                    placeholder="Enter Full Name"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                  />
                </div>

                <div className="pm-rbw-field">
                  <label htmlFor="pm-rbw-phone">Phone Number *</label>
                  <input
                    id="pm-rbw-phone"
                    type="tel"
                    pattern="[0-9]{10}"
                    maxLength={10}
                    required
                    placeholder="10-Digit Mobile"
                    value={phone}
                    onChange={(e) =>
                      setPhone(e.target.value.replace(/\D/g, ""))
                    }
                  />
                </div>
              </div>

              <div className="pm-rbw-field">
                <label htmlFor="pm-rbw-msg">Your Message *</label>
                <textarea
                  id="pm-rbw-msg"
                  rows="3"
                  required
                  placeholder="Write your query (Notes, Payment, Project)..."
                  value={message}
                  onChange={(e) => setMessage(e.target.value)}
                />
              </div>

              <button type="submit" className="pm-rbw-submit">
                <span className="pm-rbw-shine" aria-hidden="true" />
                <ion-icon name="logo-whatsapp"></ion-icon>
                <span>{sent ? "Opening WhatsApp..." : "Submit on WhatsApp"}</span>
              </button>
            </form>
          </div>
        </div>
      </div>

      {/* Bottom Legal & Back-to-Top Strip */}
      <div className="pm-rbw-bottom">
        <div className="pm-rbw-container pm-rbw-bottom-inner">
          <p className="pm-rbw-copy-text">
            © {new Date().getFullYear()} <strong>PREMium Mind</strong> • Wisdom
            on Demand
          </p>

          <div className="pm-rbw-legal">
            <Link to="/termcondition">Privacy Policy</Link>
            <span className="pm-rbw-sep">|</span>
            <Link to="/termcondition">Terms &amp; Conditions</Link>
            <button
              type="button"
              className="pm-rbw-top-btn"
              onClick={scrollToTop}
              aria-label="Back to top"
              title="Back to top"
            >
              <ion-icon name="arrow-up-outline"></ion-icon>
            </button>
          </div>
        </div>
      </div>
    </footer>
  );
}