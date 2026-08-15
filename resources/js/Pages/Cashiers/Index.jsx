import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

export default function CashiersIndex() {
    const { users, filters, auth } = usePage().props;
    const [q, setQ] = useState(filters?.q || '');
    const [role, setRole] = useState(filters?.role || '');
    const [status, setStatus] = useState(filters?.status || '');

    const search = (e) => {
        e.preventDefault();
        router.get(
            '/cashiers',
            { q, role, status },
            { preserveState: true, replace: true },
        );
    };

    const reset = () => {
        setQ('');
        setRole('');
        setStatus('');
        router.get('/cashiers', {}, { preserveState: true, replace: true });
    };

    const hasFilters = q !== '' || role !== '' || status !== '';

    const toggleActive = (user) => {
        const verb = user.is_active ? 'deactivate' : 'activate';
        if (!confirm(`${verb === 'deactivate' ? 'Deactivate' : 'Reactivate'} ${user.name}?`)) return;
        router.post(`/cashiers/${user.id}/${verb}`, {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 className="h4 mb-0">Cashiers</h1>
                        <p className="text-body-secondary small mb-0">
                            Cashier and admin accounts.
                        </p>
                    </div>
                    <Link href="/cashiers/create" className="btn btn-primary">
                        <i className="bi bi-person-plus me-1"></i>New account
                    </Link>
                </div>
            }
        >
            <Head title="Cashiers" />

            <form onSubmit={search} className="mb-3">
                <div className="row g-2">
                    <div className="col-md-5">
                        <div className="input-group">
                            <span className="input-group-text">
                                <i className="bi bi-search"></i>
                            </span>
                            <input
                                type="search"
                                className="form-control"
                                placeholder="Name or username…"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                            />
                        </div>
                    </div>
                    <div className="col-md-3">
                        <select
                            className="form-select"
                            value={role}
                            onChange={(e) => setRole(e.target.value)}
                        >
                            <option value="">All roles</option>
                            <option value="admin">Admin</option>
                            <option value="cashier">Cashier</option>
                        </select>
                    </div>
                    <div className="col-md-2">
                        <select
                            className="form-select"
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                        >
                            <option value="">Any status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div className="col-md-2">
                        <div className="d-flex gap-2">
                            <button type="submit" className="btn btn-outline-secondary flex-fill">
                                Filter
                            </button>
                            <button
                                type="button"
                                className="btn btn-outline-secondary"
                                onClick={reset}
                                disabled={!hasFilters}
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <div className="card shadow-sm">
                <div className="table-responsive">
                    <table className="table table-hover mb-0 align-middle">
                        <thead className="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-center text-body-secondary py-4">
                                        No accounts match this filter.
                                    </td>
                                </tr>
                            )}
                            {users.data.map((u) => {
                                const isSelf = u.id === auth.user.id;
                                return (
                                    <tr key={u.id}>
                                        <td>
                                            <Link
                                                href={`/cashiers/${u.id}/edit`}
                                                className="text-decoration-none fw-semibold"
                                            >
                                                {u.name}
                                            </Link>
                                            {isSelf && (
                                                <span className="badge text-bg-light ms-2">
                                                    you
                                                </span>
                                            )}
                                        </td>
                                        <td>
                                            <code>{u.username}</code>
                                        </td>
                                        <td>
                                            <span
                                                className={`badge ${
                                                    u.role === 'admin'
                                                        ? 'text-bg-primary'
                                                        : 'text-bg-info'
                                                }`}
                                            >
                                                {u.role}
                                            </span>
                                        </td>
                                        <td>
                                            {u.is_active ? (
                                                <span className="badge text-bg-success">
                                                    <i className="bi bi-check-circle me-1"></i>
                                                    Active
                                                </span>
                                            ) : (
                                                <span className="badge text-bg-secondary">
                                                    <i className="bi bi-slash-circle me-1"></i>
                                                    Inactive
                                                </span>
                                            )}
                                        </td>
                                        <td className="text-end">
                                            <Link
                                                href={`/cashiers/${u.id}/edit`}
                                                className="btn btn-sm btn-outline-secondary me-1"
                                            >
                                                <i className="bi bi-pencil"></i>
                                            </Link>
                                            <button
                                                type="button"
                                                className={`btn btn-sm ${
                                                    u.is_active
                                                        ? 'btn-outline-danger'
                                                        : 'btn-outline-success'
                                                }`}
                                                disabled={isSelf}
                                                title={
                                                    isSelf
                                                        ? "You can't deactivate yourself"
                                                        : undefined
                                                }
                                                onClick={() => toggleActive(u)}
                                            >
                                                {u.is_active ? (
                                                    <>
                                                        <i className="bi bi-slash-circle me-1"></i>
                                                        Deactivate
                                                    </>
                                                ) : (
                                                    <>
                                                        <i className="bi bi-check-circle me-1"></i>
                                                        Reactivate
                                                    </>
                                                )}
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            {users.links && users.data.length > 0 && (
                <nav className="mt-3">
                    <ul className="pagination pagination-sm justify-content-center mb-0">
                        {users.links.map((link, i) => (
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
