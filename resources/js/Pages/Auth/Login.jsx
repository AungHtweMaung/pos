import { Head, useForm, usePage } from '@inertiajs/react';
import ThemeToggle from '../../Components/ThemeToggle';

export default function Login() {
    const { app } = usePage().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        username: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post('/login', {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Sign in" />

            <div className="auth-shell bg-body-secondary px-3">
                <div className="position-absolute top-0 end-0 p-3">
                    <ThemeToggle className="btn-sm" />
                </div>

                <div className="card shadow-sm border-0" style={{ maxWidth: '24rem', width: '100%' }}>
                    <div className="card-body p-4 p-sm-5">
                        <div className="text-center mb-4">
                            <i className="bi bi-shop fs-1 text-primary"></i>
                            <h1 className="h4 mt-2 mb-1">{app?.name || 'Grocery POS'}</h1>
                            <p className="text-body-secondary small mb-0">
                                Sign in to continue
                            </p>
                        </div>

                        <form onSubmit={submit} noValidate>
                            <div className="mb-3">
                                <label htmlFor="username" className="form-label">
                                    Username
                                </label>
                                <div className="input-group has-validation">
                                    <span className="input-group-text">
                                        <i className="bi bi-person"></i>
                                    </span>
                                    <input
                                        id="username"
                                        type="text"
                                        name="username"
                                        className={`form-control ${errors.username ? 'is-invalid' : ''}`}
                                        value={data.username}
                                        autoComplete="username"
                                        autoFocus
                                        onChange={(e) => setData('username', e.target.value)}
                                    />
                                    {errors.username && (
                                        <div className="invalid-feedback">{errors.username}</div>
                                    )}
                                </div>
                            </div>

                            <div className="mb-3">
                                <label htmlFor="password" className="form-label">
                                    Password
                                </label>
                                <div className="input-group has-validation">
                                    <span className="input-group-text">
                                        <i className="bi bi-lock"></i>
                                    </span>
                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        className={`form-control ${errors.password ? 'is-invalid' : ''}`}
                                        value={data.password}
                                        autoComplete="current-password"
                                        onChange={(e) => setData('password', e.target.value)}
                                    />
                                    {errors.password && (
                                        <div className="invalid-feedback">{errors.password}</div>
                                    )}
                                </div>
                            </div>

                            <div className="form-check mb-3">
                                <input
                                    id="remember"
                                    type="checkbox"
                                    className="form-check-input"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                />
                                <label htmlFor="remember" className="form-check-label">
                                    Remember me
                                </label>
                            </div>

                            <button
                                type="submit"
                                className="btn btn-primary w-100"
                                disabled={processing}
                            >
                                {processing ? (
                                    <>
                                        <span
                                            className="spinner-border spinner-border-sm me-2"
                                            role="status"
                                            aria-hidden="true"
                                        ></span>
                                        Signing in…
                                    </>
                                ) : (
                                    <>
                                        <i className="bi bi-box-arrow-in-right me-2"></i>
                                        Sign in
                                    </>
                                )}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}
