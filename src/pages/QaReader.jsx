/**
 * Important Questions: the buyer reads the questions & answers here  (/qa/:id).
 * Above the text there is the "Micro format" button: pay once for it (its own price and terms, set in the admin),
 * then open the A4 boxes page (/microsheet/:id).
 */
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import CheckoutModal from '../components/CheckoutModal';
import { SITE_API } from '../lib/api';
import { DEFAULT_MICRO_TNC } from '../lib/defaultTerms';
import { downloadState, formatWhen } from '../lib/downloadMode';
import { parseQuestions, splitLine } from '../lib/qaParse';
import { getPdfSession } from '../lib/session';
import './Watch.css'; // the header below is the PDF reader's header (same look)
import './QaReader.css';

const THEME_KEY = 'pm_reader_dark'; // the same light / dark choice as the PDF reader

export default function QaReader() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [state, setState] = useState({ status: 'loading' });
  const [checkout, setCheckout] = useState(null);
  const [tipOpen, setTipOpen] = useState(false); // the small "Micro format" message under the icon
  const tipRef = useRef(null);
  useEffect(() => {
    if (!tipOpen) return undefined;
    const close = (e) => {
      if (tipRef.current && !tipRef.current.contains(e.target)) setTipOpen(false);
    };
    const esc = (e) => e.key === 'Escape' && setTipOpen(false);
    document.addEventListener('mousedown', close);
    document.addEventListener('touchstart', close);
    document.addEventListener('keydown', esc);
    return () => {
      document.removeEventListener('mousedown', close);
      document.removeEventListener('touchstart', close);
      document.removeEventListener('keydown', esc);
    };
  }, [tipOpen]);
  const [dark, setDark] = useState(() => {
    try {
      return localStorage.getItem(THEME_KEY) === '1';
    } catch {
      return false;
    }
  });
  const toggleDark = () =>
    setDark((prev) => {
      const next = !prev;
      try {
        localStorage.setItem(THEME_KEY, next ? '1' : '0');
      } catch {
        /* storage blocked */
      }
      return next;
    });

  const load = useCallback(async () => {
    const token = getPdfSession();
    if (!token) {
      navigate('/login', { replace: true });
      return;
    }
    try {
      const res = await fetch(`${SITE_API}/qa_text.php?course_id=${encodeURIComponent(id)}`, { headers: { 'X-PM-Session': token }, cache: 'no-store' });
      const data = await res.json().catch(() => ({}));
      if (res.status === 401) {
        navigate('/login', { replace: true });
        return;
      }
      if (!res.ok || data.status !== 'success') {
        setState({ status: 'error', code: data.code || '', message: data.message || 'Could not open this page.' });
        return;
      }
      setState({ status: 'ready', title: data.title, text: data.text, pdf: data.pdf || '', micro: data.micro });
    } catch {
      setState({ status: 'error', message: 'Could not reach the server. Check your internet and try again.' });
    }
  }, [id, navigate]);

  useEffect(() => {
    document.title = 'Important Questions | PREMium Mind';
    load();
  }, [load]);

  const questions = useMemo(() => (state.status === 'ready' ? parseQuestions(state.text) : []), [state]);

  if (state.status === 'loading') {
    return (
      <main className="qa-page">
        <div className="qa-wrap">
          <div className="qa-skel" style={{ height: 70 }} />
          <div className="qa-skel" style={{ height: 120, marginTop: 14 }} />
          <div className="qa-skel" style={{ height: 220, marginTop: 14 }} />
        </div>
      </main>
    );
  }

  if (state.status === 'error') {
    return (
      <main className="qa-page">
        <div className="qa-wrap">
          <div className="qa-card qa-center">
            <h2>{state.code === 'not_owned' ? 'Buy this product to read it' : 'Cannot open this page'}</h2>
            <p>{state.message}</p>
            <div className="qa-actions">
              {state.code === 'not_owned' ? (
                <Link to="/#our-store" className="qa-btn dark">
                  Go to store
                </Link>
              ) : (
                <button type="button" className="qa-btn dark" onClick={load}>
                  Try again
                </button>
              )}
              <Link to="/lecture" className="qa-btn">
                My Courses
              </Link>
            </div>
          </div>
        </div>
      </main>
    );
  }

  const { micro, title } = state;
  const dl = downloadState({ allow_download: micro.allowDownload, download_at: micro.downloadAt });
  const dlText = dl.mode === 'instant' ? 'download right after paying' : dl.mode === 'timed' ? `download from ${formatWhen(dl.at)}` : 'view only, no download';

  const openPdf = () => {
    try {
      sessionStorage.setItem('pmWatchReturn', `/qa/${id}`);
    } catch {
      /* storage blocked */
    }
    navigate(`/watch?${new URLSearchParams({ file: state.pdf, title }).toString()}`);
  };

  const openMicroCheckout = () =>
    setCheckout({
      courseId: Number(id),
      kind: 'micro',
      title: `${title} — Micro format`,
      price: micro.price,
      mrp: 0,
      showTnc: micro.showTnc,
      tncText: micro.tncText || DEFAULT_MICRO_TNC, // the micro format has its own terms
      download: { allow_download: micro.allowDownload, download_at: micro.downloadAt },
      slideToPay: true, // slide the handle to pay
      openLabel: 'Open micro format',
      doneText: 'Your micro format is unlocked.',
    });

  return (
    <main className={`qa-page${dark ? ' is-dark' : ''}`}>
      {/* the same header as the PDF reader: back · PREMium Mind + title · dark mode and the two buttons */}
      <div className={`rd-root qa-hdr${dark ? ' is-dark' : ''}`}>
        <header className="rd-header">
          <div className="rd-header-start">
            <button type="button" className="rd-back" aria-label="Back to My Courses" onClick={() => navigate('/lecture')}>
              <span className="rd-back-icon" aria-hidden="true">
                <ion-icon name="chevron-back-outline"></ion-icon>
              </span>
              <span>My Courses</span>
            </button>
          </div>

          <div className="rd-brand">
            PREM<em>ium</em> Mind
            <span>{title || 'Important Questions'}</span>
          </div>

          <div className="rd-header-end">
            <button type="button" className="rd-theme" aria-label={dark ? 'Switch to light mode' : 'Switch to dark mode'} aria-pressed={dark} title={dark ? 'Light mode' : 'Dark mode'} onClick={toggleDark}>
              <ion-icon name={dark ? 'sunny-outline' : 'moon-outline'}></ion-icon>
            </button>
            {state.pdf && (
              <button type="button" className="rd-theme" aria-label="Open the PDF" title="Open the PDF" onClick={openPdf}>
                <ion-icon name="document-text-outline"></ion-icon>
              </button>
            )}
            {micro.enabled && (
              <div className="qa-micro-wrap" ref={tipRef}>
                <button
                  type="button"
                  className={`rd-theme qa-micro-btn${micro.owned ? ' owned' : ''}${tipOpen ? ' open' : ''}`}
                  onClick={() => setTipOpen((v) => !v)}
                  aria-expanded={tipOpen}
                  aria-haspopup="dialog"
                  aria-label="Micro format"
                >
                  <svg className="qa-micro-ico" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <rect x="4.5" y="2.5" width="15" height="19" rx="2" />
                  <g strokeWidth="1.2">
                    <rect x="7.2" y="5.4" width="3" height="3.2" rx="0.5" />
                    <rect x="10.5" y="5.4" width="3" height="3.2" rx="0.5" />
                    <rect x="13.8" y="5.4" width="3" height="3.2" rx="0.5" />
                    <rect x="7.2" y="9.2" width="3" height="3.2" rx="0.5" />
                    <rect x="10.5" y="9.2" width="3" height="3.2" rx="0.5" />
                    <rect x="13.8" y="9.2" width="3" height="3.2" rx="0.5" />
                    <rect x="7.2" y="13" width="3" height="3.2" rx="0.5" />
                    <rect x="10.5" y="13" width="3" height="3.2" rx="0.5" />
                    <rect x="13.8" y="13" width="3" height="3.2" rx="0.5" />
                  </g>
                  <path d="M7.4 18.6h9.2" />
                </svg>
                  <em className={`qa-micro-price${micro.owned ? ' ok' : ''}`}>{micro.owned ? '✓' : `₹${micro.price}`}</em>
                </button>

                {/* the message that opens under the icon */}
                {tipOpen && (
                  <div className="qa-pop" role="dialog" aria-label="Micro format">
                    <b>Micro format</b>
                    <p>
                      All these questions in small A4 boxes, ready to print.
                      <br />
                      <span>{dlText.charAt(0).toUpperCase() + dlText.slice(1)}.</span>
                    </p>
                    {micro.owned ? (
                      <button
                        type="button"
                        className="qa-pop-btn owned"
                        onClick={() => {
                          setTipOpen(false);
                          navigate(`/microsheet/${id}`);
                        }}
                      >
                        Open micro format
                      </button>
                    ) : (
                      <button
                        type="button"
                        className="qa-pop-btn"
                        onClick={() => {
                          setTipOpen(false);
                          openMicroCheckout();
                        }}
                      >
                        Buy for ₹{micro.price}
                      </button>
                    )}
                  </div>
                )}
              </div>
            )}
          </div>
        </header>
      </div>

      <div className="qa-wrap">
        {questions.length > 1 && (
          <nav className="qa-index" aria-label="Questions">
            {questions.map((q, i) => (
              <a key={i} href={`#q-${i + 1}`}>
                {q.label || i + 1}
              </a>
            ))}
          </nav>
        )}

        {questions.map((q, i) => (
          <article key={i} id={`q-${i + 1}`} className="qa-card">
            <div className="qa-q">
              {q.label && <span className="qa-no">{q.label}</span>}
              <h2>{q.title}</h2>
            </div>
            <div className="qa-a">
              {q.lines.map((line, j) => {
                const p = splitLine(line);
                return (
                  <p key={j} className={p.num ? 'has-num' : ''}>
                    {p.num && <span className="qa-num">{p.num}</span>}
                    <span>
                      {p.head && <strong>{p.head} </strong>}
                      {p.body}
                    </span>
                  </p>
                );
              })}
            </div>
          </article>
        ))}
      </div>

      {checkout && (
        <CheckoutModal
          item={checkout}
          onClose={() => {
            setCheckout(null);
            load(); // the buyer may have just paid: show the new state
          }}
          onOpen={() => navigate(`/microsheet/${id}`)}
        />
      )}
    </main>
  );
}
