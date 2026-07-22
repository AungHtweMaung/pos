import { Head, Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

export default function CashierCreate({ roles }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        username: '',
        password: '',
        password_confirmation: '',
        role: 'cashier',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/cashiers');
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="h4 mb-0">New account</h1>
                    <Link
                        href="/cashiers"
                        className="small text-body-secondary text-decoration-none"
                    >
                        <i className="bi bi-arrow-left me-1"></i>Back to cashiers
                    </Link>
                </div>
            }
        >
            <Head title="New account" />

            <div className="card shadow-sm" style={{ maxWidth: '32rem' }}>
                <form onSubmit={submit} className="card-body">
                    <div className="mb-3">
                        <label htmlFor="name" className="form-label">Name</label>
                        <input
                            id="name"
                            type="text"
                            autoFocus
                            className={`form-control ${errors.name ? 'is-invalid' : ''}`}
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        {errors.name && <div className="invalid-feedback">{errors.name}</div>}
                    </div>

                    <div className="mb-3">
                        <label htmlFor="username" className="form-label">Username</label>
                        <input
                            id="username"
                            type="text"
                            autoComplete="off"
                            className={`form-control ${errors.username ? 'is-invalid' : ''}`}
                            value={data.username}
                            onChange={(e) => setData('username', e.target.value)}
                        />
                        {errors.username && (
                            <div className="invalid-feedback">{errors.username}</div>
                        )}
                        <div className="form-text">
                            Letters, numbers, dashes and underscores only.
                        </div>
                    </div>

                    <div className="mb-3">
                        <label htmlFor="role" className="form-label">Role</label>
                        <select
                            id="role"
                            className={`form-select ${errors.role ? 'is-invalid' : ''}`}
                            value={data.role}
                            onChange={(e) => setData('role', e.target.value)}
                        >
                            {roles.map((r) => (
                                <option key={r} value={r}>
                                    {r === 'admin' ? 'Admin' : 'Cashier'}
                                </option>
                            ))}
                        </select>
                        {errors.role && <div className="invalid-feedback">{errors.role}</div>}
                    </div>

                    <div className="row">
                        <div className="col-md-6 mb-3">
                            <label htmlFor="password" className="form-label">Password</label>
                            <input
                                id="password"
                                type="password"
                                autoComplete="new-password"
                                className={`form-control ${errors.password ? 'is-invalid' : ''}`}
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                            />
                            {errors.password && (
                                <div className="invalid-feedback">{errors.password}</div>
                            )}
                        </div>
                        <div className="col-md-6 mb-3">
                            <label htmlFor="password_confirmation" className="form-label">
                                Confirm
                            </label>
                            <input
                                id="password_confirmation"
                                type="password"
                                autoComplete="new-password"
                                className="form-control"
                                value={data.password_confirmation}
                                onChange={(e) =>
                                    setData('password_confirmation', e.target.value)
                                }
                            />
                        </div>
                    </div>

                    <div className="d-flex gap-2">
                        <button type="submit" className="btn btn-primary" disabled={processing}>
                            {processing ? 'Creating…' : 'Create account'}
                        </button>
                        <Link href="/cashiers" className="btn btn-outline-secondary">
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
