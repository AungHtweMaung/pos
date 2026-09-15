import { Head, Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import { money } from '../../money';

const dt = (v) => (v ? new Date(v).toLocaleString() : '—');

const STATUS_BADGE = {
    completed: 'text-bg-success',
    voided: 'text-bg-secondary',
    refunded: 'text-bg-warning',
};

export default function MySales() {
    const { sales, hasOpenShift } = usePage().props;

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 className="h4 mb-0">My sales</h1>
                        <p className="text-body-secondary small mb-0">
                            Sales you rang up. Reprint a receipt, or void a mistake made
                            during your current shift.
                        </p>
                    </div>
                    <Link href="/pos" className="btn btn-primary">
                        <i className="bi bi-cart me-1"></i>New sale
                    </Link>
                </div>
            }
        >
            <Head title="My sales" />

            {!hasOpenShift && (
                <div className="alert alert-info d-flex align-items-center" role="alert">
                    <i className="bi bi-info-circle me-2"></i>
                    <div>
                        You have no open shift, so sales can't be voided here. Open a shift
                        from <Link href="/shift">End of Shift</Link>, or ask an admin to void.
                    </div>
                </div>
            )}

            <div className="card shadow-sm">
                <div className="table-responsive">
                    <table className="table table-hover mb-0 align-middle">
                        <thead className="table-light">
                            <tr>
                                <th>#</th>
                                <th>When</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th className="text-end">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {sales.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-center text-body-secondary py-4">
                                        You haven't rung up any sales yet.
                                    </td>
                                </tr>
                            )}
                            {sales.data.map((s) => (
                                <tr key={s.id}>
                                    <td className="fw-semibold">#{s.id}</td>
                                    <td>{dt(s.created_at)}</td>
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
                                            href={`/sales/${s.id}/receipt`}
                                            className="btn btn-sm btn-outline-primary me-1"
                                            title="Receipt"
                                        >
                                            <i className="bi bi-receipt"></i>
                                        </Link>
                                        {s.can_void ? (
                                            <Link
                                                href={`/sales/${s.id}`}
                                                className="btn btn-sm btn-outline-danger"
                                                title="Void this sale"
                                            >
                                                <i className="bi bi-x-octagon me-1"></i>Void
                                            </Link>
                                        ) : (
                                            <Link
                                                href={`/sales/${s.id}`}
                                                className="btn btn-sm btn-outline-secondary"
                                                title="View detail"
                                            >
                                                <i className="bi bi-eye"></i>
                                            </Link>
                                        )}
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
