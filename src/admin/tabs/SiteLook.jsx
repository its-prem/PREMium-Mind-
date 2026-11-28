/** Site look: the photo on the home page + the ant / butterfly on-off switches. */
import React, { useEffect, useRef, useState } from 'react';
import { adminGet, adminPost } from '../api';
import { ErrorBox, Loading, Switch, useLoad, useUi } from '../ui';

const DEFAULT_IMG = '/mh.jpg';

export default function SiteLook() {
  const { toast, confirm } = useUi();
  const { data, error, loading, reload } = useLoad(() => adminGet('site_settings.php'));
  const [site, setSite] = useState({ heroImage: '', ant: true, butterfly: true });
  const [saving, setSaving] = useState(false);
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState('');
  const input = useRef(null);

  useEffect(() => {
    if (data?.site) setSite(data.site);
  }, [data]);
  useEffect(() => {
    if (!file) return setPreview('');
    const url = URL.createObjectURL(file);
    setPreview(url);
    return () => URL.revokeObjectURL(url);
  }, [file]);

  const send = async (payload, okText) => {
    setSaving(true);
    try {
      const fd = new FormData();
      Object.entries(payload).forEach(([k, v]) => fd.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : v));
      const r = await adminPost('site_settings.php', fd);
      setSite(r.site);
      toast(okText);
      return true;
    } catch (e) {
      toast(e.message, 'error');
      return false;
    } finally {
      setSaving(false);
    }
  };

  const pick = (e) => {
    const f = e.target.files?.[0] || null;
    if (f && f.size > 5 * 1048576) {
      toast('The photo is too big (max 5 MB).', 'error');
      e.target.value = '';
      return;
    }
    setFile(f);
  };
  const upload = async () => {
    if (!file) return;
    if (await send({ hero_file: file }, 'Home page photo changed')) {
      setFile(null);
      if (input.current) input.current.value = '';
    }
  };
  const reset = async () => {
    if (await confirm({ title: 'Use the original photo?', text: 'The photo you uploaded is removed and the built-in photo comes back.', confirmText: 'Use original' })) {
      send({ hero_reset: '1' }, 'Original photo is back');
    }
  };

  if (loading) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;

  return (
    <>
      <div className="adm-card">
        <div className="adm-section-head">
          <h4>Home page photo</h4>
          <span className="adm-hint">The big photo at the top of the home page. jpg, png or webp, up to 5 MB. A photo with the person on the right side looks best.</span>
        </div>
        <div className="adm-row" style={{ alignItems: 'flex-start', gap: 20 }}>
          <img
            src={preview || site.heroImage || DEFAULT_IMG}
            alt="Home page photo"
            style={{ width: 220, maxWidth: '100%', borderRadius: 12, border: '1px solid var(--adm-line, #ddd)', objectFit: 'cover' }}
          />
          <div style={{ flex: 1, minWidth: 220 }}>
            <p className="adm-muted" style={{ marginTop: 0 }}>
              {site.heroImage ? 'Your own photo is showing.' : 'The built-in photo is showing.'}
              {file ? ' New photo chosen: press “Upload & use”.' : ''}
            </p>
            <input ref={input} type="file" accept="image/jpeg,image/png,image/webp" onChange={pick} />
            <div className="adm-row" style={{ marginTop: 12 }}>
              <button className="adm-btn" disabled={!file || saving} onClick={upload}>
                {saving ? 'Uploading…' : 'Upload & use'}
              </button>
              {site.heroImage && (
                <button className="adm-btn ghost" disabled={saving} onClick={reset}>
                  Use original photo
                </button>
              )}
            </div>
            <span className="adm-hint">Visitors see the new photo within a minute (their browser re-checks once a minute).</span>
          </div>
        </div>
      </div>

      <div className="adm-card" style={{ marginTop: 16 }}>
        <div className="adm-section-head">
          <h4>Ant and butterfly</h4>
          <span className="adm-hint">The little animals that walk and fly over the website.</span>
        </div>
        <div className="adm-flagrows">
          {[
            ['butterfly', 'Butterfly (titli)'],
            ['ant', 'Ant (cheenti)'],
          ].map(([key, label]) => (
            <div key={key} className={`adm-flagrow${site[key] ? ' on' : ''}`}>
              <div>
                <b>{label}</b>
                <span className="adm-hint">{site[key] ? 'Showing on the website' : 'Hidden'}</span>
              </div>
              <div className="adm-row" style={{ flexWrap: 'nowrap' }}>
                <span className={`adm-chip ${site[key] ? 'ok' : 'bad'}`}>{site[key] ? 'On' : 'Off'}</span>
                <Switch
                  checked={!!site[key]}
                  disabled={saving}
                  onChange={(on) => send({ [key]: on }, `${label} is ${on ? 'ON' : 'OFF'}`)}
                  label={label}
                />
              </div>
            </div>
          ))}
        </div>
      </div>
    </>
  );
}
