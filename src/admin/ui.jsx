/**
 * Small building blocks every admin tab can use:
 *   const { toast, confirm } = useUi();
 *   toast('Saved');                    toast('Failed', 'error');
 *   if (await confirm({ title: 'Delete?', text: '...', danger: true })) { ... }
 *   <Modal title="Edit" onClose={...}>...</Modal>
 *   <Switch checked={x} onChange={fn} />
 *   <Loading />   <Empty text="Nothing here" />
 */
import React, { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';

const UiContext = createContext(null);
export const useUi = () => useContext(UiContext);

export function UiProvider({ children }) {
  const [toasts, setToasts] = useState([]);
  const [ask, setAsk] = useState(null);
  const nextId = useRef(1);

  const toast = useCallback((text, type = 'ok') => {
    const id = nextId.current++;
    setToasts((t) => [...t, { id, text, type }]);
    setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), type === 'error' ? 5000 : 2600);
  }, []);

  const confirm = useCallback(
    (opts) => new Promise((resolve) => setAsk({ ...opts, resolve })),
    []
  );

  const close = (answer) => {
    ask?.resolve(answer);
    setAsk(null);
  };

  return (
    <UiContext.Provider value={{ toast, confirm }}>
      {children}
      <div className="adm-toasts">
        {toasts.map((t) => (
          <div key={t.id} className={`adm-toast ${t.type}`}>
            {t.text}
          </div>
        ))}
      </div>
      {ask && (
        <Modal title={ask.title || 'Are you sure?'} onClose={() => close(false)} small>
          {ask.text && <p className="adm-muted">{ask.text}</p>}
          <div className="adm-row end">
            <button className="adm-btn ghost" onClick={() => close(false)}>
              Cancel
            </button>
            <button className={`adm-btn ${ask.danger ? 'danger' : ''}`} onClick={() => close(true)}>
              {ask.confirmText || 'Yes, continue'}
            </button>
          </div>
        </Modal>
      )}
    </UiContext.Provider>
  );
}

export function Modal({ title, onClose, children, small, wide }) {
  useEffect(() => {
    const onKey = (e) => e.key === 'Escape' && onClose?.();
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);
  return (
    <div className="adm-overlay" onMouseDown={(e) => e.target === e.currentTarget && onClose?.()}>
      <div className={`adm-modal${small ? ' small' : ''}${wide ? ' wide' : ''}`} role="dialog" aria-modal="true">
        <div className="adm-modal-head">
          <h3>{title}</h3>
          <button className="adm-x" onClick={onClose} aria-label="Close">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
          </button>
        </div>
        <div className="adm-modal-body">{children}</div>
      </div>
    </div>
  );
}

export function Switch({ checked, onChange, disabled, label }) {
  return (
    <label className={`adm-switch${disabled ? ' off' : ''}`} title={label}>
      <input type="checkbox" checked={!!checked} disabled={disabled} onChange={(e) => onChange?.(e.target.checked)} />
      <span />
    </label>
  );
}

export const Loading = ({ text = 'Loading…' }) => <div className="adm-state">{text}</div>;
export const Empty = ({ text }) => <div className="adm-state">{text}</div>;

export function ErrorBox({ error, onRetry }) {
  return (
    <div className="adm-state error">
      <div>{error?.message || 'Something went wrong.'}</div>
      {onRetry && (
        <button className="adm-btn" onClick={onRetry} style={{ marginTop: 10 }}>
          Try again
        </button>
      )}
    </div>
  );
}

/** Loads data when the tab opens: const { data, error, loading, reload } = useLoad(() => adminGet('x.php'), [dep]) */
export function useLoad(fn, deps = []) {
  const [state, setState] = useState({ data: null, error: null, loading: true });
  const fnRef = useRef(fn);
  fnRef.current = fn;
  const load = useCallback(() => {
    setState((s) => ({ ...s, loading: true, error: null }));
    fnRef
      .current()
      .then((data) => setState({ data, error: null, loading: false }))
      .catch((error) => setState({ data: null, error, loading: false }));
  }, []);
  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(load, deps);
  return { ...state, reload: load };
}

export const money = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });

export function fmtDate(s) {
  if (!s) return '—';
  const d = new Date(String(s).replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return s;
  return d.toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
