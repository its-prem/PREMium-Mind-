import React, { useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { BRANCHES, SEMESTERS, getBranch, getSubject, listTopics, semesterName, subjectsOfSemester } from '../data/syllabus';
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
 *   /syllabus                      -> semesters 1-6, each with its subjects inside
 *   /syllabus/:branch              -> the same list, showing only that branch's subjects (and the common ones)
 *   /syllabus/:branch/:subject     -> unit-wise syllabus of one subject  (:branch can be "all")
 * The data lives in src/data/syllabus.js.
 */
export default function Syllabus() {
  const navigate = useNavigate();
  const { branch: branchId, subject: subjectId } = useParams();
  const branch = branchId && branchId !== 'all' ? getBranch(branchId) : null;
  const subject = subjectId ? getSubject(subjectId) : null;
  const [openSem, setOpenSem] = useState([1]); // semesters that are open

  useEffect(() => {
    document.title = `${(subject && subject.title) || 'Diploma Syllabus'} | PREMium Mind`;
    window.scrollTo(0, 0);
  }, [branchId, subjectId]); // eslint-disable-line

  const missing = (branchId && branchId !== 'all' && !branch) || (subjectId && !subject);
  const title = subject ? subject.title : 'Diploma Syllabus';
  const sub = subject ? subject.semester.replace(/\s*\(.*\)/, '') : 'Semester wise subjects';
  const toggleSem = (n) => setOpenSem((cur) => (cur.includes(n) ? cur.filter((x) => x !== n) : [...cur, n]));

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
            <p className="tl-help">This page does not exist. Go back to the syllabus list.</p>
            <button type="button" className="tl-btn" onClick={() => navigate('/syllabus', { replace: true })}>
              Syllabus
            </button>
          </div>
        )}

        {/* ---------- semesters 1-6, the subjects are inside them ---------- */}
        {!missing && !subject && (
          <>
            <div className="sy-chips" role="group" aria-label="Branch">
              <button type="button" className={`sy-chip${!branch ? ' on' : ''}`} onClick={() => navigate('/syllabus')}>
                All branches
              </button>
              {BRANCHES.map((b) => (
                <button key={b.id} type="button" className={`sy-chip${branch && branch.id === b.id ? ' on' : ''}`} onClick={() => navigate(`/syllabus/${b.id}`)}>
                  {b.title}
                </button>
              ))}
            </div>
            <p className="sy-hint">
              {branch ? `Showing ${branch.title} subjects and the subjects common to every branch.` : 'Open a semester to see its subjects. Pick your branch above to see only yours.'}
            </p>

            <div className="sy-sems">
              {SEMESTERS.map((n) => {
                const subs = subjectsOfSemester(n, branch && branch.id);
                const isOpen = openSem.includes(n);
                return (
                  <article className={`sy-sem${isOpen ? ' is-open' : ''}`} key={n}>
                    <button type="button" className="sy-sem-head" aria-expanded={isOpen} onClick={() => toggleSem(n)}>
                      <span className="sy-sem-no">{n}</span>
                      <span className="sy-sem-name">
                        <b>{semesterName(n)}</b>
                        <small>{subs.length ? `${subs.length} subject${subs.length === 1 ? '' : 's'}` : 'Coming soon'}</small>
                      </span>
                      <ion-icon name="chevron-down-outline" class="sy-chev"></ion-icon>
                    </button>

                    {isOpen && (
                      <div className="sy-sem-body">
                        {subs.length ? (
                          <div className="sy-grid">
                            {subs.map((s) => (
                              <button
                                key={s.id}
                                type="button"
                                className="sy-card"
                                style={{ '--tint': (branch && branch.tint) || s.tint || '#fb7185' }}
                                onClick={() => navigate(`/syllabus/${branch ? branch.id : 'all'}/${s.id}`)}
                              >
                                <span className="sy-icon"><ion-icon name={s.icon}></ion-icon></span>
                                <span className="sy-text">
                                  <b>{s.title}</b>
                                  <small>
                                    {s.units.length} units · L-T-P {s.ltp}
                                    {s.branches === 'all' ? ' · All branches' : ''}
                                  </small>
                                </span>
                                <ion-icon name="chevron-forward-outline" class="sy-go"></ion-icon>
                              </button>
                            ))}
                          </div>
                        ) : (
                          <p className="tl-help sy-soon">Subjects of this semester are being added.</p>
                        )}
                      </div>
                    )}
                  </article>
                );
              })}
            </div>
          </>
        )}

        {/* ---------- one subject, unit wise ---------- */}
        {!missing && subject && <SubjectView key={subject.id} subject={subject} />}
      </main>
    </div>
  );
}
