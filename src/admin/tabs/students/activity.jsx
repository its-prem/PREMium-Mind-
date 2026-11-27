/**
 * Student activity for the Students tab: who is online, last seen, device, the addresses a student used, and the
 * two block buttons (block the student's account / block one IP address).
 * The server side is admin/students.php (history, block_user, unblock_user) and admin/blocklist.php.
 */
import React, { useEffect, useState } from 'react';
import { adminPost } from '../../api';
import { fmtDate } from '../../ui';

/** "Chrome · Android" from a User-Agent text */
export function deviceName(ua) {
  const s = String(ua || '');
  if (!s) return '—';
  const os = /Android/i.test(s) ? 'Android' : /iPhone|iPad|iPod/i.test(s) ? 'iPhone' : /Windows/i.test(s) ? 'Windows' : /Mac OS/i.test(s) ? 'Mac' : /Linux/i.test(s) ? 'Linux' : '';
  const app = /; wv\)|Capacitor/i.test(s) ? 'App' : /Edg\//i.test(s) ? 'Edge' : /OPR\/|Opera/i.test(s) ? 'Opera' : /Chrome\//i.test(s) ? 'Chrome' : /Firefox\//i.test(s) ? 'Firefox' : /Safari\//i.test(s) ? 'Safari' : 'Browser';
  return [app, os].filter(Boolean).join(' · ');
}

/** 40 -> "just now", 300 -> "5 min ago", 7200 -> "2 h ago" */
export function ago(seconds) {
  const n = Number(seconds);
  if (!Number.isFinite(n)) return '';
  if (n < 90) return 'just now';
  if (n < 3600) return `${Math.round(n / 60)} min ago`;
  if (n < 86400) return `${Math.round(n / 3600)} h ago`;
  return `${Math.round(n / 86400)} d ago`;
}

export const isOnline = (s) => s.seen_ago !== null && s.seen_ago !== undefined && s.seen_ago !== '' && Number(s.seen_ago) < 300;

/** the small status line on a student card */
export function SeenLine({ s }) {
  if (!s.last_seen) return <span className="adm-muted small">Never seen online</span>;
  return (
    <span className="adm-row small" style={{ gap: 6, marginBottom: 0 }}>
      <span style={{ width: 9, height: 9, borderRadius: '50%', background: isOnline(s) ? '#16a34a' : '#a1a1aa', display: 'inline-block' }} />
      <b>{isOnline(s) ? 'Online now' : `Seen ${ago(s.seen_ago)}`}</b>
      <span className="adm-muted">· {deviceName(s.last_ua)}</span>
    </span>
  );
}

/** inside the Manage popup: activity + the block buttons */
export function ActivityPanel({ student, toast, confirm, onChanged }) {
  const [info, setInfo] = useState(null);
  const [busy, setBusy] = useState(false);

  const load = () => {
    adminPost('students.php', { action: 'history', email: student.email })
      .then(setInfo)
      .catch((e) => toast(e.message, 'error'));
  };
  useEffect(load, [student.email]); // eslint-disable-line

  const blockUser = async () => {
    const ok = await confirm({
      title: `Block ${student.name || student.email}?`,
      text: 'They cannot log in or use the website until you unblock them. Their purchases stay safe.',
      confirmText: 'Block student',
      danger: true,
    });
    if (!ok) return;
    const reason = window.prompt('Reason (only you see it, can be empty):', '') || '';
    setBusy(true);
    try {
      await adminPost('students.php', { action: 'block_user', email: student.email, reason });
      toast('Student blocked');
      load();
      onChanged();
    } catch (e) {
      toast(e.message, 'error');
    } finally {
      setBusy(false);
    }
  };

  const unblockUser = async () => {
    setBusy(true);
    try {
      await adminPost('students.php', { action: 'unblock_user', email: student.email });
      toast('Student unblocked');
      load();
      onChanged();
    } catch (e) {
      toast(e.message, 'error');
    } finally {
      setBusy(false);
    }
  };

  const blockIp = async (ip) => {
    const ok = await confirm({
      title: `Block address ${ip}?`,
      text: 'Everybody who uses this address (a college Wi-Fi or a phone network has many students) is refused login, payments and PDFs for 1 day.',
      confirmText: 'Block 1 day',
      danger: true,
    });
    if (!ok) return;
    try {
      await adminPost('blocklist.php', { action: 'block', ip, reason: `From student ${student.email}`, hours: 24 });
      toast(`${ip} blocked for 1 day`);
    } catch (e) {
      toast(e.message, 'error');
    }
  };

  const blocked = info ? !!info.blocked : Number(student.is_blocked) === 1;
  const act = info?.activity;
  const ips = info?.ips || [];
  const many = ips.length >= 3;

  return (
    <div className="adm-activity">
      <div className="adm-section-head" style={{ marginBottom: 6 }}>
        <h4>Activity</h4>
        {blocked && <span className="adm-chip bad">Blocked</span>}
      </div>
      {!info ? (
        <span className="adm-muted small">Loading…</span>
      ) : !act ? (
        <span className="adm-muted small">Not seen online yet (it is written from now on).</span>
      ) : (
        <div className="small" style={{ lineHeight: 1.7 }}>
          Last seen: <b>{fmtDate(act.last_seen)}</b>
          <br />
          Device: <b>{deviceName(act.last_ua)}</b> · Visits: <b>{act.seen_count}</b>
          <br />
          First seen: {fmtDate(act.first_seen)}
        </div>
      )}

      {many && (
        <div className="adm-chip warn" style={{ margin: '8px 0', whiteSpace: 'normal' }}>
          {ips.length} different addresses: this account may be shared with other people.
        </div>
      )}
      {ips.length > 0 && (
        <div style={{ marginTop: 6 }}>
          {ips.map((r) => (
            <div key={r.ip} className="adm-tog">
              <span>
                <b>{r.ip}</b> <span className="adm-muted small">· {r.hits}× · {fmtDate(r.last_seen)}</span>
              </span>
              <button type="button" className="adm-btn ghost small" onClick={() => blockIp(r.ip)}>
                Block IP
              </button>
            </div>
          ))}
        </div>
      )}

      <div className="adm-row" style={{ marginTop: 10 }}>
        {blocked ? (
          <button type="button" className="adm-btn good" disabled={busy} onClick={unblockUser}>
            Unblock student
          </button>
        ) : (
          <button type="button" className="adm-btn warn" disabled={busy} onClick={blockUser}>
            Block this student
          </button>
        )}
      </div>
      {blocked && info?.blocked?.reason && <span className="adm-hint">Reason: {info.blocked.reason}</span>}
    </div>
  );
}
