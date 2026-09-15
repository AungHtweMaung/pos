import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

// Money formatter — cashier-facing, 2dp, thousands separator.
import { money } from '../../money';

const PAYMENT_METHODS = [
    { key: 'cash', label: 'Cash', icon: 'bi-cash-coin' },
    { key: 'qr', label: 'QR transfer', icon: 'bi-qr-code' },
];

export default function Cart() {
    const { errors: pageErrors } = usePage().props;

    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [searching, setSearching] = useState(false);
    const [lookupError, setLookupError] = useState(null);
    const [lines, setLines] = useState([]);
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [cashTendered, setCashTendered] = useState('');
    const [qrReference, setQrReference] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const searchRef = useRef(null);

    // Focus the search on mount.
    useEffect(() => {
        searchRef.current?.focus();
    }, []);

    // Live search — fetch matches ~250ms after the cashier stops typing so
    // results appear without pressing Enter. Barcode scanners still work
    // because they type-then-Enter (handled by onSubmitSearch).
    useEffect(() => {
        const q = query.trim();
        if (q === '') {
            setResults([]);
            setLookupError(null);
            return;
        }
        const timer = setTimeout(() => {
            doLookup(q);
        }, 250);
        return () => clearTimeout(timer);
    }, [query]);

    // Derived totals — recomputed on every render to match server-side.
    const totals = useMemo(() => {
        let subtotal = 0;
        let discountTotal = 0;
        let taxTotal = 0;

        for (const line of lines) {
            const gross = Number(line.price) * Number(line.quantity);
            const discount = Math.min(Number(line.discount) || 0, gross);
            const lineNet = gross - discount;
            const lineTax = (lineNet * Number(line.tax_rate)) / 100;
            subtotal += gross;
            discountTotal += discount;
            taxTotal += lineTax;
        }

        const grand = subtotal - discountTotal + taxTotal;
        const tendered = Number(cashTendered) || 0;
        const change = paymentMethod === 'cash' ? Math.max(tendered - grand, 0) : 0;

        return {
            subtotal,
            discountTotal,
            taxTotal,
            grand,
            change,
            shortByCash: paymentMethod === 'cash' && tendered < grand,
        };
    }, [lines, cashTendered, paymentMethod]);

    const doLookup = async (q) => {
        setLookupError(null);
        if (!q || q.trim() === '') {
            setResults([]);
            return { matched_barcode: false, results: [] };
        }
        setSearching(true);
        try {
            const res = await fetch(`/pos/lookup?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error(`Lookup failed (${res.status})`);
            const json = await res.json();
            setResults(json.results || []);
            return json;
        } catch (err) {
            setLookupError(err.message);
            return { matched_barcode: false, results: [] };
        } finally {
            setSearching(false);
        }
    };

    const addToCart = (item) => {
        setLines((prev) => {
            const existing = prev.find((l) => l.sale_unit_id === item.sale_unit_id);
            if (existing) {
                return prev.map((l) =>
                    l.sale_unit_id === item.sale_unit_id
                        ? { ...l, quantity: l.quantity + 1 }
                        : l,
                );
            }
            return [
                ...prev,
                {
                    sale_unit_id: item.sale_unit_id,
                    product_name: item.product_name,
                    variant_label: item.variant_label,
                    sale_unit_label: item.sale_unit_label,
                    pack_size: item.pack_size,
                    price: Number(item.price),
                    tax_rate: Number(item.tax_rate),
                    stock_qty: item.stock_qty,
                    quantity: 1,
                    discount: 0,
                },
            ];
        });
    };

    const onSubmitSearch = async (e) => {
        e.preventDefault();
        const q = query.trim();
        if (!q) return;
        const json = await doLookup(q);
        // Barcode scanners send "value" + Enter; if we found an exact barcode
        // match, auto-add and clear so the next scan flows straight in.
        if (json.matched_barcode && json.results.length === 1) {
            addToCart(json.results[0]);
            setQuery('');
            setResults([]);
        }
    };

    const changeQty = (id, delta) => {
        setLines((prev) =>
            prev
                .map((l) =>
                    l.sale_unit_id === id
                        ? { ...l, quantity: Math.max(1, l.quantity + delta) }
                        : l,
                ),
        );
    };

    const setQty = (id, value) => {
        const q = Math.max(1, parseInt(value, 10) || 1);
        setLines((prev) =>
            prev.map((l) => (l.sale_unit_id === id ? { ...l, quantity: q } : l)),
        );
    };

    const setLineDiscount = (id, value) => {
        const d = Math.max(0, Number(value) || 0);
        setLines((prev) =>
            prev.map((l) => (l.sale_unit_id === id ? { ...l, discount: d } : l)),
        );
    };

    const removeLine = (id) => {
        setLines((prev) => prev.filter((l) => l.sale_unit_id !== id));
    };

    const clearCart = () => {
        if (!lines.length) return;
        if (!confirm('Clear the cart?')) return;
        setLines([]);
        setCashTendered('');
        setQrReference('');
    };

    const submit = () => {
        if (!lines.length) return;
        setSubmitting(true);
        router.post(
            '/pos',
            {
                items: lines.map((l) => ({
                    sale_unit_id: l.sale_unit_id,
                    quantity: l.quantity,
                    discount: Number(l.discount) || 0,
                })),
                payment_method: paymentMethod,
                cash_tendered: paymentMethod === 'cash' ? Number(cashTendered) || 0 : null,
                qr_reference_note: paymentMethod === 'qr' ? qrReference : null,
            },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const canConfirm =
        lines.length > 0 &&
        !submitting &&
        (paymentMethod !== 'cash' || !totals.shortByCash) &&
        (paymentMethod !== 'qr' || qrReference.trim() !== '');

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 className="h4 mb-0">Sales / POS</h1>
                        <p className="text-body-secondary small mb-0">
                            Type a product name to see matches, or scan a barcode.
                        </p>
                    </div>
                    <button
                        type="button"
                        className="btn btn-outline-secondary"
                        onClick={clearCart}
                        disabled={!lines.length}
                    >
                        <i className="bi bi-trash me-1"></i>Clear cart
                    </button>
                </div>
            }
        >
            <Head title="Sales / POS" />

            <div className="row g-3">
                {/* Left: search + cart */}
                <div className="col-lg-8">
                    <form onSubmit={onSubmitSearch} className="mb-3">
                        <div className="input-group input-group-lg">
                            <span className="input-group-text">
                                <i className="bi bi-upc-scan"></i>
                            </span>
                            <input
                                ref={searchRef}
                                type="search"
                                className="form-control"
                                placeholder="Scan barcode or type product name…"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                            />
                            <button
                                type="submit"
                                className="btn btn-primary"
                                disabled={searching}
                            >
                                {searching ? '…' : 'Add'}
                            </button>
                        </div>
                        <div className="form-text">
                            Matches appear as you type. Press <kbd>Enter</kbd> after a
                            barcode scan to auto-add.
                        </div>
                    </form>

                    {lookupError && (
                        <div className="alert alert-danger py-2">{lookupError}</div>
                    )}

                    {results.length > 0 && (
                        <div className="card shadow-sm mb-3">
                            <div className="card-header small text-body-secondary">
                                Matches — click to add
                            </div>
                            <ul className="list-group list-group-flush">
                                {results.map((r) => (
                                    <li
                                        key={r.sale_unit_id}
                                        className="list-group-item d-flex justify-content-between align-items-center"
                                    >
                                        <div>
                                            <div className="fw-semibold">
                                                {r.product_name}{' '}
                                                <span className="text-body-secondary">— {r.variant_label}</span>
                                            </div>
                                            <div className="small text-body-secondary">
                                                {r.sale_unit_label} · pack {r.pack_size} ·{' '}
                                                <code>{r.barcode}</code> · stock {r.stock_qty}
                                            </div>
                                        </div>
                                        <div className="d-flex align-items-center gap-2">
                                            <span className="fw-semibold">
                                                {money(r.price)}
                                            </span>
                                            <button
                                                type="button"
                                                className="btn btn-sm btn-outline-primary"
                                                onClick={() => {
                                                    addToCart(r);
                                                    setResults([]);
                                                    setQuery('');
                                                    searchRef.current?.focus();
                                                }}
                                            >
                                                <i className="bi bi-plus-lg"></i>
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div className="card shadow-sm">
                        <div className="card-header d-flex justify-content-between align-items-center">
                            <span className="fw-semibold">Cart</span>
                            <span className="text-body-secondary small">
                                {lines.length} line{lines.length === 1 ? '' : 's'}
                            </span>
                        </div>
                        <div className="table-responsive">
                            <table className="table mb-0 align-middle">
                                <thead className="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th className="text-end">Price</th>
                                        <th style={{ width: '10rem' }}>Qty</th>
                                        <th style={{ width: '9rem' }} className="text-end">
                                            Discount
                                        </th>
                                        <th className="text-end">Line</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {lines.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="text-center text-body-secondary py-4"
                                            >
                                                Cart is empty. Scan or search to add items.
                                            </td>
                                        </tr>
                                    )}
                                    {lines.map((l) => {
                                        const gross = l.price * l.quantity;
                                        const disc = Math.min(Number(l.discount) || 0, gross);
                                        const lineTotal = gross - disc;
                                        return (
                                            <tr key={l.sale_unit_id}>
                                                <td>
                                                    <div className="fw-semibold">
                                                        {l.product_name}
                                                    </div>
                                                    <div className="small text-body-secondary">
                                                        {l.variant_label} · {l.sale_unit_label}{' '}
                                                        (pack {l.pack_size})
                                                    </div>
                                                </td>
                                                <td className="text-end">{money(l.price)}</td>
                                                <td>
                                                    <div className="input-group input-group-sm">
                                                        <button
                                                            type="button"
                                                            className="btn btn-outline-secondary"
                                                            onClick={() =>
                                                                changeQty(l.sale_unit_id, -1)
                                                            }
                                                        >
                                                            −
                                                        </button>
                                                        <input
                                                            type="number"
                                                            min={1}
                                                            className="form-control text-center"
                                                            value={l.quantity}
                                                            onChange={(e) =>
                                                                setQty(
                                                                    l.sale_unit_id,
                                                                    e.target.value,
                                                                )
                                                            }
                                                        />
                                                        <button
                                                            type="button"
                                                            className="btn btn-outline-secondary"
                                                            onClick={() =>
                                                                changeQty(l.sale_unit_id, 1)
                                                            }
                                                        >
                                                            +
                                                        </button>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input
                                                        type="number"
                                                        min={0}
                                                        step="0.01"
                                                        className="form-control form-control-sm text-end"
                                                        value={l.discount}
                                                        onChange={(e) =>
                                                            setLineDiscount(
                                                                l.sale_unit_id,
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </td>
                                                <td className="text-end fw-semibold">
                                                    {money(lineTotal)}
                                                </td>
                                                <td className="text-end">
                                                    <button
                                                        type="button"
                                                        className="btn btn-sm btn-outline-danger"
                                                        onClick={() =>
                                                            removeLine(l.sale_unit_id)
                                                        }
                                                    >
                                                        <i className="bi bi-x"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {/* Right: totals + payment */}
                <div className="col-lg-4">
                    <div className="card shadow-sm sticky-lg-top" style={{ top: '1rem' }}>
                        <div className="card-body">
                            <h2 className="h5 mb-3">Totals</h2>
                            <dl className="row mb-3">
                                <dt className="col-6 text-body-secondary fw-normal">Subtotal</dt>
                                <dd className="col-6 text-end">{money(totals.subtotal)}</dd>

                                <dt className="col-6 text-body-secondary fw-normal">
                                    Discount
                                </dt>
                                <dd className="col-6 text-end">
                                    −{money(totals.discountTotal)}
                                </dd>

                                <dt className="col-6 text-body-secondary fw-normal">Tax</dt>
                                <dd className="col-6 text-end">{money(totals.taxTotal)}</dd>

                                <dt className="col-6 fw-semibold border-top pt-2">
                                    Grand total
                                </dt>
                                <dd className="col-6 text-end fw-semibold border-top pt-2 fs-5">
                                    {money(totals.grand)}
                                </dd>
                            </dl>

                            <div className="mb-3">
                                <div className="form-label">Payment method</div>
                                <div className="btn-group w-100" role="group">
                                    {PAYMENT_METHODS.map((m) => (
                                        <button
                                            key={m.key}
                                            type="button"
                                            className={`btn ${
                                                paymentMethod === m.key
                                                    ? 'btn-primary'
                                                    : 'btn-outline-primary'
                                            }`}
                                            onClick={() => setPaymentMethod(m.key)}
                                        >
                                            <i className={`bi ${m.icon} me-1`}></i>
                                            {m.label}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {paymentMethod === 'cash' && (
                                <div className="mb-3">
                                    <label htmlFor="cashTendered" className="form-label">
                                        Cash tendered
                                    </label>
                                    <input
                                        id="cashTendered"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        className={`form-control ${
                                            pageErrors?.cash_tendered ? 'is-invalid' : ''
                                        }`}
                                        value={cashTendered}
                                        onChange={(e) => setCashTendered(e.target.value)}
                                    />
                                    {pageErrors?.cash_tendered && (
                                        <div className="invalid-feedback">
                                            {pageErrors.cash_tendered}
                                        </div>
                                    )}
                                    <div className="d-flex justify-content-between mt-2 small">
                                        <span className="text-body-secondary">Change</span>
                                        <span
                                            className={
                                                totals.shortByCash
                                                    ? 'text-danger fw-semibold'
                                                    : 'fw-semibold'
                                            }
                                        >
                                            {totals.shortByCash
                                                ? 'Short'
                                                : money(totals.change)}
                                        </span>
                                    </div>
                                </div>
                            )}

                            {paymentMethod === 'qr' && (
                                <div className="mb-3">
                                    <label htmlFor="qrRef" className="form-label">
                                        Reference note{' '}
                                        <span className="text-body-secondary small">
                                            (read out to buyer)
                                        </span>
                                    </label>
                                    <input
                                        id="qrRef"
                                        type="text"
                                        className={`form-control ${
                                            pageErrors?.qr_reference_note ? 'is-invalid' : ''
                                        }`}
                                        value={qrReference}
                                        onChange={(e) => setQrReference(e.target.value)}
                                        placeholder="e.g. POS-A1B2"
                                    />
                                    {pageErrors?.qr_reference_note && (
                                        <div className="invalid-feedback">
                                            {pageErrors.qr_reference_note}
                                        </div>
                                    )}
                                    <div className="form-text">
                                        The buyer transfers the grand total and includes this
                                        note; confirm once you see the transfer.
                                    </div>
                                </div>
                            )}

                            {pageErrors?.items && (
                                <div className="alert alert-danger py-2 small">
                                    {pageErrors.items}
                                </div>
                            )}

                            <button
                                type="button"
                                className="btn btn-success w-100 btn-lg"
                                disabled={!canConfirm}
                                onClick={submit}
                            >
                                <i className="bi bi-check-circle me-1"></i>
                                {submitting ? 'Confirming…' : `Confirm ${money(totals.grand)}`}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
