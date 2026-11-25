import React, { useMemo, useState } from 'react';

/* ======================= CGPA / SGPA ======================= */

function Frac({ top, bottom }) {
  return (
    <span className="tl-frac">
      <span className="tl-frac-top">{top}</span>
      <span className="tl-frac-bottom">{bottom}</span>
    </span>
  );
}

// [letter, grade point, performance, marks range]
const GRADES = [
  ['A+', 10, 'Excellent', '90% and above'],
  ['A', 9, 'Very Good', '80% to 89%'],
  ['B+', 8, 'Good', '70% to 79%'],
  ['B', 7, 'Fair', '60% to 69%'],
  ['C+', 6, 'Average', '50% to 59%'],
  ['C', 5, 'Pass', '35% to 49%'],
  ['F', 0, 'Fail', 'Below 35%'],
];

let uid = 1;
const newSubject = () => ({ id: uid++, name: '', credits: '', grade: '10' });
const newSemester = () => ({ id: uid++, sgpa: '', credits: '' });

export default function CgpaTool() {
  const [mode, setMode] = useState('sgpa');
  const [subjects, setSubjects] = useState([newSubject(), newSubject(), newSubject()]);
  const [sems, setSems] = useState([newSemester(), newSemester()]);

  const sgpa = useMemo(() => {
    let pts = 0;
    let cr = 0;
    subjects.forEach((s) => {
      const c = parseFloat(s.credits);
      if (c > 0) {
        pts += c * Number(s.grade);
        cr += c;
      }
    });
    return cr ? { value: pts / cr, credits: cr } : null;
  }, [subjects]);

  const cgpa = useMemo(() => {
    let pts = 0;
    let cr = 0;
    sems.forEach((s) => {
      const g = parseFloat(s.sgpa);
      const c = parseFloat(s.credits);
      if (g >= 0 && g <= 10 && c > 0) {
        pts += g * c;
        cr += c;
      }
    });
    return cr ? { value: pts / cr, credits: cr } : null;
  }, [sems]);

  const result = mode === 'sgpa' ? sgpa : cgpa;
  const update = (list, setList, id, patch) =>
    setList(list.map((r) => (r.id === id ? { ...r, ...patch } : r)));

  return (
    <div className="tl-panel">
      <h2>CGPA / SGPA Calculator</h2>
      <p className="tl-help">
        Subject ke credits aur grade daalo, SGPA turant mil jayega. Sab semester ka SGPA daal kar CGPA
        nikalo.
      </p>

      <div className="tl-tabs" role="tablist">
        <button type="button" className={mode === 'sgpa' ? 'is-on' : ''} onClick={() => setMode('sgpa')}>
          SGPA (1 semester)
        </button>
        <button type="button" className={mode === 'cgpa' ? 'is-on' : ''} onClick={() => setMode('cgpa')}>
          CGPA (all semesters)
        </button>
      </div>

      {mode === 'sgpa' ? (
        <>
          {subjects.map((s, i) => (
            <div className="tl-row" key={s.id}>
              <div className="tl-field" style={{ flex: 2 }}>
                <label>Subject {i + 1}</label>
                <input
                  className="tl-input"
                  placeholder="Name (optional)"
                  value={s.name}
                  onChange={(e) => update(subjects, setSubjects, s.id, { name: e.target.value })}
                />
              </div>
              <div className="tl-field">
                <label>Credits</label>
                <input
                  className="tl-input"
                  inputMode="decimal"
                  placeholder="4"
                  value={s.credits}
                  onChange={(e) => update(subjects, setSubjects, s.id, { credits: e.target.value })}
                />
              </div>
              <div className="tl-field">
                <label>Grade</label>
                <select
                  className="tl-select"
                  value={s.grade}
                  onChange={(e) => update(subjects, setSubjects, s.id, { grade: e.target.value })}
                >
                  {GRADES.map(([g, p, perf]) => (
                    <option key={g} value={p}>
                      {g} - {perf} ({p})
                    </option>
                  ))}
                </select>
              </div>
              <button
                type="button"
                className="tl-icon-btn"
                aria-label="Remove subject"
                disabled={subjects.length <= 1}
                onClick={() => setSubjects(subjects.filter((x) => x.id !== s.id))}
              >
                <ion-icon name="close-outline"></ion-icon>
              </button>
            </div>
          ))}
          <button type="button" className="tl-btn is-ghost is-small" onClick={() => setSubjects([...subjects, newSubject()])}>
            <ion-icon name="add-outline"></ion-icon> Add subject
          </button>
        </>
      ) : (
        <>
          {sems.map((s, i) => (
            <div className="tl-row" key={s.id}>
              <div className="tl-field">
                <label>Sem {i + 1} SGPA</label>
                <input
                  className="tl-input"
                  inputMode="decimal"
                  placeholder="8.2"
                  value={s.sgpa}
                  onChange={(e) => update(sems, setSems, s.id, { sgpa: e.target.value })}
                />
              </div>
              <div className="tl-field">
                <label>Total credits</label>
                <input
                  className="tl-input"
                  inputMode="decimal"
                  placeholder="24"
                  value={s.credits}
                  onChange={(e) => update(sems, setSems, s.id, { credits: e.target.value })}
                />
              </div>
              <button
                type="button"
                className="tl-icon-btn"
                aria-label="Remove semester"
                disabled={sems.length <= 1}
                onClick={() => setSems(sems.filter((x) => x.id !== s.id))}
              >
                <ion-icon name="close-outline"></ion-icon>
              </button>
            </div>
          ))}
          <button type="button" className="tl-btn is-ghost is-small" onClick={() => setSems([...sems, newSemester()])}>
            <ion-icon name="add-outline"></ion-icon> Add semester
          </button>
        </>
      )}

      {mode === 'sgpa' && (
        <details className="tl-grades">
          <summary>Grade point table</summary>
          <table>
            <thead>
              <tr><th>Grade</th><th>Performance</th><th>Point</th><th>Marks</th></tr>
            </thead>
            <tbody>
              {GRADES.map(([g, p, perf, range]) => (
                <tr key={g}><td>{g}</td><td>{perf}</td><td>{p}</td><td>{range}</td></tr>
              ))}
            </tbody>
          </table>
        </details>
      )}

      <div className="tl-result">
        <div className="tl-stat">
          <small>{mode === 'sgpa' ? 'Your SGPA' : 'Your CGPA'}</small>
          <b>{result ? result.value.toFixed(2) : '--'}</b>
        </div>
        <div className="tl-stat">
          <small>Percentage</small>
          <b>{result ? `${Math.max(0, (result.value - 0.5) * 10).toFixed(1)}%` : '--'}</b>
        </div>
        <div className="tl-stat">
          <small>Total credits</small>
          <b>{result ? result.credits : '--'}</b>
        </div>
      </div>
      <details className="tl-math">
        <summary>JUT formulas (tap to view)</summary>
        <div className="tl-eq tl-eq-wide">
          <span className="tl-var">SGPA</span> <span>=</span>
          <Frac
            top={<><span className="tl-nb">Σ<sub>Semester</sub> (Course credits × Grade point)</span> <small>for all courses</small></>}
            bottom={<span className="tl-nb">Σ<sub>Semester</sub> (Course credits)</span>}
          />
        </div>
        <div className="tl-eq">
          <span className="tl-var">CGPA</span> <span>=</span>
          <Frac top={<>Σ ( S<sub>i</sub> × C<sub>i</sub> )</>} bottom={<>Σ C<sub>i</sub></>} />
        </div>
        <div className="tl-eq">
          <span className="tl-var">Percentage</span> <span>=</span>
          <span>(CGPA − 0.5) × 10</span>
        </div>
        <p className="tl-math-note">
          For CGPA: C<sub>i</sub> = total credits of semester i, S<sub>i</sub> = SGPA of that semester. For the
          final result, always check your official marksheet.
        </p>
      </details>
    </div>
  );
}
