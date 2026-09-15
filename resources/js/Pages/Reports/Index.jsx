import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

import { money } from '../../money';

const num = (n) => Number(n || 0).toLocaleString();
const dt = (v) => (v ? new Date(v).toLocaleString() : '—');

const RANGES = [
    { key: 'today', label: 'Today' },
    { key: 'week', label: 'This week' },
    { key: 'month', label: 'This month' },
    { key: 'custom', label: 'Custom' },
];

const METHOD_LABEL = { cash: 'Cash', qr: 'QR transfer' };

function StatCard({ label, value, sub, tone }) {
    return (
        <div className="col-6 col-lg-3">
            <div className="card shadow-sm h-100">
                <div className="card-body">
                    <div className="text-body-secondary small">{label}</div>
                    <div className={`fs-4 fw-semibold ${tone || ''}`}>{value}</div>
                    {sub && <div className="text-body-secondary small">{sub}</div>}
                </div>
            </div>
        </div>
    );
}

export default function ReportsIndex() {
    const { filters, summary, bestSellers, voids } = usePage().props;
    const [range, setRange] = useState(filters.range);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [sortBy, setSortBy] = useState('revenue');

    const applyRange = (key) => {
        setRange(key);
        if (key !== 'custom') {
            router.get('/reports', { range: key }, { preserveState: true, replace: true });
        }
    };

    const applyCustom = (e) => {
        e.preventDefault();
        router.get(
            '/reports',
            { range: 'custom', from, to },
            { preserveState: true, replace: true },
        );
    };

    const sortedSellers = [...bestSellers].sort((a, b) => {
        if (sortBy === 'units') return b.base_units - a.base_units;
        if (sortBy === 'profit') return b.profit - a.profit;
        return b.revenue - a.revenue;
    });

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 className="h4 mb-0">Reports</h1>
                        <p className="text-body-secondary small mb-0">
                            Sales, best-sellers and voids for the selected period.
                        </p>
                    </div>
                    <Link href="/shifts" className="btn btn-outline-secondary">
                        <i className="bi bi-clock-history me-1"></i>Shift history
                    </Link>
                </div>
            }
        >
            <Head title="Reports" />

            {/* Range picker */}
            <div className="card shadow-sm mb-3">
                <div className="card-body">
                    <div className="btn-group mb-2" role="group">
                        {RANGES.map((r) => (
                            <button
                                key={r.key}
                                type="button"
                                className={`btn ${
                                    range === r.key ? 'btn-primary' : 'btn-outline-primary'
                                }`}
                                onClick={() => applyRange(r.key)}
                            >
                                {r.label}
                            </button>
                        ))}
                    </div>

                    {range === 'custom' && (
                        <form onSubmit={applyCustom} className="row g-2 align-items-end mt-1">
                            <div className="col-auto">
                                <label htmlFor="from" className="form-label small mb-0">
                                    From
                                </label>
                                <input
                                    id="from"
                                    type="date"
                                    className="form-control"
                                    value={from}
                                    onChange={(e) => setFrom(e.target.value)}
                                />
                            </div>
                            <div className="col-auto">
                                <label htmlFor="to" className="form-label small mb-0">
                                    To
                                </label>
                                <input
                                    id="to"
                                    type="date"
                                    className="form-control"
                                    value={to}
                                    onChange={(e) => setTo(e.target.value)}
                                />
                            </div>
                            <div className="col-auto">
                                <button type="submit" className="btn btn-outline-secondary">
                                    Apply
                                </button>
                            </div>
                        </form>
                    )}

                    <div className="text-body-secondary small mt-2">
                        Showing <strong>{filters.from}</strong> to <strong>{filters.to}</strong>.
                    </div>
                </div>
            </div>

            {/* Summary stat cards */}
            <div className="row g-3 mb-3">
                <StatCard
                    label="Revenue"
                    value={money(summary.revenue)}
                    sub={`${num(summary.transactions)} sales`}
                />
                <StatCard
                    label="Gross profit"
                    value={money(summary.gross_profit)}
                    sub={`${summary.margin_pct}% margin`}
                    tone={summary.gross_profit < 0 ? 'text-danger' : 'text-success'}
                />
                <StatCard
                    label="Items sold"
                    value={num(summary.items_sold)}
                    sub={`avg ${money(summary.average_sale)} / sale`}
                />
                <StatCard
                    label="Voids / refunds"
                    value={money(summary.void_total)}
                    sub={`${num(summary.void_count)} sales`}
                    tone={summary.void_count > 0 ? 'text-danger' : ''}
                />
            </div>

            <div className="row g-3">
                {/* Payment breakdown */}
                <div className="col-lg-4">
                    <div className="card shadow-sm h-100">
                        <div className="card-header fw-semibold">By payment method</div>
                        <div className="table-responsive">
                            <table className="table mb-0 align-middle">
                                <tbody>
                                    {Object.entries(summary.methods).map(([m, v]) => (
                                        <tr key={m}>
                                            <td>
                                                {METHOD_LABEL[m]}
                                                <span className="text-body-secondary small ms-2">
                                                    {num(v.count)}
                                                </span>
                                            </td>
                                            <td className="text-end fw-semibold">
                                                {money(v.total)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="table-light">
                                    <tr>
                                        <td className="fw-semibold">Tax collected</td>
                                        <td className="text-end">{money(summary.tax_total)}</td>
                                    </tr>
                                    <tr>
                                        <td className="fw-semibold">Discounts given</td>
                                        <td className="text-end">
                                            {money(summary.discount_total)}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="fw-semibold">Cost of goods</td>
                                        <td className="text-end">{money(summary.cost)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                {/* Best sellers */}
                <div className="col-lg-8">
                    <div className="card shadow-sm h-100">
                        <div className="card-header d-flex justify-content-between align-items-center">
                            <span className="fw-semibold">Best sellers</span>
                            <div className="btn-group btn-group-sm" role="group">
                                {[
                                    ['revenue', 'Revenue'],
                                    ['units', 'Units'],
                                    ['profit', 'Profit'],
                                ].map(([k, label]) => (
                                    <button
                                        key={k}
                                        type="button"
                                        className={`btn ${
                                            sortBy === k
                                                ? 'btn-secondary'
                                                : 'btn-outline-secondary'
                                        }`}
                                        onClick={() => setSortBy(k)}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div className="table-responsive">
                            <table className="table table-hover mb-0 align-middle">
                                <thead className="table-light">
                                    <tr>
                                        <th>Product / Variant</th>
                                        <th className="text-end">Units (base)</th>
                                        <th className="text-end">Revenue</th>
                                        <th className="text-end">Profit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sortedSellers.length === 0 && (
                                        <tr>
                                            <td colSpan={4} className="text-center text-body-secondary py-4">
                                                No sales in this period.
                                            </td>
                                        </tr>
                                    )}
                                    {sortedSellers.map((s) => (
                                        <tr key={s.variant_id}>
                                            <td>
                                                <span className="fw-semibold">
                                                    {s.product_name}
                                                </span>
                                                <span className="text-body-secondary">
                                                    {' '}
                                                    — {s.variant_label}
                                                </span>
                                            </td>
                                            <td className="text-end">{num(s.base_units)}</td>
                                            <td className="text-end">{money(s.revenue)}</td>
                                            <td className="text-end">
                                                <span
                                                    className={
                                                        s.profit < 0 ? 'text-danger' : ''
                                                    }
                                                >
                                                    {money(s.profit)}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {/* Void / refund log */}
            <div className="card shadow-sm mt-3">
                <div className="card-header fw-semibold">Void / refund log</div>
                <div className="table-responsive">
                    <table className="table table-hover mb-0 align-middle">
                        <thead className="table-light">
                            <tr>
                                <th>Sale</th>
                                <th>When</th>
                                <th>Cashier</th>
                                <th>Voided by</th>
                                <th>Reason</th>
                                <th className="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {voids.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-center text-body-secondary py-4">
                                        No voids or refunds in this period.
                                    </td>
                                </tr>
                            )}
                            {voids.map((v) => (
                                <tr key={v.id}>
                                    <td>
                                        <Link
                                            href={`/sales/${v.id}`}
                                            className="text-decoration-none fw-semibold"
                                        >
                                            #{v.id}
                                        </Link>
                                    </td>
                                    <td>{dt(v.created_at)}</td>
                                    <td>{v.cashier || '—'}</td>
                                    <td>{v.voided_by || '—'}</td>
                                    <td>
                                        <span className="text-body-secondary">
                                            {v.reason || '—'}
                                        </span>
                                    </td>
                                    <td className="text-end">{money(v.grand_total)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
