import { Head, Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

import { money } from '../../money';

const dt = (v) => (v ? new Date(v).toLocaleString() : '—');

function DifferenceBadge({ value }) {
    if (value === null || value === undefined) return <span>—</span>;
    const v = Number(value);
    const cls = v === 0 ? 'text-bg-success' : v > 0 ? 'text-bg-info' : 'text-bg-danger';
    return (
        <span className={`badge ${cls}`}>
            {v > 0 ? '+' : ''}
            {money(v)}
        </span>
    );
}

export default function ShiftHistory() {
    const { shifts } = usePage().props;

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 className="h4 mb-0">Shift history</h1>
                        <p className="text-body-secondary small mb-0">
                            Closed shifts across all cashiers.
                        </p>
                    </div>
                    <Link href="/shift" className="btn btn-outline-secondary">
                        <i className="bi bi-cash-stack me-1"></i>My drawer
                    </Link>
                </div>
            }
        >
            <Head title="Shift history" />

            <div className="card shadow-sm">
                <div className="table-responsive">
                    <table className="table table-hover mb-0 align-middle">
                        <thead className="table-light">
                            <tr>
                                <th>Cashier</th>
                                <th>Opened</th>
                                <th>Closed</th>
                                <th className="text-end">Float</th>
                                <th className="text-end">Expected</th>
                                <th className="text-end">Counted</th>
                                <th className="text-end">Difference</th>
                            </tr>
                        </thead>
                        <tbody>
                            {shifts.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="text-center text-body-secondary py-4">
                                        No closed shifts yet.
                                    </td>
                                </tr>
                            )}
                            {shifts.data.map((s) => (
                                <tr key={s.id}>
                                    <td className="fw-semibold">{s.cashier?.name}</td>
                                    <td>{dt(s.opened_at)}</td>
                                    <td>{dt(s.closed_at)}</td>
                                    <td className="text-end">{money(s.opening_float)}</td>
                                    <td className="text-end">{money(s.expected_cash)}</td>
                                    <td className="text-end">{money(s.counted_cash)}</td>
                                    <td className="text-end">
                                        <DifferenceBadge value={s.difference} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {shifts.links && shifts.data.length > 0 && (
                <nav className="mt-3">
                    <ul className="pagination pagination-sm justify-content-center mb-0">
                        {shifts.links.map((link, i) => (
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
