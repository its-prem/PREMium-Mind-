import React, { Suspense, useEffect, useRef, useState } from 'react';
import { getCustomTemplate } from '../lib/resumeTemplates';
import './ResumeDocument.css';

/** Realistic sample used for template previews (the buyer replaces it). */
export const SAMPLE_RESUME = {
  name: 'Aarav Kumar',
  title: 'Diploma in Computer Science Engineering',
  email: 'aarav.kumar@example.com',
  phone: '+91 98765 43210',
  location: 'Ranchi, Jharkhand',
  link: 'linkedin.com/in/aarav-kumar',
  summary:
    'Motivated fresher with hands-on project experience in web development and a strong foundation in data structures. Looking for an entry-level software role where I can learn fast and contribute to real products.',
  skills: ['HTML / CSS', 'JavaScript', 'React', 'Python', 'SQL', 'Git & GitHub', 'Problem Solving'],
  experience: [
    {
      id: 'e1',
      role: 'Web Development Intern',
      company: 'TechSpark Solutions, Ranchi',
      period: 'Jun 2025 - Aug 2025',
      points:
        'Built 5 responsive pages using React and Tailwind CSS\nReduced page load time by 30% through image optimisation\nWorked with a team of 4 using Git and daily stand-ups',
    },
  ],
  education: [
    {
      id: 'd1',
      degree: 'Diploma in Computer Science Engineering',
      school: 'Government Polytechnic, Ranchi',
      period: '2022 - 2025',
      score: '8.4 CGPA',
    },
    {
      id: 'd2',
      degree: 'Matriculation (10th)',
      school: 'DAV Public School, Ranchi',
      period: '2021',
      score: '88%',
    },
  ],
  projects: [
    {
      id: 'p1',
      name: 'Student Notes Store',
      desc: 'Full-stack website to sell and read digital notes with secure PDF access.',
    },
    {
      id: 'p2',
      name: 'Weather Dashboard',
      desc: 'React app showing live weather and a 5-day forecast using a public API.',
    },
  ],
};

export const BLANK_RESUME = {
  name: '',
  title: '',
  email: '',
  phone: '',
  location: '',
  link: '',
  summary: '',
  skills: [],
  experience: [],
  education: [],
  projects: [],
};

export const ACCENTS = ['#09090b', '#dc2626', '#2563eb', '#059669', '#7c3aed', '#d97706'];

const bullets = (text) =>
  String(text || '')
    .split('\n')
    .map((t) => t.trim())
    .filter(Boolean);

// data-edit="path" marks a text the student can click in the editor and change in place (see ResumeEditor.jsx).
// Path examples: name, summary, skills.2, experience.0.role, experience.0.points#1 (the 2nd line of the points).
function Contact({ data, className = '' }) {
  const items = ['email', 'phone', 'location', 'link'].filter((k) => data[k]);
  if (!items.length) return null;
  return (
    <ul className={`rs-contact ${className}`}>
      {items.map((k) => (
        <li key={k} data-edit={k}>
          {data[k]}
        </li>
      ))}
    </ul>
  );
}

function Experience({ items }) {
  if (!items.length) return null;
  return items.map((e, i) => (
    <div className="rs-item" key={e.id}>
      <div className="rs-item-head">
        <b data-edit={`experience.${i}.role`}>{e.role}</b>
        <span data-edit={`experience.${i}.period`}>{e.period}</span>
      </div>
      <div className="rs-item-sub" data-edit={`experience.${i}.company`}>{e.company}</div>
      {bullets(e.points).length > 0 && (
        <ul className="rs-points">
          {bullets(e.points).map((pt, j) => (
            <li key={pt + j} data-edit={`experience.${i}.points#${j}`}>
              {pt}
            </li>
          ))}
        </ul>
      )}
    </div>
  ));
}

function Education({ items }) {
  if (!items.length) return null;
  return items.map((e, i) => (
    <div className="rs-item" key={e.id}>
      <div className="rs-item-head">
        <b data-edit={`education.${i}.degree`}>{e.degree}</b>
        <span data-edit={`education.${i}.period`}>{e.period}</span>
      </div>
      <div className="rs-item-sub">
        <span data-edit={`education.${i}.school`}>{e.school}</span>
        {e.score ? (
          <>
            {' | '}
            <span data-edit={`education.${i}.score`}>{e.score}</span>
          </>
        ) : null}
      </div>
    </div>
  ));
}

function Projects({ items }) {
  if (!items.length) return null;
  return items.map((p, i) => (
    <div className="rs-item" key={p.id}>
      <div className="rs-item-head">
        <b data-edit={`projects.${i}.name`}>{p.name}</b>
      </div>
      <div className="rs-item-sub rs-plain" data-edit={`projects.${i}.desc`}>{p.desc}</div>
    </div>
  ));
}

const Section = ({ title, show = true, children }) =>
  show ? (
    <section className="rs-section">
      <h3>{title}</h3>
      {children}
    </section>
  ) : null;

/**
 * One A4 resume page (794 x 1123 px). `template` is modern | classic | compact.
 * Used for the live editor and (scaled down) for the store thumbnails.
 */
export default function ResumeDocument({ template = 'modern', data, accent = '#09090b' }) {
  const d = { ...BLANK_RESUME, ...data };
  const style = { '--accent': accent };

  // a template dropped into src/templates/resume/ (loaded only now, see lib/resumeTemplates.js)
  const Custom = getCustomTemplate(template);
  if (Custom) {
    return (
      <Suspense fallback={<div className="rs-doc" style={style} />}>
        <Custom data={d} accent={accent} />
      </Suspense>
    );
  }

  const summary = <Section title="Summary" show={!!d.summary}><p data-edit="summary" data-multiline="1">{d.summary}</p></Section>;
  const experience = (
    <Section title="Experience" show={d.experience.length > 0}>
      <Experience items={d.experience} />
    </Section>
  );
  const education = (
    <Section title="Education" show={d.education.length > 0}>
      <Education items={d.education} />
    </Section>
  );
  const projects = (
    <Section title="Projects" show={d.projects.length > 0}>
      <Projects items={d.projects} />
    </Section>
  );
  const skills = (
    <Section title="Skills" show={d.skills.length > 0}>
      <ul className="rs-skills">
        {d.skills.map((s, i) => (
          <li key={s + i} data-edit={`skills.${i}`}>
            {s}
          </li>
        ))}
      </ul>
    </Section>
  );

  if (template === 'classic') {
    return (
      <div className="rs-doc rs-classic" style={style}>
        <header>
          <h1 data-edit="name">{d.name || 'Your Name'}</h1>
          {d.title && <p className="rs-title" data-edit="title">{d.title}</p>}
          <Contact data={d} className="rs-inline" />
        </header>
        {summary}
        {experience}
        {education}
        {projects}
        {skills}
      </div>
    );
  }

  if (template === 'compact') {
    return (
      <div className="rs-doc rs-compact" style={style}>
        <header>
          <div>
            <h1 data-edit="name">{d.name || 'Your Name'}</h1>
            {d.title && <p className="rs-title" data-edit="title">{d.title}</p>}
          </div>
          <Contact data={d} />
        </header>
        <div className="rs-body">
          {summary}
          {skills}
          {education}
          {experience}
          {projects}
        </div>
      </div>
    );
  }

  // modern (default): header + main column + side column
  return (
    <div className="rs-doc rs-modern" style={style}>
      <header>
        <h1 data-edit="name">{d.name || 'Your Name'}</h1>
        {d.title && <p className="rs-title" data-edit="title">{d.title}</p>}
      </header>
      <div className="rs-cols">
        <main>
          {summary}
          {experience}
          {projects}
        </main>
        <aside>
          <Section title="Contact" show={!!(d.email || d.phone || d.location || d.link)}>
            <Contact data={d} />
          </Section>
          {skills}
          {education}
        </aside>
      </div>
    </div>
  );
}

export const DOC_WIDTH = 794;
export const DOC_HEIGHT = 1123;

/** Scales the A4 page down to fit its container's width. */
export function ResumeScaled(props) {
  const wrapRef = useRef(null);
  const [scale, setScale] = useState(0.4);

  useEffect(() => {
    const el = wrapRef.current;
    if (!el) return undefined;
    const update = () => setScale(el.clientWidth / DOC_WIDTH);
    update();
    const observer = new ResizeObserver(update);
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  return (
    <div ref={wrapRef} className="rs-scaled" style={{ height: DOC_HEIGHT * scale }}>
      <div
        className="rs-scaled-inner"
        style={{ width: DOC_WIDTH, height: DOC_HEIGHT, transform: `scale(${scale})` }}
      >
        <ResumeDocument {...props} />
      </div>
    </div>
  );
}
