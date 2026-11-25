import React from 'react';
import { adminGet } from '../api';
import Icon from '../Icon';
import { ErrorBox, Loading, fmtDate, money, useLoad } from '../ui';

// Add a number card: add a line here AND the same key in server/admin/dashboard.php ($stats).
const STAT_CARDS = [
  { key: 'revenueToday', label: 'Sales today', format: money, icon: 'rupee', tone: 'green' },
  { key: 'revenue', label: 'Total sales', format: money, icon: 'trend', tone: 'indigo' },
  { key: 'paidOrders', label: 'Paid orders', icon: 'bag', tone: 'blue' },
  { key: 'students', label: 'Students', icon: 'users', tone: 'violet' },
  { key: 'courses', label: 'Live products', icon: 'box', tone: 'blue' },
  { key: 'pendingReports', label: 'Open reports', icon: 'flag', tone: 'amber' },
  { key: 'trashed', label: 'In trash', icon: 'trash', tone: 'grey' },
];

export default function Dashboard() {
  const { data, error, loading, reload } = useLoad(() => adminGet('dashboard.php'));
  if (loading) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;

  const { stats, chart, purchases } = data;
  const max = Math.max(1, ...chart.map((d) => d.amount));

  return (
    <>
      <div className="adm-grid">
        {STAT_CARDS.map((c) => (
          <div key={c.key} className="adm-card adm-stat">
            <i className={`adm-stat-ico ${c.tone}`}>
              <Icon name={c.icon} size={20} />
            </i>
            <div>
              <span>{c.label}</span>
              <b>{c.format ? c.format(stats[c.key]) : stats[c.key]}</b>
            </div>
          </div>
        ))}
      </div>

      <div className="adm-card" style={{ marginBottom: 18 }}>
        <div className="adm-row between" style={{ marginBottom: 10 }}>
          <b>Sales – last 14 days</b>
          <button className="adm-btn ghost small" onClick={reload}>
            Refresh
          </button>
        </div>
        <div className="adm-bars">
          {chart.map((d) => (
            <div key={d.date} className="adm-bar" title={`${d.date}: ${money(d.amount)} (${d.orders} orders)`}>
              {d.amount > 0 && <span>{Math.round(d.amount)}</span>}
              <i style={{ height: `${(d.amount / max) * 100}%` }} />
              <span>{d.date.slice(8)}</span>
            </div>
          ))}
        </div>
      </div>

      <div className="adm-card">
        <b>Latest purchases</b>
        {purchases.length === 0 ? (
          <p className="adm-muted">No purchases yet.</p>
        ) : (
          <div className="adm-table-wrap" style={{ border: 0, marginTop: 8 }}>
            <table className="adm-table">
              <tbody>
                {purchases.map((p, i) => (
                  <tr key={i}>
                    <td>
                      <b>{p.user_name || 'Student'}</b>
                      <div className="adm-muted small">{p.user_email}</div>
                    </td>
                    <td>{p.course_title || `Product #${p.course_id}`}</td>
                    <td>{p.course_price ? money(p.course_price) : ''}</td>
                    <td className="adm-muted">{fmtDate(p.purchased_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </>
  );
}
