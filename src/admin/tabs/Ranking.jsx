/** Drag the products into the order they should appear on the home page, then Save. */
import React, { useEffect, useState } from 'react';
import { adminGet, adminPost } from '../api';
import Icon from '../Icon';
import { ErrorBox, Loading, money, useLoad, useUi } from '../ui';

export default function Ranking() {
  const { toast } = useUi();
  const { data, error, loading, reload } = useLoad(() => adminGet('ranking.php'));
  const [list, setList] = useState([]);
  const [dragId, setDragId] = useState(null);
  const [overId, setOverId] = useState(null);
  const [dirty, setDirty] = useState(false);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (data) {
      setList(data.courses);
      setDirty(false);
    }
  }, [data]);

  const drop = (targetId) => {
    if (dragId == null || dragId === targetId) return;
    const next = list.filter((c) => c.id !== dragId);
    const idx = next.findIndex((c) => c.id === targetId);
    next.splice(idx, 0, list.find((c) => c.id === dragId));
    setList(next);
    setDirty(true);
  };

  const move = (i, dir) => {
    const j = i + dir;
    if (j < 0 || j >= list.length) return;
    const next = [...list];
    [next[i], next[j]] = [next[j], next[i]];
    setList(next);
    setDirty(true);
  };

  const save = async () => {
    setSaving(true);
    try {
      await adminPost('ranking.php', { order: list.map((c) => Number(c.id)) });
      toast('Home order saved');
      setDirty(false);
    } catch (e) {
      toast(e.message, 'error');
    }
    setSaving(false);
  };

  if (loading) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;

  return (
    <>
      <div className="adm-toolbar">
        <span className="adm-muted grow">Top of the list shows first on the home page. Drag a row, or use the arrows.</span>
        <button className="adm-btn" onClick={save} disabled={!dirty || saving}>
          {saving ? 'Saving…' : 'Save order'}
        </button>
      </div>
      <ul className="adm-rank">
        {list.map((c, i) => (
          <li
            key={c.id}
            draggable
            className={`${dragId === c.id ? 'drag' : ''} ${overId === c.id && dragId !== c.id ? 'over' : ''}`}
            onDragStart={() => setDragId(c.id)}
            onDragOver={(e) => {
              e.preventDefault();
              setOverId(c.id);
            }}
            onDragEnd={() => {
              setDragId(null);
              setOverId(null);
            }}
            onDrop={() => {
              drop(c.id);
              setDragId(null);
              setOverId(null);
            }}
          >
            <span className="adm-grip"><Icon name="grip" /></span>
            <span className="adm-pos">{i + 1}</span>
            <img className="adm-thumb" src={c.image} alt="" onError={(e) => (e.currentTarget.style.visibility = 'hidden')} />
            <div style={{ flex: 1, minWidth: 0 }}>
              <b>{c.title}</b>
              <div className="adm-muted small">
                {c.category} · {money(c.price)}
              </div>
            </div>
            <button className="adm-btn ghost small" onClick={() => move(i, -1)} disabled={i === 0}>
              ▲
            </button>
            <button className="adm-btn ghost small" onClick={() => move(i, 1)} disabled={i === list.length - 1}>
              ▼
            </button>
          </li>
        ))}
      </ul>
    </>
  );
}
