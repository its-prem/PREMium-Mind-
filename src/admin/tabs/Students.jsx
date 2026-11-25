/** Students: search, give / take products with one switch, verify, delete. */
import React, { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { adminGet, adminPost } from '../api';
import { Empty, ErrorBox, Loading, Modal, Switch, fmtDate, useLoad, useUi } from '../ui';

export default function Students() {
  const { toast, confirm } = useUi();
  const [params] = useSearchParams();
  const [q, setQ] = useState(params.get('search') || '');
  const [search, setSearch] = useState(params.get('search') || '');
  const [page, setPage] = useState(1);
  const [open, setOpen] = useState(null); // student whose access popup is open
  const { data, error, loading, reload } = useLoad(() => adminGet('students.php', { search, page }), [search, page]);

  // wait a moment after typing before searching
  useEffect(() => {
    const t = setTimeout(() => {
      setPage(1);
      setSearch(q.trim());
    }, 400);
    return () => clearTimeout(t);
  }, [q]);

  const run = async (body, okText) => {
    try {
      await adminPost('students.php', body);
      if (okText) toast(okText);
      return true;
    } catch (e) {
      toast(e.message, 'error');
      return false;
    }
  };

  const del = async (s) => {
    const ok = await confirm({
      title: `Delete ${s.name || s.email}?`,
      text: 'The account and all their product access will be removed for good.',
      danger: true,
      confirmText: 'Delete student',
    });
    if (ok && (await run({ action: 'delete', email: s.email }, 'Student deleted'))) {
      setOpen(null);
      reload();
    }
  };

  const pages = data ? Math.max(1, Math.ceil(data.total / data.perPage)) : 1;

  return (
    <>
      <div className="adm-toolbar">
        <input className="adm-input grow" placeholder="Search by name or email…" value={q} onChange={(e) => setQ(e.target.value)} />
        {data && <span className="adm-muted">{data.total} students</span>}
      </div>

      {loading && !data ? (
        <Loading />
      ) : error ? (
        <ErrorBox error={error} onRetry={reload} />
      ) : data.students.length === 0 ? (
        <Empty text="No students found." />
      ) : (
        <>
          <div className="adm-cards">
            {data.students.map((s) => (
              <div key={s.id} className="adm-card">
                <div className="adm-row between" style={{ flexWrap: 'nowrap' }}>
                  <div className="adm-row" style={{ flexWrap: 'nowrap', minWidth: 0 }}>
                    <span className="adm-avatar big">{(s.name || s.email || '?').charAt(0).toUpperCase()}</span>
                    <b className="adm-ellipsis">{s.name || '—'}</b>
                  </div>
                  <span className={`adm-chip ${s.is_active == 1 ? 'ok' : 'warn'}`}>{s.is_active == 1 ? 'Verified' : 'Pending'}</span>
                </div>
                <div className="adm-muted small" style={{ marginTop: 4 }}>
                  {s.email}
                  {s.phone ? ` · ${s.phone}` : ''}
                  <br />
                  Joined: {s.created_at ? fmtDate(s.created_at) : 'Google sign-in'}
                </div>
                <div className="adm-row between" style={{ marginTop: 8 }}>
                  <span>
                    Owns <b>{s.enrolled.length}</b> product(s)
                  </span>
                  <button className="adm-btn small" onClick={() => setOpen(s)}>
                    Manage
                  </button>
                </div>
              </div>
            ))}
          </div>
          <div className="adm-pager">
            <button className="adm-btn ghost small" disabled={page <= 1} onClick={() => setPage(page - 1)}>
              ‹ Prev
            </button>
            <span className="adm-muted">
              Page {page} / {pages}
            </span>
            <button className="adm-btn ghost small" disabled={page >= pages} onClick={() => setPage(page + 1)}>
              Next ›
            </button>
          </div>
        </>
      )}

      {open && (
        <AccessModal
          student={data.students.find((x) => x.id === open.id) || open}
          courses={data.courses}
          onClose={() => setOpen(null)}
          onToggle={async (cid, on) => {
            const ok = await run({ action: on ? 'grant' : 'revoke', email: open.email, course_id: cid }, on ? 'Access given' : 'Access removed');
            if (ok) reload();
          }}
          onVerify={async (v) => {
            if (await run({ action: 'verify', email: open.email, value: v ? 1 : 0 }, 'Saved')) reload();
          }}
          onDelete={() => del(open)}
        />
      )}
    </>
  );
}

function AccessModal({ student, courses, onClose, onToggle, onVerify, onDelete }) {
  const [filter, setFilter] = useState('');
  const owned = new Set(student.enrolled);
  const shown = courses.filter((c) => c.title.toLowerCase().includes(filter.toLowerCase()));
  return (
    <Modal title={student.name || student.email} onClose={onClose}>
      <div className="adm-muted small">{student.email}</div>
      <div className="adm-tog">
        <span>Verified account</span>
        <Switch checked={student.is_active == 1} onChange={onVerify} />
      </div>
      <input className="adm-input" style={{ margin: '12px 0 6px' }} placeholder="Find a product…" value={filter} onChange={(e) => setFilter(e.target.value)} />
      <div style={{ maxHeight: '45vh', overflowY: 'auto' }}>
        {shown.map((c) => (
          <div key={c.id} className="adm-tog">
            <span>
              {c.title} <span className="adm-chip grey">{c.category}</span>
            </span>
            <Switch checked={owned.has(Number(c.id))} onChange={(on) => onToggle(Number(c.id), on)} />
          </div>
        ))}
      </div>
      <div className="adm-row end">
        <button className="adm-btn danger" onClick={onDelete}>
          Delete student
        </button>
      </div>
    </Modal>
  );
}
