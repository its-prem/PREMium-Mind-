/** IP block: stop a bad IP address from using the website's server (login, payments, PDFs). Admins are never locked out. */
import React, { useState } from 'react';
import { adminGet, adminPost } from '../api';
import { Empty, ErrorBox, Loading, fmtDate, useLoad, useUi } from '../ui';

const DURATIONS = [
  { hours: 1, label: '1 hour' },
  { hours: 24, label: '1 day' },
  { hours: 24 * 7, label: '7 days' },
  { hours: 0, label: 'For ever' },
];

export default function IpBlock() {
  const { toast, confirm } = useUi();
  const { data, error, loading, reload } = useLoad(() => adminGet('blocklist.php'));
  const [ip, setIp] = useState('');
  const [reason, setReason] = useState('');
  const [hours, setHours] = useState(24);
  const [busy, setBusy] = useState(false);

  const call = async (body, okText) => {
    setBusy(true);
    try {
      await adminPost('blocklist.php', body);
      toast(okText);
      reload();
      return true;
    } catch (e) {
      toast(e.message, 'error');
      return false;
    } finally {
      setBusy(false);
    }
  };

  const block = async (address, why, h) => {
    const ok = await confirm({
      title: `Block ${address}?`,
      text: 'Many students can share one address (a college Wi-Fi or a phone network). Everybody on it will be refused login, payments and PDFs. Block only when you are sure.',
      confirmText: 'Block',
      danger: true,
    });
    if (!ok) return;
    if (await call({ action: 'block', ip: address, reason: why, hours: h }, `${address} is blocked`)) {
      setIp('');
      setReason('');
    }
  };

  if (loading) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;

  return (
    <>
      <div className="adm-card">
        <div className="adm-section-head">
          <h4>Block an address</h4>
          <span className="adm-hint">
            Your own address now: <b>{data.you}</b>. You can never be locked out of this panel.
          </span>
        </div>
        <div className="adm-form-grid">
          <div className="adm-field">
            <label htmlFor="ip_addr">IP address</label>
            <input id="ip_addr" className="adm-input" value={ip} onChange={(e) => setIp(e.target.value)} placeholder="203.0.113.25" inputMode="text" autoComplete="off" />
          </div>
          <div className="adm-field">
            <label htmlFor="ip_hours">For how long</label>
            <select id="ip_hours" className="adm-select" value={hours} onChange={(e) => setHours(Number(e.target.value))}>
              {DURATIONS.map((d) => (
                <option key={d.hours} value={d.hours}>{d.label}</option>
              ))}
            </select>
          </div>
          <div className="adm-field full">
            <label htmlFor="ip_why">Reason (only you see it)</label>
            <input id="ip_why" className="adm-input" value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Guessing passwords / spam orders" maxLength={200} />
          </div>
        </div>
        <div className="adm-row end">
          <button className="adm-btn danger" disabled={busy || !ip.trim()} onClick={() => block(ip.trim(), reason, hours)}>
            Block this address
          </button>
        </div>
      </div>

      <div className="adm-card" style={{ marginTop: 16 }}>
        <div className="adm-section-head">
          <h4>Suspicious addresses</h4>
          <span className="adm-hint">Addresses that crossed a limit lately (too many logins, payments, PDF requests). Nothing is blocked automatically.</span>
        </div>
        {!data.suspicious.length ? (
          <Empty text="Nobody suspicious in the last 14 days." />
        ) : (
          data.suspicious.map((s) => (
            <div key={s.ip} className="adm-flagrow">
              <div style={{ minWidth: 0 }}>
                <b>{s.ip}</b>
                <span className="adm-hint">
                  {s.what} · last {fmtDate(s.last_seen)}
                </span>
              </div>
              <div className="adm-row" style={{ flexWrap: 'nowrap' }}>
                {Number(s.is_blocked) ? (
                  <span className="adm-chip bad">Blocked</span>
                ) : (
                  <button className="adm-btn danger small" disabled={busy || s.ip === data.you} onClick={() => block(s.ip, `Suspicious: ${s.what}`, 24)}>
                    Block 1 day
                  </button>
                )}
                <button className="adm-btn ghost small" disabled={busy} onClick={() => call({ action: 'forget', ip: s.ip }, 'Removed from the list')}>
                  Ignore
                </button>
              </div>
            </div>
          ))
        )}
      </div>

      <div className="adm-card" style={{ marginTop: 16 }}>
        <div className="adm-section-head">
          <h4>Blocked now</h4>
          <span className="adm-hint">{data.blocked.length} address(es)</span>
        </div>
        {!data.blocked.length ? (
          <Empty text="No address is blocked." />
        ) : (
          data.blocked.map((b) => (
            <div key={b.ip} className="adm-flagrow on">
              <div style={{ minWidth: 0 }}>
                <b>{b.ip}</b>
                <span className="adm-hint">
                  {b.reason || 'No reason written'} · {b.expires_at ? `until ${fmtDate(b.expires_at)}` : 'for ever'}
                </span>
              </div>
              <button className="adm-btn small" disabled={busy} onClick={() => call({ action: 'unblock', ip: b.ip }, `${b.ip} is unblocked`)}>
                Unblock
              </button>
            </div>
          ))
        )}
      </div>
    </>
  );
}
