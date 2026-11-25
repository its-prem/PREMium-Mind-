/** Products: add, edit, copy, trash, restore, delete, quick switches. Form fields live in courses/fields.js. */
import React, { useMemo, useState } from 'react';
import { adminGet, adminPost } from '../api';
import Icon from '../Icon';
import { Empty, ErrorBox, Loading, Switch, money, useLoad, useUi } from '../ui';
import CourseForm from './courses/CourseForm';
import { FLAGS } from './courses/fields';

export default function Courses({ options }) {
  const { toast, confirm } = useUi();
  const { data, error, loading, reload } = useLoad(() => adminGet('courses.php'));
  const [q, setQ] = useState('');
  const [cat, setCat] = useState('all');
  const [view, setView] = useState('live'); // live | trash
  const [form, setForm] = useState(null); // null | {} (new) | course
  const [local, setLocal] = useState({}); // switch values just changed here
  const [busy, setBusy] = useState([]); // switches being saved

  const rows = useMemo(() => {
    if (!data) return [];
    return data.courses
      .filter((c) => (view === 'trash' ? c.is_deleted == 1 : c.is_deleted != 1))
      .filter((c) => cat === 'all' || c.category === cat)
      .filter((c) => c.title.toLowerCase().includes(q.toLowerCase()));
  }, [data, q, cat, view]);

  const trashed = data ? data.courses.filter((c) => c.is_deleted == 1).length : 0;

  const call = async (body, okText) => {
    try {
      await adminPost('courses.php', body);
      if (okText) toast(okText);
      reload();
    } catch (e) {
      toast(e.message, 'error');
    }
  };

  const setFlag = async (c, field, value) => {
    const key = `${c.id}:${field}`;
    setLocal((l) => ({ ...l, [key]: value }));
    setBusy((b) => [...b, key]);
    try {
      await adminPost('courses.php', { action: 'flag', id: c.id, field, value: value === true ? 1 : value === false ? 0 : value });
      toast('Saved');
      // the new value stays in `local` (the list is up to date with it); `local` is cleared when the form saves
      setBusy((b) => b.filter((k) => k !== key));
    } catch (e) {
      toast(e.message, 'error');
      setBusy((b) => b.filter((k) => k !== key));
      setLocal((l) => {
        const n = { ...l };
        delete n[key];
        return n;
      });
    }
  };
  const flagValue = (c, field) => {
    const k = `${c.id}:${field}`;
    return k in local ? local[k] : c[field];
  };

  const ask = async (opts, body, okText) => {
    if (await confirm(opts)) call(body, okText);
  };

  if (loading && !data) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;

  return (
    <>
      <div className="adm-toolbar">
        <input className="adm-input grow" placeholder="Search products…" value={q} onChange={(e) => setQ(e.target.value)} />
        <select className="adm-select" style={{ width: 'auto' }} value={cat} onChange={(e) => setCat(e.target.value)}>
          <option value="all">All categories</option>
          {(options?.categories || []).map((o) => (
            <option key={o.id} value={o.id}>
              {o.label}
            </option>
          ))}
        </select>
        <button className={`adm-btn ${view === 'live' ? '' : 'ghost'}`} onClick={() => setView('live')}>
          Live
        </button>
        <button className={`adm-btn ${view === 'trash' ? '' : 'ghost'}`} onClick={() => setView('trash')}>
          Trash ({trashed})
        </button>
        <button className="adm-btn good" onClick={() => setForm({})}>
          <Icon name="plus" size={16} /> Add product
        </button>
      </div>

      {rows.length === 0 ? (
        <Empty text={view === 'trash' ? 'Trash is empty.' : 'No products found.'} />
      ) : (
        <div className="adm-table-wrap">
          <table className="adm-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Price</th>
                <th>Switches</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {rows.map((c) => {
                const dead = c.is_deleted == 1;
                const n = Number(c.enrolled_count || 0);
                return (
                  <tr key={c.id} className={dead ? 'dim' : ''}>
                    <td>
                      <div className="adm-prod">
                        <img className="adm-thumb" src={c.image} alt="" onError={(e) => (e.currentTarget.style.visibility = 'hidden')} />
                        <div>
                          <b>{c.title}</b>
                          <span className="adm-chip">{c.category}</span>{' '}
                          {c.pdf_file ? <span className="adm-chip ok">PDF</span> : <span className="adm-chip grey">no PDF</span>}{' '}
                          {n > 0 && <span className="adm-chip warn">{n} students</span>}
                          <div className="adm-muted small" style={{ marginBottom: 0 }}>
                            #{c.id} · rank {c.sort_order}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td style={{ whiteSpace: 'nowrap' }}>
                      <b>{money(c.price)}</b>
                      {c.old_price > 0 && (
                        <div className="adm-muted small">
                          <s>{money(c.old_price)}</s>
                        </div>
                      )}
                    </td>
                    <td>
                      {!dead && (
                        <div className="adm-flags">
                          {FLAGS.filter((f) => f.inList).map((f) => (
                            <div key={f.name} className="adm-flag">
                              {f.label}
                              <Switch
                                checked={flagValue(c, f.name) == 1 || flagValue(c, f.name) === true}
                                disabled={busy.includes(`${c.id}:${f.name}`)}
                                onChange={(on) => setFlag(c, f.name, on)}
                              />
                            </div>
                          ))}
                          <select
                            className="adm-select"
                            style={{ padding: '4px 8px', fontSize: '0.8rem' }}
                            value={flagValue(c, 'btn_type') || 'normal'}
                            onChange={(e) => setFlag(c, 'btn_type', e.target.value)}
                          >
                            {(options?.btnTypes || []).map((o) => (
                              <option key={o.id} value={o.id}>
                                {o.label}
                              </option>
                            ))}
                          </select>
                        </div>
                      )}
                    </td>
                    <td>
                      <div className="adm-actions">
                        {dead ? (
                          <>
                            <button className="adm-btn good small" onClick={() => call({ action: 'restore', id: c.id }, 'Restored')}>
                              Restore
                            </button>
                            {n > 0 && (
                              <button
                                className="adm-btn warn small"
                                onClick={() =>
                                  ask(
                                    { title: 'Remove from all students?', text: `${n} student(s) still have this product. Their access will be removed (a history copy is kept).`, danger: true, confirmText: 'Remove access' },
                                    { action: 'revoke_all', id: c.id },
                                    'Access removed'
                                  )
                                }
                              >
                                Remove access
                              </button>
                            )}
                            <button
                              className="adm-btn danger small"
                              onClick={() =>
                                ask(
                                  { title: `Delete “${c.title}” for ever?`, text: 'This cannot be undone.', danger: true, confirmText: 'Delete for ever' },
                                  { action: 'delete', id: c.id },
                                  'Deleted'
                                )
                              }
                            >
                              Delete
                            </button>
                          </>
                        ) : (
                          <>
                            <button className="adm-btn small" onClick={() => setForm(c)}>
                              Edit
                            </button>
                            <button className="adm-btn ghost small" onClick={() => call({ action: 'duplicate', id: c.id }, 'Copy created')}>
                              Copy
                            </button>
                            {n > 0 && (
                              <button
                                className="adm-btn warn small"
                                onClick={() =>
                                  ask(
                                    { title: 'Remove from all students?', text: `${n} student(s) will lose this product (a history copy is kept).`, danger: true, confirmText: 'Remove access' },
                                    { action: 'revoke_all', id: c.id },
                                    'Access removed'
                                  )
                                }
                              >
                                Revoke
                              </button>
                            )}
                            <button
                              className="adm-btn danger small"
                              onClick={() =>
                                ask(
                                  { title: `Move “${c.title}” to trash?`, text: 'It disappears from the website. You can restore it later.', danger: true, confirmText: 'Move to trash' },
                                  { action: 'trash', id: c.id },
                                  'Moved to trash'
                                )
                              }
                            >
                              Trash
                            </button>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {form && (
        <CourseForm
          course={form.id ? form : null}
          options={options}
          onClose={() => setForm(null)}
          onSaved={() => {
            setForm(null);
            setLocal({});
            reload();
          }}
        />
      )}
    </>
  );
}
