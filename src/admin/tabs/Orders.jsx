/** Payments: every order, check one with Cashfree, give access by hand. */
import React, { useState } from 'react';
import { adminGet, adminPost } from '../api';
import { Empty, ErrorBox, Loading, fmtDate, money, useLoad, useUi } from '../ui';

const FILTERS = [
  ['ALL', 'All'],
  ['PAID', 'Paid'],
  ['CREATED', 'Not finished'],
  ['FAILED', 'Failed'],
];
const CHIP = { PAID: 'ok', CREATED: 'warn' };

export default function Orders() {
  const { toast, confirm } = useUi();
  const [status, setStatus] = useState('ALL');
  const [page, setPage] = useState(1);
  const [busy, setBusy] = useState('');
  const { data, error, loading, reload } = useLoad(() => adminGet('orders.php', { status, page }), [status, page]);

  const act = async (o, action, ask, okText) => {
    if (ask && !(await confirm(ask))) return;
    setBusy(o.order_id + action);
    try {
      const r = await adminPost('orders.php', { action, order_id: o.order_id });
      toast(action === 'verify' ? `Cashfree says: ${r.gateway}` : okText || 'Done');
      reload();
    } catch (e) {
      toast(e.message, 'error');
    }
    setBusy('');
  };

  const pages = data ? Math.max(1, Math.ceil(data.total / data.perPage)) : 1;

  return (
    <>
      <div className="adm-toolbar">
        {FILTERS.map(([id, label]) => (
          <button
            key={id}
            className={`adm-btn ${status === id ? '' : 'ghost'}`}
            onClick={() => {
              setStatus(id);
              setPage(1);
            }}
          >
            {label}
          </button>
        ))}
      </div>

      {loading && !data ? (
        <Loading />
      ) : error ? (
        <ErrorBox error={error} onRetry={reload} />
      ) : data.orders.length === 0 ? (
        <Empty text="No orders here." />
      ) : (
        <>
          <div className="adm-table-wrap">
            <table className="adm-table">
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Student</th>
                  <th>Product</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {data.orders.map((o) => (
                  <tr key={o.order_id}>
                    <td>
                      <div className="small" style={{ marginBottom: 0 }}>{o.order_id}</div>
                      <span className="adm-muted small">{fmtDate(o.created_at)}</span>
                    </td>
                    <td>
                      <b>{o.user_name || '—'}</b>
                      <div className="adm-muted small">{o.user_email}</div>
                    </td>
                    <td>
                      {o.course_title || `Product #${o.course_id}`}
                      {o.kind === "micro" && (
                        <div>
                          <span className="adm-chip warn">Micro format</span>
                        </div>
                      )}
                    </td>
                    <td>{money(o.amount)}</td>
                    <td>
                      <span className={`adm-chip ${CHIP[o.status] || 'bad'}`}>{o.status}</span>
                      {o.paid_at && <div className="adm-muted small">{fmtDate(o.paid_at)}</div>}
                    </td>
                    <td>
                      <div className="adm-actions">
                        {o.status !== 'PAID' && (
                          <>
                            <button className="adm-btn small" disabled={!!busy} onClick={() => act(o, 'verify')}>
                              {busy === o.order_id + 'verify' ? 'Checking…' : 'Check payment'}
                            </button>
                            <button
                              className="adm-btn good small"
                              disabled={!!busy}
                              onClick={() =>
                                act(o, 'grant', {
                                  title: 'Give access by hand?',
                                  text: `${o.user_email} will get the product and this order becomes PAID, even if Cashfree has not confirmed it.`,
                                  confirmText: 'Give access',
                                }, 'Access given')
                              }
                            >
                              Give access
                            </button>
                            <button
                              className="adm-btn danger small"
                              disabled={!!busy}
                              onClick={() => act(o, 'delete', { title: 'Delete this order?', text: 'Only unpaid orders can be deleted.', danger: true, confirmText: 'Delete' }, 'Order deleted')}
                            >
                              Delete
                            </button>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
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
    </>
  );
}
