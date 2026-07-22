import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

export default function ProductsIndex() {
    const { products, filters } = usePage().props;
    const [q, setQ] = useState(filters?.q || '');

    const search = (e) => {
        e.preventDefault();
        router.get('/inventory/products', { q }, { preserveState: true, replace: true });
    };

    const destroy = (product) => {
        if (!confirm(`Delete product “${product.name}”? Variants and sale units will also be removed.`)) return;
        router.delete(`/inventory/products/${product.id}`);
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 className="h4 mb-0">Inventory</h1>
                        <p className="text-body-secondary small mb-0">Products, variants and sale units.</p>
                    </div>
                    <Link href="/inventory/products/create" className="btn btn-primary">
                        <i className="bi bi-plus-lg me-1"></i>
                        New product
                    </Link>
                </div>
            }
        >
            <Head title="Inventory" />

            <form onSubmit={search} className="mb-3">
                <div className="input-group">
                    <span className="input-group-text">
                        <i className="bi bi-search"></i>
                    </span>
                    <input
                        type="search"
                        className="form-control"
                        placeholder="Search by name, category or barcode…"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                    />
                    <button type="submit" className="btn btn-outline-secondary">Search</button>
                </div>
            </form>

            <div className="card shadow-sm">
                <div className="table-responsive">
                    <table className="table table-hover mb-0 align-middle">
                        <thead className="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th className="text-end">Tax %</th>
                                <th className="text-end">Variants</th>
                                <th className="text-end">Stock (base)</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {products.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-center text-body-secondary py-4">
                                        No products yet. <Link href="/inventory/products/create">Add one</Link>.
                                    </td>
                                </tr>
                            )}
                            {products.data.map((p) => (
                                <tr key={p.id}>
                                    <td>
                                        <Link href={`/inventory/products/${p.id}/edit`} className="text-decoration-none fw-semibold">
                                            {p.name}
                                        </Link>
                                        {p.has_low_stock && (
                                            <span className="badge text-bg-warning ms-2" title="One or more variants at or below threshold">
                                                <i className="bi bi-exclamation-triangle me-1"></i>Low
                                            </span>
                                        )}
                                    </td>
                                    <td>
                                        {p.category ? (
                                            <span className="badge text-bg-light">{p.category}</span>
                                        ) : (
                                            <span className="text-body-secondary">—</span>
                                        )}
                                    </td>
                                    <td className="text-end">{p.tax_rate.toFixed(2)}</td>
                                    <td className="text-end">{p.variants_count}</td>
                                    <td className="text-end">{p.total_stock}</td>
                                    <td className="text-end">
                                        <Link
                                            href={`/inventory/products/${p.id}/edit`}
                                            className="btn btn-sm btn-outline-secondary me-1"
                                        >
                                            <i className="bi bi-pencil"></i>
                                        </Link>
                                        <button
                                            type="button"
                                            className="btn btn-sm btn-outline-danger"
                                            onClick={() => destroy(p)}
                                        >
                                            <i className="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {products.links && products.data.length > 0 && (
                <nav className="mt-3">
                    <ul className="pagination pagination-sm justify-content-center mb-0">
                        {products.links.map((link, i) => (
                            <li
                                key={i}
                                className={`page-item ${link.active ? 'active' : ''} ${!link.url ? 'disabled' : ''}`}
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
