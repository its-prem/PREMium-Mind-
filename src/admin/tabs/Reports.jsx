/** Problem reports from students: open / solved, mark solved, delete. */
import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { adminGet, adminPost } from '../api';
import { Empty, ErrorBox, Loading, fmtDate, useLoad, useUi } from '../ui';

export default function Reports() {
  const { toast, confirm } = useUi();
  const [status, setStatus] = useState('pending');
  const { data, error, loading, reload } = useLoad(() => adminGet('reports.php', { status }), [status]);

  const act = async (id, action, ask) => {
    if (ask && !(await confirm(ask))) return;
    try {
      await adminPost('reports.php', { action, id });
      toast('Done');
      reload();
    } catch (e) {
      toast(e.message, 'error');
    }
  };

  return (
    <>
      <div className="adm-toolbar">
        <button className={`adm-btn ${status === 'pending' ? '' : 'ghost'}`} onClick={() => setStatus('pending')}>
          Open
        </button>
        <button className={`adm-btn ${status === 'resolved' ? '' : 'ghost'}`} onClick={() => setStatus('resolved')}>
          Solved
        </button>
      </div>
      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBox error={error} onRetry={reload} />
      ) : data.reports.length === 0 ? (
        <Empty text={status === 'pending' ? 'All caught up – no open reports.' : 'No solved reports.'} />
      ) : (
        <div className="adm-cards">
          {data.reports.map((r) => (
            <div key={r.id} className="adm-card">
              <span className="adm-chip warn">{r.course_title || `Product #${r.course_id}`}</span>
              <p style={{ margin: '10px 0 4px' }}>
                <b>{r.user_name || 'Unknown student'}</b>
                <br />
                <span className="adm-muted">
                  {r.email}
                  {r.user_phone ? ` · ${r.user_phone}` : ''}
                </span>
              </p>
              <p style={{ background: '#f4f6fb', padding: 10, borderRadius: 10, whiteSpace: 'pre-wrap' }}>{r.message}</p>
              <div className="adm-muted small">{fmtDate(r.created_at)}</div>
              <div className="adm-actions">
                {r.screenshot && (
                  <a className="adm-btn ghost small" href={r.screenshot} target="_blank" rel="noreferrer">
                    Screenshot
                  </a>
                )}
                <Link className="adm-btn ghost small" to={`/admin/students?search=${encodeURIComponent(r.email)}`}>
                  Student
                </Link>
                {status === 'pending' ? (
                  <button className="adm-btn good small" onClick={() => act(r.id, 'resolve')}>
                    Mark solved
                  </button>
                ) : (
                  <button className="adm-btn ghost small" onClick={() => act(r.id, 'reopen')}>
                    Reopen
                  </button>
                )}
                <button
                  className="adm-btn danger small"
                  onClick={() => act(r.id, 'delete', { title: 'Delete this report?', text: 'It will be removed for good.', danger: true, confirmText: 'Delete' })}
                >
                  Delete
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </>
  );
}
