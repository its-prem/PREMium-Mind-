import React, { useEffect, useRef, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { BRANCHES, SEMESTERS, getBranch, getSubject, listTopics, semesterName, subjectsOfSemester } from '../data/syllabus';
import { goBack } from '../lib/goBack';
import { useUploadedSyllabus } from '../lib/syllabusStore';
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
            {semester} · {subject.units.length} units · {total} topics
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
 * Diploma syllabus pages. Every tap opens the next screen (no drop-downs):
 *   /syllabus                     -> semesters 1-6
 *   /syllabus/sem/:n              -> the subject cards of that semester
 *   /syllabus/:branch/:subject    -> the syllabus (units and topics) of one subject   (:branch can be "all")
 *   /syllabus/:branch             -> the semester list for one branch (older link)
 * The branch chips only filter: they add ?b=cse to the address. The data lives in src/data/syllabus.js.
 */
export default function Syllabus() {
  const navigate = useNavigate();
  const { branch: p1, subject: p2 } = useParams();
  const [query] = useSearchParams();
  const loadingUploaded = useUploadedSyllabus(); // subjects uploaded from the admin panel

  const isSemPage = p1 === 'sem';
  const semNo = isSemPage ? Number(p2) : 0;
  const subjectId = !isSemPage && p2 ? p2 : '';
  const branchId = isSemPage ? query.get('b') : p1 && p1 !== 'all' ? p1 : query.get('b');
  const branch = branchId ? getBranch(branchId) : null;
  const subject = subjectId ? getSubject(subjectId) : null;
  const bq = branch ? `?b=${branch.id}` : ''; // keeps the chosen branch while moving between screens

  const waiting = loadingUploaded && subjectId && !subject; // an uploaded subject is still being fetched
  const missing = !loadingUploaded && ((branchId && !branch) || (subjectId && !subject) || (isSemPage && !SEMESTERS.includes(semNo)));

  useEffect(() => {
    document.title = `${(subject && subject.title) || (isSemPage && !missing && semesterName(semNo)) || 'Diploma Syllabus'} | PREMium Mind`;
    window.scrollTo(0, 0);
  }, [p1, p2, branchId]); // eslint-disable-line

  let title = 'Diploma Syllabus';
  let sub = 'Semester wise subjects';
  if (isSemPage && !missing) {
    title = semesterName(semNo);
    sub = 'Choose a subject';
  }
  if (subject) {
    title = subject.title;
    sub = subject.semester.replace(/\s*\(.*\)/, '');
  }

  const pickBranch = (id) => navigate(isSemPage ? `/syllabus/sem/${semNo}${id ? `?b=${id}` : ''}` : `/syllabus${id ? `?b=${id}` : ''}`, { replace: true });

  const chips = (
    <>
      <div className="sy-chips" role="group" aria-label="Branch">
        <button type="button" className={`sy-chip${!branch ? ' on' : ''}`} onClick={() => pickBranch('')}>
          All branches
        </button>
        {BRANCHES.map((b) => (
          <button key={b.id} type="button" className={`sy-chip${branch && branch.id === b.id ? ' on' : ''}`} onClick={() => pickBranch(b.id)}>
            {b.title}
          </button>
        ))}
      </div>
      <p className="sy-hint">
        {branch ? `Showing ${branch.title} subjects and the subjects common to every branch.` : 'Pick your branch above to see only your subjects.'}
      </p>
    </>
  );

  return (
    <div className="tl-page">
      <header className="tl-top">
        <button type="button" className="tl-back" aria-label="Back" onClick={() => goBack(navigate, subject ? `/syllabus/sem/${subject.sem}${bq}` : isSemPage ? `/syllabus${bq}` : "/")}>
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

        {/* ---------- 1. semesters 1-6: tap one to see its subjects ---------- */}
        {waiting && <p className="tl-help sy-soon">Loading…</p>}

        {!missing && !waiting && !isSemPage && !subject && (
          <>
            {chips}
            <div className="sy-sems">
              {SEMESTERS.map((n) => {
                const count = subjectsOfSemester(n, branch && branch.id).length;
                return (
                  <button key={n} type="button" className="sy-sem sy-semcard" onClick={() => navigate(`/syllabus/sem/${n}${bq}`)}>
                    <span className="sy-sem-no">{n}</span>
                    <span className="sy-sem-name">
                      <b>{semesterName(n)}</b>
                      <small>{count ? `${count} subject${count === 1 ? '' : 's'}` : 'Coming soon'}</small>
                    </span>
                    <ion-icon name="chevron-forward-outline" class="sy-go"></ion-icon>
                  </button>
                );
              })}
            </div>
          </>
        )}

        {/* ---------- 2. one semester: its subject cards ---------- */}
        {!missing && isSemPage && (
          <>
            {chips}
            {subjectsOfSemester(semNo, branch && branch.id).length ? (
              <div className="sy-dks">
                {subjectsOfSemester(semNo, branch && branch.id).map((s) => (
                  <button
                    key={s.id}
                    type="button"
                    className="sy-dk"
                    style={{ '--tint': (branch && branch.tint) || s.tint || '#fb7185' }}
                    onClick={() => navigate(`/syllabus/${branch ? branch.id : 'all'}/${s.id}`)}
                  >
                    <span className="sy-dk-meta">
                      <span className="sy-dk-units">
                        <i></i> {s.units.length} UNITS
                      </span>
                    </span>
                    <span className="sy-dk-title">{s.title}</span>
                    <span className="sy-dk-foot">
                      <span>{s.branches === 'all' ? 'All branches' : 'Selected branches'}</span>
                      <span className="sy-dk-go">
                        <ion-icon name="arrow-forward-outline"></ion-icon>
                      </span>
                    </span>
                  </button>
                ))}
              </div>
            ) : (
              <p className="tl-help sy-soon">Subjects of this semester are being added.</p>
            )}
          </>
        )}

        {/* ---------- 3. one subject: its syllabus ---------- */}
        {!missing && subject && <SubjectView key={subject.id} subject={subject} />}
      </main>
    </div>
  );
}
