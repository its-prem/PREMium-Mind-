import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { MOCK_TESTS } from '../data/mockTests';
import { goBackOrHome } from '../lib/goBack';
import { cloudGet, cloudPut, stampGet, stampSet } from '../lib/cloud';
import './Tools.css';

const BEST_KEY = 'pm_mock_best';
const LETTERS = ['A', 'B', 'C', 'D', 'E'];

const readBest = () => {
  try {
    return JSON.parse(localStorage.getItem(BEST_KEY) || '{}');
  } catch {
    return {};
  }
};

const SHOW_TESTS = false; // false = the page shows "Coming soon" instead of the test list

const fmtTime = (s) => `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;

export default function MockTests() {
  const navigate = useNavigate();
  const [best, setBest] = useState(readBest);
  const [test, setTest] = useState(null); // running test
  const [answers, setAnswers] = useState([]);
  const [index, setIndex] = useState(0);
  const [left, setLeft] = useState(0);
  const [done, setDone] = useState(false);
  const [showNav, setShowNav] = useState(false); // the question-number sheet
  const doneRef = useRef(false);

  useEffect(() => {
    document.title = 'Mock Tests | PREMium Mind';
    window.scrollTo(0, 0);
  }, []);

  // Best scores follow the student's account: take the account's copy if it is newer, or upload ours
  useEffect(() => {
    let dead = false;
    cloudGet('mock').then((remote) => {
      if (dead) return;
      if (remote && remote.data && typeof remote.data === 'object' && remote.ts > stampGet('mock')) {
        const merged = { ...readBest() };
        Object.entries(remote.data).forEach(([k, v]) => {
          if (!(merged[k] >= v)) merged[k] = v;
        });
        try {
          localStorage.setItem(BEST_KEY, JSON.stringify(merged));
        } catch {
          /* private mode */
        }
        stampSet('mock', remote.ts);
        setBest(merged);
      } else if (!remote && Object.keys(readBest()).length) {
        cloudPut('mock', readBest(), 0);
      }
    });
    return () => {
      dead = true;
    };
  }, []);

  const start = (t) => {
    setTest(t);
    setAnswers(Array(t.questions.length).fill(null));
    setIndex(0);
    setLeft(t.minutes * 60);
    setDone(false);
    doneRef.current = false;
    window.scrollTo(0, 0);
  };

  const finish = useCallback(
    (t, ans) => {
      if (doneRef.current) return;
      doneRef.current = true;
      const score = ans.reduce((n, a, i) => n + (a === t.questions[i].answer ? 1 : 0), 0);
      setDone(true);
      setBest((cur) => {
        const next = { ...cur };
        if (!(next[t.id] >= score)) next[t.id] = score;
        try {
          localStorage.setItem(BEST_KEY, JSON.stringify(next));
        } catch {
          /* private mode */
        }
        stampSet('mock');
        cloudPut('mock', next);
        return next;
      });
      window.scrollTo(0, 0);
    },
    []
  );

  useEffect(() => {
    if (!test || done) return undefined;
    const id = setInterval(() => setLeft((s) => s - 1), 1000);
    return () => clearInterval(id);
  }, [test, done]);

  useEffect(() => {
    if (test && !done && left <= 0) finish(test, answers);
  }, [left, test, done, answers, finish]);

  const back = () => {
    if (test && !done && !window.confirm('Test beech me chhod doge? Progress save nahi hogi.')) return;
    if (test) setTest(null);
    else goBackOrHome(navigate);
  };

  /* ---------- Picker ---------- */
  if (!test) {
    return (
      <div className="tl-page">
        <header className="tl-top">
          <button type="button" className="tl-back" aria-label="Back to store" onClick={back}>
            <ion-icon name="arrow-back-outline"></ion-icon>
          </button>
          <div>
            <h1>Mock Test Series</h1>
            <p>Timed practice tests, instant result</p>
          </div>
          <img className="tl-logo" src="/small-logo.png" alt="" />
        </header>
        <main className="tl-wrap">
          {/* The test list is switched off for now. To show it again, set SHOW_TESTS to true (top of this file). */}
          {SHOW_TESTS ? (
            <>
              <div className="tl-grid">
                {MOCK_TESTS.map((t) => (
                  <button key={t.id} type="button" className="tl-pick" style={{ '--tint': t.tint }} onClick={() => start(t)}>
                    <span className="tl-pick-icon"><ion-icon name={t.icon}></ion-icon></span>
                    <b>{t.title}</b>
                    <span className="tl-sub">
                      {t.questions.length} questions · {t.minutes} min
                    </span>
                    {best[t.id] != null ? (
                      <span className="tl-chip">Best: {best[t.id]}/{t.questions.length}</span>
                    ) : (
                      <span className="tl-chip">FREE</span>
                    )}
                  </button>
                ))}
              </div>
              <p className="tl-help">Aur subjects ke tests jaldi add honge.</p>
            </>
          ) : (
            <div className="tl-panel" style={{ textAlign: 'center', padding: '48px 20px' }}>
              <h2>Coming soon</h2>
              <p className="tl-help">Mock tests are on the way. Please check back soon.</p>
            </div>
          )}
        </main>
      </div>
    );
  }

  const total = test.questions.length;
  const score = answers.reduce((n, a, i) => n + (a === test.questions[i].answer ? 1 : 0), 0);
  const answered = answers.filter((a) => a !== null).length;

  /* ---------- Result + review ---------- */
  if (done) {
    const pct = Math.round((score / total) * 100);
    return (
      <div className="tl-page">
        <header className="tl-top">
          <button type="button" className="tl-back" aria-label="Back to tests" onClick={back}>
            <ion-icon name="arrow-back-outline"></ion-icon>
          </button>
          <div>
            <h1>{test.title}</h1>
            <p>Result</p>
          </div>
        </header>
        <main className="tl-wrap">
          <div className="tl-panel">
            <div className="tl-result" style={{ marginTop: 0 }}>
              <div className="tl-stat"><small>Score</small><b>{score} / {total}</b></div>
              <div className="tl-stat"><small>Percentage</small><b>{pct}%</b></div>
              <div className="tl-stat"><small>Attempted</small><b>{answered} / {total}</b></div>
            </div>
            <p className="tl-msg" style={{ marginBottom: 14 }}>
              {pct >= 80 ? 'Shandaar! Tum is topic me strong ho.' : pct >= 50 ? 'Achha hai, thodi aur practice se top score aa jayega.' : 'Is topic ko ek baar aur padho, phir dobara try karo.'}
            </p>
            <div className="tl-row">
              <button type="button" className="tl-btn" onClick={() => start(test)}>
                <ion-icon name="refresh-outline"></ion-icon> Retry
              </button>
              <button type="button" className="tl-btn is-ghost" onClick={() => setTest(null)}>
                All tests
              </button>
            </div>
          </div>

          <div className="tl-panel" style={{ marginTop: 14 }}>
            <h2>Answer review</h2>
            {test.questions.map((q, qi) => (
              <div className="mt-review-item" key={qi}>
                <p className="mt-q">{qi + 1}. {q.q}</p>
                <div className="mt-opts">
                  {q.options.map((o, oi) => {
                    const right = oi === q.answer;
                    const wrong = answers[qi] === oi && !right;
                    return (
                      <div key={oi} className={`mt-opt ${right ? 'is-right' : ''} ${wrong ? 'is-wrong' : ''}`}>
                        <b>{LETTERS[oi]}</b> {o}
                      </div>
                    );
                  })}
                </div>
                {answers[qi] === null && <p className="tl-help" style={{ margin: 0 }}>Not attempted</p>}
                <p className="mt-why">{q.why}</p>
              </div>
            ))}
          </div>
        </main>
      </div>
    );
  }

  /* ---------- Running ---------- */
  const q = test.questions[index];
  return (
    <div className="tl-page">
      <header className="tl-top">
        <button type="button" className="tl-back" aria-label="Quit test" onClick={back}>
          <ion-icon name="close-outline"></ion-icon>
        </button>
        <div style={{ flex: 1 }}>
          <h1>{test.title}</h1>
          <p>Question {index + 1} of {total}</p>
        </div>
        <button type="button" className="mt-navbtn" aria-label="All questions" aria-expanded={showNav} onClick={() => setShowNav((v) => !v)}>
          <ion-icon name="grid-outline"></ion-icon>
          <span>{answered}/{total}</span>
        </button>
        <span className={`mt-timer ${left <= 60 ? 'is-low' : ''}`}>{fmtTime(Math.max(0, left))}</span>
      </header>

      {/* question numbers: opens from the grid button, tap a number to jump */}
      {showNav && (
        <div className="mt-sheet-back" onClick={() => setShowNav(false)}>
          <div className="mt-sheet" role="dialog" aria-label="Questions" onClick={(e) => e.stopPropagation()}>
            <div className="mt-sheet-head">
              <b>Questions</b>
              <span>{answered} of {total} answered</span>
              <button type="button" aria-label="Close" onClick={() => setShowNav(false)}>
                <ion-icon name="close-outline"></ion-icon>
              </button>
            </div>
            <div className="mk-dots">
              {test.questions.map((_, i) => (
                <button
                  key={i}
                  type="button"
                  className={`mk-dot ${answers[i] !== null ? 'is-done' : ''} ${i === index ? 'is-now' : ''}`}
                  onClick={() => {
                    setIndex(i);
                    setShowNav(false);
                  }}
                  aria-label={`Go to question ${i + 1}`}
                >
                  {i + 1}
                </button>
              ))}
            </div>
          </div>
        </div>
      )}

      <main className="tl-wrap">
        <div className="mt-progress"><i style={{ width: `${(answered / total) * 100}%` }} /></div>
        <div className="tl-panel">
          <p className="mt-q">{index + 1}. {q.q}</p>
          <div className="mt-opts">
            {q.options.map((o, oi) => (
              <button
                key={oi}
                type="button"
                className={`mt-opt ${answers[index] === oi ? 'is-picked' : ''}`}
                onClick={() => setAnswers(answers.map((a, i) => (i === index ? oi : a)))}
              >
                <b>{LETTERS[oi]}</b> {o}
              </button>
            ))}
          </div>

          <div className="tl-row">
            <button type="button" className="tl-btn is-ghost" disabled={index === 0} onClick={() => setIndex(index - 1)}>
              <ion-icon name="arrow-back-outline"></ion-icon> Prev
            </button>
            {index < total - 1 ? (
              <button type="button" className="tl-btn" onClick={() => setIndex(index + 1)}>
                Next <ion-icon name="arrow-forward-outline"></ion-icon>
              </button>
            ) : null}
            <button
              type="button"
              className="tl-btn"
              style={{ marginLeft: 'auto', background: '#dc2626' }}
              onClick={() => {
                if (answered < total && !window.confirm(`${total - answered} question abhi baaki hain. Submit karein?`)) return;
                finish(test, answers);
              }}
            >
              Submit test
            </button>
          </div>
        </div>
      </main>
    </div>
  );
}
