/** Maintenance mode: one switch for the whole website + the text visitors see. */
import React, { useEffect, useState } from 'react';
import { adminGet, adminPost } from '../api';
import { ErrorBox, Loading, Switch, useLoad, useUi } from '../ui';
import { SITE_PAGES } from '../../lib/sitePages';

const FALLBACK = {
  enabled: false,
  title: "We'll be back soon",
  message: 'We are updating PREMium Mind. Your notes and courses are safe. Please check back in a little while.',
  returnAt: '',
  whatsapp: '',
  pages: [],
};

export default function Maintenance() {
  const { toast, confirm } = useUi();
  const { data, error, loading, reload } = useLoad(async () => {
    const saved = await adminGet('maintenance.php');
    if (saved.config) return saved.config;
    // never saved from here yet: start from the website's own maintenance.json
    try {
      const res = await fetch('/maintenance.json', { cache: 'no-store' });
      const file = await res.json();
      return { ...FALLBACK, enabled: !!file.enabled, title: file.title || FALLBACK.title, message: file.message || FALLBACK.message, returnAt: file.returnAt || '', whatsapp: file.whatsapp || '', pages: [] };
    } catch {
      return FALLBACK;
    }
  });
  const [form, setForm] = useState(FALLBACK);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (data) setForm({ ...FALLBACK, ...data, pages: Array.isArray(data.pages) ? data.pages : [] });
  }, [data]);

  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  const save = async (next, okText) => {
    setSaving(true);
    try {
      const r = await adminPost('maintenance.php', next);
      setForm({ ...FALLBACK, ...r.config, pages: r.config.pages || [] });
      toast(okText);
      return true;
    } catch (e) {
      toast(e.message, 'error');
      return false;
    } finally {
      setSaving(false);
    }
  };

  const toggle = async (on) => {
    const ok = await confirm(
      on
        ? { title: 'Turn maintenance mode ON?', text: 'Every visitor will see the “We’ll be back soon” page. The admin panel stays open for you.', confirmText: 'Turn ON', danger: true }
        : { title: 'Turn maintenance mode OFF?', text: 'The website opens for everyone again.', confirmText: 'Turn OFF' }
    );
    if (ok) save({ ...form, enabled: on }, on ? 'Maintenance mode is ON' : 'Website is live again');
  };

  const togglePage = (path, on) => {
    const pages = on ? [...new Set([...form.pages, path])] : form.pages.filter((p) => p !== path);
    const label = SITE_PAGES.find((p) => p.path === path)?.label || path;
    save({ ...form, pages }, on ? `${label} is closed` : `${label} is open`);
  };

  if (loading) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;

  return (
    <>
      <div className={`adm-card adm-maint ${form.enabled ? 'on' : ''}`}>
        <div className="adm-row between" style={{ flexWrap: 'nowrap' }}>
          <div>
            <b style={{ fontSize: '1.1rem' }}>{form.enabled ? 'Maintenance mode is ON' : 'Website is live'}</b>
            <div className="adm-muted">
              {form.enabled ? 'Visitors see the maintenance page. It can take up to a minute to reach everyone.' : 'Everyone can use the website normally.'}
            </div>
          </div>
          <Switch checked={form.enabled} disabled={saving} onChange={toggle} label="Maintenance mode" />
        </div>
      </div>

      <div className="adm-card" style={{ marginTop: 16 }}>
        <div className="adm-section-head">
          <h4>Close single pages</h4>
          <span className="adm-hint">
            Close just one page and keep the rest of the website open. {form.enabled ? 'The whole website is closed right now (main switch is ON), so every page is closed anyway.' : 'The rest of the website stays open.'}
          </span>
        </div>
        <div className="adm-flagrows">
          {SITE_PAGES.map((p) => {
            const closed = form.pages.includes(p.path);
            return (
              <div key={p.path} className={`adm-flagrow${closed ? ' on' : ''}`}>
                <div>
                  <b>{p.label}</b>
                  <span className="adm-hint">
                    {p.path}
                    {p.warn ? ` · ${p.warn}` : ''}
                  </span>
                </div>
                <div className="adm-row" style={{ flexWrap: 'nowrap' }}>
                  <span className={`adm-chip ${closed ? 'bad' : 'ok'}`}>{closed ? 'Closed' : 'Open'}</span>
                  <Switch checked={closed} disabled={saving} onChange={(on) => togglePage(p.path, on)} label={`Close ${p.label}`} />
                </div>
              </div>
            );
          })}
        </div>
      </div>

      <div className="adm-card" style={{ marginTop: 16 }}>
        <div className="adm-section-head">
          <h4>What visitors see</h4>
          <span className="adm-hint">Saved text is used the next time the page is opened.</span>
        </div>
        <div className="adm-form-grid">
          <div className="adm-field full">
            <label htmlFor="m_title">Heading</label>
            <input id="m_title" className="adm-input" value={form.title} onChange={(e) => set('title', e.target.value)} maxLength={120} />
          </div>
          <div className="adm-field full">
            <label htmlFor="m_msg">Message</label>
            <textarea id="m_msg" className="adm-textarea" rows={4} value={form.message} onChange={(e) => set('message', e.target.value)} maxLength={600} />
          </div>
          <div className="adm-field">
            <label htmlFor="m_ret">Back at (optional)</label>
            <input id="m_ret" className="adm-input" type="datetime-local" value={form.returnAt} onChange={(e) => set('returnAt', e.target.value)} />
            <span className="adm-hint">Visitors see a countdown to this time.</span>
          </div>
          <div className="adm-field">
            <label htmlFor="m_wa">WhatsApp support number</label>
            <input id="m_wa" className="adm-input" value={form.whatsapp} onChange={(e) => set('whatsapp', e.target.value)} placeholder="91XXXXXXXXXX" inputMode="numeric" />
            <span className="adm-hint">Country code + number, digits only.</span>
          </div>
        </div>
        <div className="adm-row end">
          <button className="adm-btn" disabled={saving} onClick={() => save(form, 'Saved')}>
            {saving ? 'Saving…' : 'Save text'}
          </button>
        </div>
      </div>
    </>
  );
}
