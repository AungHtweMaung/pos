import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

import { money } from '../../money';

const STATUS_BADGE = {
    completed: 'text-bg-success',
    voided: 'text-bg-secondary',
    refunded: 'text-bg-warning',
};

export default function SaleShow() {
    const { sale, canVoid } = usePage().props;

    const voidForm = useForm({ reason: '' });

    const submitVoid = (e) => {
        e.preventDefault();
        if (!confirm(`Void sale #${sale.id}? Stock will be restored.`)) return;
        voidForm.post(`/sales/${sale.id}/void`, {
            preserveScroll: true,
            onSuccess: () => voidForm.reset('reason'),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 className="h4 mb-0">Sale #{sale.id}</h1>
                        <p className="text-body-secondary small mb-0">
                            {new Date(sale.created_at).toLocaleString()} — rung up by{' '}
                            {sale.cashier?.name}
                        </p>
                    </div>
                    <div className="d-flex gap-2">
                        <Link
                            href={`/sales/${sale.id}/receipt`}
                            className="btn btn-outline-primary"
                        >
                            <i className="bi bi-receipt me-1"></i>Receipt
                        </Link>
                        <Link href="/sales" className="btn btn-outline-secondary">
                            <i className="bi bi-arrow-left me-1"></i>Back
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`Sale #${sale.id}`} />

            <div className="row g-3">
                <div className="col-lg-8">
                    <div className="card shadow-sm mb-3">
                        <div className="card-header d-flex justify-content-between align-items-center">
                            <span className="fw-semibold">Items</span>
                            <span
                                className={`badge ${
                                    STATUS_BADGE[sale.status] || 'text-bg-light'
                                }`}
                            >
                                {sale.status}
                            </span>
                        </div>
                        <div className="table-responsive">
                            <table className="table mb-0 align-middle">
                                <thead className="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th className="text-end">Unit price</th>
                                        <th className="text-end">Qty</th>
                                        <th className="text-end">Discount</th>
                                        <th className="text-end">Line total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sale.items.map((i) => (
                                        <tr key={i.id}>
                                            <td>
                                                <div className="fw-semibold">
                                                    {i.sale_unit.variant.product.name}
                                                </div>
                                                <div className="small text-body-secondary">
                                                    {i.sale_unit.variant.label} ·{' '}
                                                    {i.sale_unit.label} (pack{' '}
                                                    {i.sale_unit.pack_size})
                                                </div>
                                            </td>
                                            <td className="text-end">{money(i.unit_price)}</td>
                                            <td className="text-end">{i.quantity}</td>
                                            <td className="text-end">{money(i.discount)}</td>
                                            <td className="text-end fw-semibold">
                                                {money(i.line_total)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {sale.status !== 'completed' && (
                        <div className="alert alert-warning">
                            <div className="fw-semibold">
                                <i className="bi bi-exclamation-triangle me-1"></i>
                                This sale is {sale.status}.
                            </div>
                            {sale.voider && (
                                <div className="small mt-1">
                                    By {sale.voider.name} —{' '}
                                    <em>{sale.voided_reason || 'no reason given'}</em>
                                </div>
                            )}
                        </div>
                    )}

                    {canVoid && (
                        <form onSubmit={submitVoid} className="card shadow-sm">
                            <div className="card-body">
                                <h2 className="h6">Void / refund</h2>
                                <p className="small text-body-secondary">
                                    Restores stock and marks the sale as voided. This can't be
                                    undone.
                                </p>
                                <div className="mb-2">
                                    <label htmlFor="reason" className="form-label">
                                        Reason
                                    </label>
                                    <input
                                        id="reason"
                                        type="text"
                                        className={`form-control ${
                                            voidForm.errors.reason ? 'is-invalid' : ''
                                        }`}
                                        value={voidForm.data.reason}
                                        onChange={(e) =>
                                            voidForm.setData('reason', e.target.value)
                                        }
                                    />
                                    {voidForm.errors.reason && (
                                        <div className="invalid-feedback">
                                            {voidForm.errors.reason}
                                        </div>
                                    )}
                                </div>
                                <button
                                    type="submit"
                                    className="btn btn-danger"
                                    disabled={voidForm.processing}
                                >
                                    <i className="bi bi-x-octagon me-1"></i>
                                    Void sale
                                </button>
                            </div>
                        </form>
                    )}
                </div>

                <div className="col-lg-4">
                    <div className="card shadow-sm">
                        <div className="card-body">
                            <h2 className="h6 mb-3">Summary</h2>
                            <dl className="row mb-0">
                                <dt className="col-6 text-body-secondary fw-normal">Subtotal</dt>
                                <dd className="col-6 text-end">{money(sale.subtotal)}</dd>

                                <dt className="col-6 text-body-secondary fw-normal">
                                    Discount
                                </dt>
                                <dd className="col-6 text-end">−{money(sale.discount_total)}</dd>

                                <dt className="col-6 text-body-secondary fw-normal">Tax</dt>
                                <dd className="col-6 text-end">{money(sale.tax_total)}</dd>

                                <dt className="col-6 fw-semibold border-top pt-2">
                                    Grand total
                                </dt>
                                <dd className="col-6 text-end fw-semibold border-top pt-2">
                                    {money(sale.grand_total)}
                                </dd>

                                <dt className="col-6 text-body-secondary fw-normal mt-3">
                                    Payment
                                </dt>
                                <dd className="col-6 text-end mt-3">
                                    <span className="badge text-bg-light">
                                        {sale.payment_method}
                                    </span>
                                </dd>

                                {sale.payment_method === 'cash' && (
                                    <>
                                        <dt className="col-6 text-body-secondary fw-normal">
                                            Cash tendered
                                        </dt>
                                        <dd className="col-6 text-end">
                                            {money(sale.cash_tendered)}
                                        </dd>
                                        <dt className="col-6 text-body-secondary fw-normal">
                                            Change
                                        </dt>
                                        <dd className="col-6 text-end">
                                            {money(sale.change_due)}
                                        </dd>
                                    </>
                                )}

                                {sale.payment_method === 'qr' && sale.qr_reference_note && (
                                    <>
                                        <dt className="col-6 text-body-secondary fw-normal">
                                            QR ref
                                        </dt>
                                        <dd className="col-6 text-end">
                                            <code>{sale.qr_reference_note}</code>
                                        </dd>
                                    </>
                                )}
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
