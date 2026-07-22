import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

const money = (n) =>
    Number(n || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const STATUS_BADGE = {
    completed: 'text-bg-success',
    voided: 'text-bg-secondary',
    refunded: 'text-bg-warning',
};

export default function SalesIndex() {
    const { sales, filters } = usePage().props;
    const [q, setQ] = useState(filters?.q || '');
    const [status, setStatus] = useState(filters?.status || '');

    const search = (e) => {
        e.preventDefault();
        router.get(
            '/sales',
            { q, status },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="h4 mb-0">Sales history</h1>
                    <p className="text-body-secondary small mb-0">
                        Every completed, voided, or refunded sale.
                    </p>
                </div>
            }
        >
            <Head title="Sales" />

            <form onSubmit={search} className="mb-3">
                <div className="row g-2">
                    <div className="col-md-6">
                        <div className="input-group">
                            <span className="input-group-text">
                                <i className="bi bi-search"></i>
                            </span>
                            <input
                                type="search"
                                className="form-control"
                                placeholder="Sale # or cashier name…"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                            />
                        </div>
                    </div>
                    <div className="col-md-4">
                        <select
                            className="form-select"
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                        >
                            <option value="">All statuses</option>
                            <option value="completed">Completed</option>
                            <option value="voided">Voided</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>
                    <div className="col-md-2">
                        <button type="submit" className="btn btn-outline-secondary w-100">
                            Filter
                        </button>
                    </div>
                </div>
            </form>

            <div className="card shadow-sm">
                <div className="table-responsive">
                    <table className="table table-hover mb-0 align-middle">
                        <thead className="table-light">
                            <tr>
                                <th>#</th>
                                <th>When</th>
                                <th>Cashier</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th className="text-end">Grand total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {sales.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="text-center text-body-secondary py-4">
                                        No sales match this filter.
                                    </td>
                                </tr>
                            )}
                            {sales.data.map((s) => (
                                <tr key={s.id}>
                                    <td className="fw-semibold">#{s.id}</td>
                                    <td>{new Date(s.created_at).toLocaleString()}</td>
                                    <td>{s.cashier?.name || '—'}</td>
                                    <td>
                                        <span className="badge text-bg-light">
                                            {s.payment_method}
                                        </span>
                                    </td>
                                    <td>
                                        <span
                                            className={`badge ${
                                                STATUS_BADGE[s.status] || 'text-bg-light'
                                            }`}
                                        >
                                            {s.status}
                                        </span>
                                    </td>
                                    <td className="text-end">{money(s.grand_total)}</td>
                                    <td className="text-end">
                                        <Link
                                            href={`/sales/${s.id}`}
                                            className="btn btn-sm btn-outline-secondary me-1"
                                        >
                                            <i className="bi bi-eye"></i>
                                        </Link>
                                        <Link
                                            href={`/sales/${s.id}/receipt`}
                                            className="btn btn-sm btn-outline-primary"
                                        >
                                            <i className="bi bi-receipt"></i>
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {sales.links && sales.data.length > 0 && (
                <nav className="mt-3">
                    <ul className="pagination pagination-sm justify-content-center mb-0">
                        {sales.links.map((link, i) => (
                            <li
                                key={i}
                                className={`page-item ${link.active ? 'active' : ''} ${
                                    !link.url ? 'disabled' : ''
                                }`}
                            >
                                <Link
                                    className="page-link"
                                    href={link.url || '#'}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    preserveScroll
                                    preserveState
                                />
                            </li>
                        ))}
                    </ul>
                </nav>
            )}
        </AuthenticatedLayout>
    );
}
