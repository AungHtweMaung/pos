import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

// ----- Product core form ---------------------------------------------------

function ProductForm({ product }) {
    const { data, setData, put, processing, errors } = useForm({
        name: product.name,
        category: product.category ?? '',
        tax_rate: String(product.tax_rate),
    });

    const submit = (e) => {
        e.preventDefault();
        put(`/inventory/products/${product.id}`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="card shadow-sm mb-4">
            <div className="card-body">
                <h2 className="h5 card-title">Product details</h2>
                <div className="row g-3">
                    <div className="col-md-6">
                        <label className="form-label">Name</label>
                        <input
                            type="text"
                            className={`form-control ${errors.name ? 'is-invalid' : ''}`}
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        {errors.name && <div className="invalid-feedback">{errors.name}</div>}
                    </div>
                    <div className="col-md-4">
                        <label className="form-label">Category</label>
                        <input
                            type="text"
                            className={`form-control ${errors.category ? 'is-invalid' : ''}`}
                            value={data.category}
                            onChange={(e) => setData('category', e.target.value)}
                        />
                        {errors.category && <div className="invalid-feedback">{errors.category}</div>}
                    </div>
                    <div className="col-md-2">
                        <label className="form-label">Tax %</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            className={`form-control ${errors.tax_rate ? 'is-invalid' : ''}`}
                            value={data.tax_rate}
                            onChange={(e) => setData('tax_rate', e.target.value)}
                        />
                        {errors.tax_rate && <div className="invalid-feedback">{errors.tax_rate}</div>}
                    </div>
                </div>
                <div className="mt-3">
                    <button type="submit" className="btn btn-primary" disabled={processing}>
                        {processing ? 'Saving…' : 'Save changes'}
                    </button>
                </div>
            </div>
        </form>
    );
}

// ----- New-variant form ----------------------------------------------------

function NewVariantForm({ productId }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        label: '',
        stock_qty: '0',
        low_stock_threshold: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(`/inventory/products/${productId}/variants`, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form onSubmit={submit} className="row g-2 align-items-end">
            <div className="col-md-4">
                <label className="form-label small">Label (e.g. 250ml)</label>
                <input
                    type="text"
                    className={`form-control form-control-sm ${errors.label ? 'is-invalid' : ''}`}
                    value={data.label}
                    onChange={(e) => setData('label', e.target.value)}
                />
                {errors.label && <div className="invalid-feedback">{errors.label}</div>}
            </div>
            <div className="col-md-3">
                <label className="form-label small">Opening stock</label>
                <input
                    type="number"
                    min="0"
                    className={`form-control form-control-sm ${errors.stock_qty ? 'is-invalid' : ''}`}
                    value={data.stock_qty}
                    onChange={(e) => setData('stock_qty', e.target.value)}
                />
                {errors.stock_qty && <div className="invalid-feedback">{errors.stock_qty}</div>}
            </div>
            <div className="col-md-3">
                <label className="form-label small">Low-stock threshold</label>
                <input
                    type="number"
                    min="0"
                    className={`form-control form-control-sm ${errors.low_stock_threshold ? 'is-invalid' : ''}`}
                    value={data.low_stock_threshold}
                    onChange={(e) => setData('low_stock_threshold', e.target.value)}
                    placeholder="none"
                />
                {errors.low_stock_threshold && <div className="invalid-feedback">{errors.low_stock_threshold}</div>}
            </div>
            <div className="col-md-2">
                <button type="submit" className="btn btn-sm btn-primary w-100" disabled={processing}>
                    <i className="bi bi-plus-lg me-1"></i>Add variant
                </button>
            </div>
        </form>
    );
}

// ----- Variant edit + management ------------------------------------------

function VariantRow({ productId, variant }) {
    const [open, setOpen] = useState(false);
    const [showAdjust, setShowAdjust] = useState(false);

    const edit = useForm({
        label: variant.label,
        low_stock_threshold: variant.low_stock_threshold ?? '',
    });

    const saveVariant = (e) => {
        e.preventDefault();
        edit.put(`/inventory/products/${productId}/variants/${variant.id}`, {
            preserveScroll: true,
        });
    };

    const deleteVariant = () => {
        if (!confirm(`Delete variant “${variant.label}”? Its sale units will also be removed.`)) return;
        router.delete(`/inventory/products/${productId}/variants/${variant.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <div className="card mb-3">
            <div className="card-header d-flex justify-content-between align-items-center bg-body-tertiary">
                <button
                    type="button"
                    className="btn btn-link p-0 text-decoration-none text-body fw-semibold"
                    onClick={() => setOpen((o) => !o)}
                >
                    <i className={`bi ${open ? 'bi-chevron-down' : 'bi-chevron-right'} me-2`}></i>
                    {variant.label}
                    <span className="badge text-bg-secondary ms-2">stock {variant.stock_qty}</span>
                    {variant.is_low_stock && (
                        <span className="badge text-bg-warning ms-1">
                            <i className="bi bi-exclamation-triangle me-1"></i>low
                        </span>
                    )}
                    <span className="badge text-bg-light ms-1">{variant.sale_units.length} sale unit{variant.sale_units.length === 1 ? '' : 's'}</span>
                </button>
                <div>
                    <button
                        type="button"
                        className="btn btn-sm btn-outline-primary me-1"
                        onClick={() => setShowAdjust((s) => !s)}
                    >
                        <i className="bi bi-arrow-left-right me-1"></i>Adjust stock
                    </button>
                    <button
                        type="button"
                        className="btn btn-sm btn-outline-danger"
                        onClick={deleteVariant}
                    >
                        <i className="bi bi-trash"></i>
                    </button>
                </div>
            </div>

            {showAdjust && (
                <StockAdjustForm
                    productId={productId}
                    variantId={variant.id}
                    onDone={() => setShowAdjust(false)}
                />
            )}

            {open && (
                <div className="card-body">
                    <form onSubmit={saveVariant} className="row g-2 align-items-end mb-3">
                        <div className="col-md-5">
                            <label className="form-label small">Label</label>
                            <input
                                type="text"
                                className={`form-control form-control-sm ${edit.errors.label ? 'is-invalid' : ''}`}
                                value={edit.data.label}
                                onChange={(e) => edit.setData('label', e.target.value)}
                            />
                            {edit.errors.label && <div className="invalid-feedback">{edit.errors.label}</div>}
                        </div>
                        <div className="col-md-4">
                            <label className="form-label small">Low-stock threshold</label>
                            <input
                                type="number"
                                min="0"
                                className={`form-control form-control-sm ${edit.errors.low_stock_threshold ? 'is-invalid' : ''}`}
                                value={edit.data.low_stock_threshold ?? ''}
                                onChange={(e) => edit.setData('low_stock_threshold', e.target.value)}
                                placeholder="none"
                            />
                            {edit.errors.low_stock_threshold && (
                                <div className="invalid-feedback">{edit.errors.low_stock_threshold}</div>
                            )}
                        </div>
                        <div className="col-md-3">
                            <button className="btn btn-sm btn-outline-primary w-100" disabled={edit.processing}>
                                Save variant
                            </button>
                        </div>
                    </form>

                    <h3 className="h6 mt-3">Sale units</h3>
                    <div className="table-responsive">
                        <table className="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Label</th>
                                    <th>Barcode</th>
                                    <th className="text-end">Pack size</th>
                                    <th className="text-end">Price</th>
                                    <th className="text-end">Cost</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {variant.sale_units.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-body-secondary small">
                                            No sale units yet — add one below.
                                        </td>
                                    </tr>
                                )}
                                {variant.sale_units.map((u) => (
                                    <SaleUnitRow key={u.id} productId={productId} variantId={variant.id} unit={u} />
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <NewSaleUnitForm productId={productId} variantId={variant.id} />

                    {variant.recent_adjustments?.length > 0 && (
                        <>
                            <h3 className="h6 mt-4">Recent stock adjustments</h3>
                            <ul className="list-group list-group-flush small">
                                {variant.recent_adjustments.map((a) => (
                                    <li key={a.id} className="list-group-item px-0 d-flex justify-content-between">
                                        <span>
                                            <span
                                                className={`badge me-2 ${
                                                    a.change_qty > 0 ? 'text-bg-success' : 'text-bg-danger'
                                                }`}
                                            >
                                                {a.change_qty > 0 ? '+' : ''}
                                                {a.change_qty}
                                            </span>
                                            {a.reason}
                                        </span>
                                        <span className="text-body-secondary">
                                            {a.by} · {a.at}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </div>
            )}
        </div>
    );
}

// ----- Sale unit row + inline edit ----------------------------------------

function SaleUnitRow({ productId, variantId, unit }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        label: unit.label,
        barcode: unit.barcode,
        pack_size: String(unit.pack_size),
        price: String(unit.price),
        cost: String(unit.cost),
    });

    const save = (e) => {
        e.preventDefault();
        form.put(
            `/inventory/products/${productId}/variants/${variantId}/sale-units/${unit.id}`,
            {
                preserveScroll: true,
                onSuccess: () => setEditing(false),
            }
        );
    };

    const destroy = () => {
        if (!confirm(`Delete sale unit “${unit.label}”?`)) return;
        router.delete(
            `/inventory/products/${productId}/variants/${variantId}/sale-units/${unit.id}`,
            { preserveScroll: true }
        );
    };

    if (!editing) {
        return (
            <tr>
                <td>{unit.label}</td>
                <td><code>{unit.barcode}</code></td>
                <td className="text-end">{unit.pack_size}</td>
                <td className="text-end">{unit.price.toFixed(2)}</td>
                <td className="text-end">{unit.cost.toFixed(2)}</td>
                <td className="text-end">
                    <button
                        type="button"
                        className="btn btn-sm btn-outline-secondary me-1"
                        onClick={() => setEditing(true)}
                    >
                        <i className="bi bi-pencil"></i>
                    </button>
                    <button
                        type="button"
                        className="btn btn-sm btn-outline-danger"
                        onClick={destroy}
                    >
                        <i className="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        );
    }

    return (
        <tr>
            <td>
                <input
                    className={`form-control form-control-sm ${form.errors.label ? 'is-invalid' : ''}`}
                    value={form.data.label}
                    onChange={(e) => form.setData('label', e.target.value)}
                />
            </td>
            <td>
                <input
                    className={`form-control form-control-sm ${form.errors.barcode ? 'is-invalid' : ''}`}
                    value={form.data.barcode}
                    onChange={(e) => form.setData('barcode', e.target.value)}
                />
                {form.errors.barcode && <div className="invalid-feedback d-block">{form.errors.barcode}</div>}
            </td>
            <td>
                <input
                    type="number" min="1"
                    className={`form-control form-control-sm text-end ${form.errors.pack_size ? 'is-invalid' : ''}`}
                    value={form.data.pack_size}
                    onChange={(e) => form.setData('pack_size', e.target.value)}
                />
            </td>
            <td>
                <input
                    type="number" step="0.01" min="0"
                    className={`form-control form-control-sm text-end ${form.errors.price ? 'is-invalid' : ''}`}
                    value={form.data.price}
                    onChange={(e) => form.setData('price', e.target.value)}
                />
            </td>
            <td>
                <input
                    type="number" step="0.01" min="0"
                    className={`form-control form-control-sm text-end ${form.errors.cost ? 'is-invalid' : ''}`}
                    value={form.data.cost}
                    onChange={(e) => form.setData('cost', e.target.value)}
                />
            </td>
            <td className="text-end">
                <button className="btn btn-sm btn-primary me-1" onClick={save} disabled={form.processing}>
                    <i className="bi bi-check"></i>
                </button>
                <button className="btn btn-sm btn-outline-secondary" onClick={() => setEditing(false)}>
                    <i className="bi bi-x"></i>
                </button>
            </td>
        </tr>
    );
}

function NewSaleUnitForm({ productId, variantId }) {
    const form = useForm({
        label: '',
        barcode: '',
        pack_size: '1',
        price: '0',
        cost: '0',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(
            `/inventory/products/${productId}/variants/${variantId}/sale-units`,
            {
                preserveScroll: true,
                onSuccess: () => form.reset(),
            }
        );
    };

    return (
        <form onSubmit={submit} className="row g-2 align-items-end mt-2">
            <div className="col-md-3">
                <input
                    className={`form-control form-control-sm ${form.errors.label ? 'is-invalid' : ''}`}
                    placeholder="Label"
                    value={form.data.label}
                    onChange={(e) => form.setData('label', e.target.value)}
                />
                {form.errors.label && <div className="invalid-feedback">{form.errors.label}</div>}
            </div>
            <div className="col-md-3">
                <input
                    className={`form-control form-control-sm ${form.errors.barcode ? 'is-invalid' : ''}`}
                    placeholder="Barcode"
                    value={form.data.barcode}
                    onChange={(e) => form.setData('barcode', e.target.value)}
                />
                {form.errors.barcode && <div className="invalid-feedback">{form.errors.barcode}</div>}
            </div>
            <div className="col-md-2">
                <input
                    type="number" min="1"
                    className={`form-control form-control-sm text-end ${form.errors.pack_size ? 'is-invalid' : ''}`}
                    placeholder="Pack"
                    value={form.data.pack_size}
                    onChange={(e) => form.setData('pack_size', e.target.value)}
                />
            </div>
            <div className="col-md-2">
                <input
                    type="number" step="0.01" min="0"
                    className={`form-control form-control-sm text-end ${form.errors.price ? 'is-invalid' : ''}`}
                    placeholder="Price"
                    value={form.data.price}
                    onChange={(e) => form.setData('price', e.target.value)}
                />
            </div>
            <div className="col-md-1">
                <input
                    type="number" step="0.01" min="0"
                    className={`form-control form-control-sm text-end ${form.errors.cost ? 'is-invalid' : ''}`}
                    placeholder="Cost"
                    value={form.data.cost}
                    onChange={(e) => form.setData('cost', e.target.value)}
                />
            </div>
            <div className="col-md-1">
                <button className="btn btn-sm btn-outline-primary w-100" disabled={form.processing}>
                    <i className="bi bi-plus-lg"></i>
                </button>
            </div>
        </form>
    );
}

// ----- Stock adjustment ---------------------------------------------------

function StockAdjustForm({ productId, variantId, onDone }) {
    const form = useForm({
        change_qty: '',
        reason: 'Restock',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(
            `/inventory/products/${productId}/variants/${variantId}/stock-adjustments`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    onDone?.();
                },
            }
        );
    };

    return (
        <div className="card-body border-bottom bg-body-secondary">
            <form onSubmit={submit} className="row g-2 align-items-end">
                <div className="col-md-3">
                    <label className="form-label small">Change (+/-)</label>
                    <input
                        type="number"
                        className={`form-control form-control-sm ${form.errors.change_qty ? 'is-invalid' : ''}`}
                        value={form.data.change_qty}
                        onChange={(e) => form.setData('change_qty', e.target.value)}
                        placeholder="e.g. 24 or -3"
                    />
                    {form.errors.change_qty && <div className="invalid-feedback">{form.errors.change_qty}</div>}
                </div>
                <div className="col-md-6">
                    <label className="form-label small">Reason</label>
                    <input
                        type="text"
                        className={`form-control form-control-sm ${form.errors.reason ? 'is-invalid' : ''}`}
                        value={form.data.reason}
                        onChange={(e) => form.setData('reason', e.target.value)}
                        list="reason-suggestions"
                    />
                    <datalist id="reason-suggestions">
                        <option value="Restock" />
                        <option value="Damage" />
                        <option value="Correction" />
                        <option value="Expired" />
                    </datalist>
                    {form.errors.reason && <div className="invalid-feedback">{form.errors.reason}</div>}
                </div>
                <div className="col-md-3">
                    <button className="btn btn-sm btn-primary w-100" disabled={form.processing}>
                        Apply adjustment
                    </button>
                </div>
            </form>
        </div>
    );
}

// ----- Page ---------------------------------------------------------------

export default function ProductEdit() {
    const { product } = usePage().props;

    const destroy = () => {
        if (!confirm(`Delete “${product.name}” and everything under it?`)) return;
        router.delete(`/inventory/products/${product.id}`);
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 className="h4 mb-0">{product.name}</h1>
                        <Link href="/inventory/products" className="small text-body-secondary text-decoration-none">
                            <i className="bi bi-arrow-left me-1"></i>Back to inventory
                        </Link>
                    </div>
                    <button className="btn btn-outline-danger" onClick={destroy}>
                        <i className="bi bi-trash me-1"></i>Delete product
                    </button>
                </div>
            }
        >
            <Head title={product.name} />

            <ProductForm product={product} />

            <div className="card shadow-sm mb-4">
                <div className="card-body">
                    <div className="d-flex justify-content-between align-items-center mb-3">
                        <h2 className="h5 mb-0">Variants</h2>
                        <span className="text-body-secondary small">
                            Stock is tracked in base units on each variant.
                        </span>
                    </div>

                    {product.variants.length === 0 && (
                        <div className="alert alert-info small mb-3">
                            No variants yet — add the first one below (e.g. “250ml”).
                        </div>
                    )}

                    {product.variants.map((v) => (
                        <VariantRow key={v.id} productId={product.id} variant={v} />
                    ))}

                    <div className="border-top pt-3 mt-3">
                        <h3 className="h6">Add variant</h3>
                        <NewVariantForm productId={product.id} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
