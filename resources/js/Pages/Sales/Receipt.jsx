import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

const money = (n) =>
    Number(n || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

// Print-friendly receipt (spec §8.1 step 6). Standalone layout — no chrome,
// no nav — so Ctrl+P prints just the slip. Screen shows action buttons that
// hide on print.
export default function Receipt() {
    const { sale, app } = usePage().props;

    useEffect(() => {
        document.body.classList.add('bg-body');
        return () => document.body.classList.remove('bg-body');
    }, []);

    const total = money(sale.grand_total);

    return (
        <>
            <Head title={`Receipt #${sale.id}`} />

            <style>{`
                @media print {
                    .no-print { display: none !important; }
                    .receipt { box-shadow: none !important; border: 0 !important; }
                }
                .receipt {
                    max-width: 24rem;
                    margin: 2rem auto;
                }
                .receipt .line {
                    border-top: 1px dashed var(--bs-border-color);
                    margin: 0.5rem 0;
                }
            `}</style>

            <div className="container">
                <div className="d-flex justify-content-end gap-2 my-3 no-print">
                    <button
                        type="button"
                        className="btn btn-outline-primary btn-sm"
                        onClick={() => window.print()}
                    >
                        <i className="bi bi-printer me-1"></i>Print
                    </button>
                    <Link href="/pos" className="btn btn-primary btn-sm">
                        <i className="bi bi-cart me-1"></i>New sale
                    </Link>
                    <Link href={`/sales/${sale.id}`} className="btn btn-outline-secondary btn-sm">
                        <i className="bi bi-eye me-1"></i>Sale detail
                    </Link>
                </div>

                <div className="card receipt shadow-sm">
                    <div className="card-body">
                        <div className="text-center mb-2">
                            <div className="h5 mb-0">{app?.name || 'Grocery POS'}</div>
                            <div className="small text-body-secondary">
                                {new Date(sale.created_at).toLocaleString()}
                            </div>
                        </div>

                        <div className="line"></div>

                        <div className="small mb-1">
                            <div>
                                <strong>Sale #:</strong> {sale.id}
                            </div>
                            <div>
                                <strong>Cashier:</strong> {sale.cashier?.name}
                            </div>
                        </div>

                        <div className="line"></div>

                        <table className="table table-sm mb-0 small">
                            <tbody>
                                {sale.items.map((i) => (
                                    <tr key={i.id}>
                                        <td className="p-1">
                                            <div>{i.sale_unit.variant.product.name}</div>
                                            <div className="text-body-secondary">
                                                {i.sale_unit.label} × {i.quantity} @{' '}
                                                {money(i.unit_price)}
                                                {Number(i.discount) > 0 && (
                                                    <> − {money(i.discount)}</>
                                                )}
                                            </div>
                                        </td>
                                        <td className="p-1 text-end align-top">
                                            {money(i.line_total)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        <div className="line"></div>

                        <dl className="row mb-0 small">
                            <dt className="col-7 fw-normal">Subtotal</dt>
                            <dd className="col-5 text-end mb-1">{money(sale.subtotal)}</dd>

                            <dt className="col-7 fw-normal">Discount</dt>
                            <dd className="col-5 text-end mb-1">
                                −{money(sale.discount_total)}
                            </dd>

                            <dt className="col-7 fw-normal">Tax</dt>
                            <dd className="col-5 text-end mb-1">{money(sale.tax_total)}</dd>

                            <dt className="col-7 fw-bold border-top pt-1">Total</dt>
                            <dd className="col-5 text-end fw-bold border-top pt-1">{total}</dd>
                        </dl>

                        <div className="line"></div>

                        <div className="small">
                            <div>
                                <strong>Payment:</strong> {sale.payment_method}
                            </div>
                            {sale.payment_method === 'cash' && (
                                <>
                                    <div>Cash: {money(sale.cash_tendered)}</div>
                                    <div>Change: {money(sale.change_due)}</div>
                                </>
                            )}
                            {sale.payment_method === 'qr' && sale.qr_reference_note && (
                                <div>
                                    QR ref: <code>{sale.qr_reference_note}</code>
                                </div>
                            )}
                        </div>

                        <div className="line"></div>

                        <div className="text-center small text-body-secondary">
                            Thank you!
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
