import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

import { money } from '../../money';

const dt = (v) => (v ? new Date(v).toLocaleString() : '—');

function OpenShiftForm() {
    const { data, setData, post, processing, errors } = useForm({
        opening_float: '0',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/shift/open', { preserveScroll: true });
    };

    return (
        <div className="card shadow-sm" style={{ maxWidth: '32rem' }}>
            <form onSubmit={submit} className="card-body">
                <h2 className="h5 mb-1">Start a shift</h2>
                <p className="text-body-secondary small">
                    Count the cash already in the drawer (the opening float) and enter it
                    to begin.
                </p>

                <div className="mb-3">
                    <label htmlFor="opening_float" className="form-label">
                        Opening float
                    </label>
                    <input
                        id="opening_float"
                        type="number"
                        step="0.01"
                        min="0"
                        autoFocus
                        className={`form-control ${errors.opening_float ? 'is-invalid' : ''}`}
                        value={data.opening_float}
                        onChange={(e) => setData('opening_float', e.target.value)}
                    />
                    {errors.opening_float && (
                        <div className="invalid-feedback">{errors.opening_float}</div>
                    )}
                </div>

                <button type="submit" className="btn btn-primary" disabled={processing}>
                    <i className="bi bi-play-circle me-1"></i>
                    {processing ? 'Opening…' : 'Open shift'}
                </button>
            </form>
        </div>
    );
}

function CloseShiftForm({ current }) {
    const { data, setData, post, processing, errors } = useForm({
        counted_cash: '',
    });

    const counted = Number(data.counted_cash) || 0;
    const expected = Number(current.expected_cash);
    const diff = data.counted_cash === '' ? null : Math.round((counted - expected) * 100) / 100;

    const submit = (e) => {
        e.preventDefault();
        if (!confirm('Close this shift? This finalizes the reconciliation.')) return;
        post(`/shift/${current.id}/close`, { preserveScroll: true });
    };

    return (
        <div className="row g-3">
            <div className="col-lg-6">
                <div className="card shadow-sm h-100">
                    <div className="card-body">
                        <h2 className="h5 mb-3">Current shift</h2>
                        <dl className="row mb-0">
                            <dt className="col-6 text-body-secondary fw-normal">Opened</dt>
                            <dd className="col-6 text-end">{dt(current.opened_at)}</dd>

                            <dt className="col-6 text-body-secondary fw-normal">
                                Opening float
                            </dt>
                            <dd className="col-6 text-end">{money(current.opening_float)}</dd>

                            <dt className="col-6 text-body-secondary fw-normal">
                                Cash sales
                            </dt>
                            <dd className="col-6 text-end">+{money(current.cash_sales)}</dd>

                            <dt className="col-6 fw-semibold border-top pt-2">
                                Expected in drawer
                            </dt>
                            <dd className="col-6 text-end fw-semibold border-top pt-2 fs-5">
                                {money(current.expected_cash)}
                            </dd>
                        </dl>
                        <p className="text-body-secondary small mt-3 mb-0">
                            Expected = opening float + cash sales taken during this shift.
                            Card and QR payments don't affect the drawer.
                        </p>
                    </div>
                </div>
            </div>

            <div className="col-lg-6">
                <div className="card shadow-sm h-100">
                    <form onSubmit={submit} className="card-body">
                        <h2 className="h5 mb-3">Close &amp; reconcile</h2>

                        <div className="mb-3">
                            <label htmlFor="counted_cash" className="form-label">
                                Counted cash
                            </label>
                            <input
                                id="counted_cash"
                                type="number"
                                step="0.01"
                                min="0"
                                autoFocus
                                className={`form-control form-control-lg ${
                                    errors.counted_cash ? 'is-invalid' : ''
                                }`}
                                value={data.counted_cash}
                                onChange={(e) => setData('counted_cash', e.target.value)}
                            />
                            {errors.counted_cash && (
                                <div className="invalid-feedback">{errors.counted_cash}</div>
                            )}
                            <div className="form-text">
                                Count everything in the drawer and enter the total.
                            </div>
                        </div>

                        {diff !== null && (
                            <div
                                className={`alert py-2 d-flex justify-content-between ${
                                    diff === 0
                                        ? 'alert-success'
                                        : diff > 0
                                          ? 'alert-info'
                                          : 'alert-danger'
                                }`}
                            >
                                <span>
                                    {diff === 0
                                        ? 'Balanced'
                                        : diff > 0
                                          ? 'Over'
                                          : 'Short'}
                                </span>
                                <span className="fw-semibold">
                                    {diff > 0 ? '+' : ''}
                                    {money(diff)}
                                </span>
                            </div>
                        )}

                        <button
                            type="submit"
                            className="btn btn-danger"
                            disabled={processing || data.counted_cash === ''}
                        >
                            <i className="bi bi-stop-circle me-1"></i>
                            {processing ? 'Closing…' : 'Close shift'}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
}

export default function ShiftIndex() {
    const { current, recent, auth } = usePage().props;

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 className="h4 mb-0">End of Shift</h1>
                        <p className="text-body-secondary small mb-0">
                            Open a drawer, then reconcile cash when your shift ends.
                        </p>
                    </div>
                    {auth.user.is_admin && (
                        <Link href="/shifts" className="btn btn-outline-secondary">
                            <i className="bi bi-clock-history me-1"></i>All shifts
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="End of Shift" />

            <div className="mb-4">
                {current ? <CloseShiftForm current={current} /> : <OpenShiftForm />}
            </div>

            <h2 className="h6 text-body-secondary mb-2">Your recent shifts</h2>
            <div className="card shadow-sm">
                <div className="table-responsive">
                    <table className="table table-hover mb-0 align-middle">
                        <thead className="table-light">
                            <tr>
                                <th>Opened</th>
                                <th>Closed</th>
                                <th className="text-end">Float</th>
                                <th className="text-end">Expected</th>
                                <th className="text-end">Counted</th>
                                <th className="text-end">Difference</th>
                            </tr>
                        </thead>
                        <tbody>
                            {recent.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-center text-body-secondary py-4">
                                        No closed shifts yet.
                                    </td>
                                </tr>
                            )}
                            {recent.map((s) => (
                                <tr key={s.id}>
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
        </AuthenticatedLayout>
    );
}

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
