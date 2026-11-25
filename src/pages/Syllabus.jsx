import React, { useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { BRANCHES, getBranch, getSubject, listTopics } from '../data/syllabus';
import { goBackOrHome } from '../lib/goBack';
import { loadKept, saveKept } from '../lib/keep';
import { cloudGet, cloudPut, stampGet, stampSet } from '../lib/cloud';
import './Tools.css';
import './Syllabus.css';

/** One subject, compact: a slim progress header and one row per unit. Every topic can be ticked. */
function SubjectView({ subject }) {
  const doneKey = `syllabus-topics-${subject.id}`;
  const topicsAll = listTopics(subject);
  const [open, setOpen] = useState([]); // open unit numbers
  const [done, setDone] = useState(() => loadKept(doneKey, { topics: [] }).topics); // ids of completed topics

  const cloudReady = useRef(false);

  // Remember the ticks on this device, and in the student's account (so they follow to another phone)
  useEffect(() => {
    saveKept(doneKey, { topics: done });
    if (cloudReady.current) {
      stampSet(doneKey);
      cloudPut(doneKey, { topics: done });
    }
  }, [done]); // eslint-disable-line

  // On opening: take the account's copy if it is newer than this device's copy
  useEffect(() => {
    let dead = false;
    cloudGet(doneKey).then((remote) => {
      if (dead) return;
      cloudReady.current = true;
      if (remote && remote.data && Array.isArray(remote.data.topics) && remote.ts > stampGet(doneKey)) {
        stampSet(doneKey, remote.ts);
        setDone(remote.data.topics);
      } else if (done.length && !remote) {
        cloudPut(doneKey, { topics: done }, 0); // first time: upload what this device already has
      }
    });
    return () => {
      dead = true;
    };
  }, []); // eslint-disable-line

  const toggleOpen = (i) => setOpen((cur) => (cur.includes(i) ? cur.filter((x) => x !== i) : [...cur, i]));
  const toggleTopic = (id) => setDone((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]));
  // Tick or untick every topic of one unit at once
  const toggleUnit = (ui) => {
    const ids = topicsAll.filter((t) => t.unit === ui).map((t) => t.id);
    setDone((cur) => (ids.every((id) => cur.includes(id)) ? cur.filter((id) => !ids.includes(id)) : [...new Set([...cur, ...ids])]));
  };

  const total = topicsAll.length;
  const doneCount = topicsAll.filter((t) => done.includes(t.id)).length;
  const percent = total ? Math.round((doneCount / total) * 100) : 0;
  const allOpen = open.length === subject.units.length;
  const semester = subject.semester.replace(/\s*\(.*\)/, '');

  return (
    <>
      <section className="sy-top">
        <span className="sy-top-icon"><ion-icon name={subject.icon}></ion-icon></span>
        <div className="sy-top-main">
          <div className="sy-top-line">
            <h2>{subject.title}</h2>
            <b>{percent}%</b>
          </div>
          <p>
            {semester} · L-T-P {subject.ltp} · {subject.units.length} units · {total} topics
          </p>
          <div className="sy-bar"><i style={{ width: `${percent}%` }} /></div>
          <small>{doneCount} of {total} topics completed</small>
        </div>
      </section>

      <div className="sy-listhead">
        <span>Syllabus</span>
        <button type="button" onClick={() => setOpen(allOpen ? [] : subject.units.map((_, i) => i))}>
          {allOpen ? 'Close all' : 'Open all'}
        </button>
      </div>

      <div className="sy-units">
        {subject.units.map((u, ui) => {
          const isOpen = open.includes(ui);
          const unitTopics = topicsAll.filter((t) => t.unit === ui);
          const unitDone = unitTopics.filter((t) => done.includes(t.id)).length;
          const complete = unitDone === unitTopics.length;
          return (
            <article className={`sy-unit ${isOpen ? 'is-open' : ''} ${complete ? 'is-done' : ''}`} key={u.title}>
              <button type="button" className="sy-unit-main" aria-expanded={isOpen} onClick={() => toggleOpen(ui)}>
                <span className="sy-unit-name">
                  <em>Unit {ui + 1} :</em> {u.title}
                </span>
                <span className="sy-count">
                  {complete ? <ion-icon name="checkmark-circle"></ion-icon> : null}
                  {unitDone}/{unitTopics.length}
                </span>
                <ion-icon name="chevron-down-outline" class="sy-chev"></ion-icon>
              </button>

              {isOpen && (
                <div className="sy-unit-body">
                  {u.sections.map((sec, si) => (
                    <div className="sy-sec" key={si}>
                      {sec.heading && <h4>{sec.heading}</h4>}
                      <ul className="sy-topics">
                        {sec.topics.map((t, ti) => {
                          const id = `${ui}-${si}-${ti}`;
                          const on = done.includes(id);
                          return (
                            <li key={id}>
                              <button type="button" className={`sy-topic ${on ? 'is-on' : ''}`} aria-pressed={on} onClick={() => toggleTopic(id)}>
                                <span className="sy-check"><ion-icon name="checkmark-outline"></ion-icon></span>
                                <span className="sy-topic-text">{t}</span>
                              </button>
                            </li>
                          );
                        })}
                      </ul>
                    </div>
                  ))}
                  <button type="button" className="sy-markall" onClick={() => toggleUnit(ui)}>
                    {complete ? 'Unmark whole unit' : 'Mark whole unit as completed'}
                  </button>
                </div>
              )}
            </article>
          );
        })}
      </div>
    </>
  );
}

/**
 * Diploma syllabus pages:
 *   /syllabus                      -> all branches
 *   /syllabus/:branch              -> subjects of that branch
 *   /syllabus/:branch/:subject     -> unit-wise syllabus
 * The data lives in src/data/syllabus.js.
 */
export default function Syllabus() {
  const navigate = useNavigate();
  const { branch: branchId, subject: subjectId } = useParams();
  const branch = branchId ? getBranch(branchId) : null;
  const subject = subjectId ? getSubject(subjectId) : null;

  useEffect(() => {
    document.title = `${(subject && subject.title) || (branch && branch.title) || 'Diploma Syllabus'} | PREMium Mind`;
    window.scrollTo(0, 0);
  }, [branchId, subjectId]); // eslint-disable-line

  let title = 'Diploma Syllabus';
  let sub = 'Pick your branch';
  if (branch) {
    title = branch.title;
    sub = 'Subjects & syllabus';
  }
  if (branch && subject) {
    title = subject.title;
    sub = branch.title;
  }

  const missing = (branchId && !branch) || (subjectId && (!subject || !branch.subjects.includes(subjectId)));

  return (
    <div className="tl-page">
      <header className="tl-top">
        <button type="button" className="tl-back" aria-label="Back" onClick={() => goBackOrHome(navigate)}>
          <ion-icon name="arrow-back-outline"></ion-icon>
        </button>
        <div>
          <h1>{title}</h1>
          <p>{sub}</p>
        </div>
        <img className="tl-logo" src="/small-logo.png" alt="" />
      </header>

      <main className="tl-wrap">
        {missing && (
          <div className="tl-panel">
            <h2>Not found</h2>
            <p className="tl-help">This page does not exist. Go back and pick your branch again.</p>
            <button type="button" className="tl-btn" onClick={() => navigate('/syllabus', { replace: true })}>
              All branches
            </button>
          </div>
        )}

        {/* ---------- 1. all branches ---------- */}
        {!missing && !branch && (
          <div className="sy-grid">
            {BRANCHES.map((b) => (
              <button
                key={b.id}
                type="button"
                className="sy-card"
                style={{ '--tint': b.tint }}
                onClick={() => navigate(`/syllabus/${b.id}`)}
              >
                <span className="sy-icon"><ion-icon name={b.icon}></ion-icon></span>
                <span className="sy-text">
                  <b>{b.title}</b>
                  <small>{b.desc}</small>
                </span>
                <ion-icon name="chevron-forward-outline" class="sy-go"></ion-icon>
              </button>
            ))}
          </div>
        )}

        {/* ---------- 2. subjects of a branch ---------- */}
        {!missing && branch && !subject && (
          <>
            <div className="sy-hero" style={{ '--tint': branch.tint }}>
              <span className="sy-icon"><ion-icon name={branch.icon}></ion-icon></span>
              <div>
                <h2>{branch.title}</h2>
                <p>{branch.desc}</p>
              </div>
            </div>

            <p className="sy-label">1st Semester</p>
            <div className="sy-grid">
              {branch.subjects.map((sid) => {
                const s = getSubject(sid);
                return (
                  <button
                    key={sid}
                    type="button"
                    className="sy-card"
                    style={{ '--tint': branch.tint }}
                    onClick={() => navigate(`/syllabus/${branch.id}/${sid}`)}
                  >
                    <span className="sy-icon"><ion-icon name={s.icon}></ion-icon></span>
                    <span className="sy-text">
                      <b>{s.title}</b>
                      <small>
                        {s.units.length} units · L-T-P {s.ltp}
                      </small>
                    </span>
                    <ion-icon name="chevron-forward-outline" class="sy-go"></ion-icon>
                  </button>
                );
              })}
            </div>
            <p className="tl-help sy-soon">More subjects for this branch are being added.</p>
          </>
        )}

        {/* ---------- 3. one subject, unit wise ---------- */}
        {!missing && branch && subject && <SubjectView key={subject.id} subject={subject} />}
      </main>
    </div>
  );
}
