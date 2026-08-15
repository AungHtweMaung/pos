import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

export default function CashierEdit({ user, roles }) {
    const { auth } = usePage().props;
    const isSelf = user.id === auth.user.id;

    const detailsForm = useForm({
        name: user.name,
        username: user.username,
        role: user.role,
    });

    const passwordForm = useForm({
        password: '',
        password_confirmation: '',
    });

    const saveDetails = (e) => {
        e.preventDefault();
        detailsForm.put(`/cashiers/${user.id}`, { preserveScroll: true });
    };

    const savePassword = (e) => {
        e.preventDefault();
        passwordForm.put(`/cashiers/${user.id}/password`, {
            preserveScroll: true,
            onSuccess: () => passwordForm.reset(),
        });
    };

    const toggleActive = () => {
        const verb = user.is_active ? 'deactivate' : 'activate';
        if (!confirm(`${verb === 'deactivate' ? 'Deactivate' : 'Reactivate'} ${user.name}?`)) return;
        router.post(`/cashiers/${user.id}/${verb}`, {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 className="h4 mb-0">{user.name}</h1>
                        <p className="text-body-secondary small mb-0">
                            <code>{user.username}</code>
                            <span
                                className={`badge ms-2 ${
                                    user.role === 'admin'
                                        ? 'text-bg-primary'
                                        : 'text-bg-info'
                                }`}
                            >
                                {user.role}
                            </span>
                            <span
                                className={`badge ms-2 ${
                                    user.is_active
                                        ? 'text-bg-success'
                                        : 'text-bg-secondary'
                                }`}
                            >
                                {user.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </p>
                    </div>
                    <Link href="/cashiers" className="btn btn-outline-secondary">
                        <i className="bi bi-arrow-left me-1"></i>Back
                    </Link>
                </div>
            }
        >
            <Head title={`Edit ${user.name}`} />

            <div className="row g-3">
                <div className="col-lg-7">
                    <div className="card shadow-sm mb-3">
                        <form onSubmit={saveDetails} className="card-body">
                            <h2 className="h6 mb-3">Profile</h2>

                            <div className="mb-3">
                                <label htmlFor="name" className="form-label">Name</label>
                                <input
                                    id="name"
                                    type="text"
                                    className={`form-control ${
                                        detailsForm.errors.name ? 'is-invalid' : ''
                                    }`}
                                    value={detailsForm.data.name}
                                    onChange={(e) => detailsForm.setData('name', e.target.value)}
                                />
                                {detailsForm.errors.name && (
                                    <div className="invalid-feedback">
                                        {detailsForm.errors.name}
                                    </div>
                                )}
                            </div>

                            <div className="mb-3">
                                <label htmlFor="username" className="form-label">Username</label>
                                <input
                                    id="username"
                                    type="text"
                                    autoComplete="off"
                                    className={`form-control ${
                                        detailsForm.errors.username ? 'is-invalid' : ''
                                    }`}
                                    value={detailsForm.data.username}
                                    onChange={(e) =>
                                        detailsForm.setData('username', e.target.value)
                                    }
                                />
                                {detailsForm.errors.username && (
                                    <div className="invalid-feedback">
                                        {detailsForm.errors.username}
                                    </div>
                                )}
                            </div>

                            <div className="mb-3">
                                <label htmlFor="role" className="form-label">Role</label>
                                <select
                                    id="role"
                                    className={`form-select ${
                                        detailsForm.errors.role ? 'is-invalid' : ''
                                    }`}
                                    value={detailsForm.data.role}
                                    onChange={(e) => detailsForm.setData('role', e.target.value)}
                                    disabled={isSelf}
                                >
                                    {roles.map((r) => (
                                        <option key={r} value={r}>
                                            {r === 'admin' ? 'Admin' : 'Cashier'}
                                        </option>
                                    ))}
                                </select>
                                {detailsForm.errors.role && (
                                    <div className="invalid-feedback">
                                        {detailsForm.errors.role}
                                    </div>
                                )}
                                {isSelf && (
                                    <div className="form-text">
                                        You can't change your own role.
                                    </div>
                                )}
                            </div>

                            <button
                                type="submit"
                                className="btn btn-primary"
                                disabled={detailsForm.processing}
                            >
                                {detailsForm.processing ? 'Saving…' : 'Save changes'}
                            </button>
                        </form>
                    </div>

                    <div className="card shadow-sm">
                        <form onSubmit={savePassword} className="card-body">
                            <h2 className="h6 mb-3">Reset password</h2>
                            <p className="small text-body-secondary">
                                Sets a new password for this account. The user isn't notified —
                                share it with them directly.
                            </p>

                            <div className="row">
                                <div className="col-md-6 mb-3">
                                    <label htmlFor="password" className="form-label">
                                        New password
                                    </label>
                                    <input
                                        id="password"
                                        type="password"
                                        autoComplete="new-password"
                                        className={`form-control ${
                                            passwordForm.errors.password ? 'is-invalid' : ''
                                        }`}
                                        value={passwordForm.data.password}
                                        onChange={(e) =>
                                            passwordForm.setData('password', e.target.value)
                                        }
                                    />
                                    {passwordForm.errors.password && (
                                        <div className="invalid-feedback">
                                            {passwordForm.errors.password}
                                        </div>
                                    )}
                                </div>
                                <div className="col-md-6 mb-3">
                                    <label
                                        htmlFor="password_confirmation"
                                        className="form-label"
                                    >
                                        Confirm
                                    </label>
                                    <input
                                        id="password_confirmation"
                                        type="password"
                                        autoComplete="new-password"
                                        className="form-control"
                                        value={passwordForm.data.password_confirmation}
                                        onChange={(e) =>
                                            passwordForm.setData(
                                                'password_confirmation',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>

                            <button
                                type="submit"
                                className="btn btn-outline-primary"
                                disabled={passwordForm.processing}
                            >
                                {passwordForm.processing ? 'Saving…' : 'Reset password'}
                            </button>
                        </form>
                    </div>
                </div>

                <div className="col-lg-5">
                    <div className="card shadow-sm">
                        <div className="card-body">
                            <h2 className="h6 mb-2">
                                {user.is_active ? 'Deactivate' : 'Reactivate'} account
                            </h2>
                            <p className="small text-body-secondary">
                                {user.is_active
                                    ? "Deactivated users can't sign in. Their past sales stay in history."
                                    : 'Reactivating restores their sign-in access.'}
                            </p>
                            <button
                                type="button"
                                className={`btn w-100 ${
                                    user.is_active ? 'btn-outline-danger' : 'btn-outline-success'
                                }`}
                                disabled={isSelf}
                                onClick={toggleActive}
                            >
                                {user.is_active ? (
                                    <>
                                        <i className="bi bi-slash-circle me-1"></i>Deactivate
                                    </>
                                ) : (
                                    <>
                                        <i className="bi bi-check-circle me-1"></i>Reactivate
                                    </>
                                )}
                            </button>
                            {isSelf && (
                                <div className="form-text mt-1">
                                    You can't deactivate yourself.
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
