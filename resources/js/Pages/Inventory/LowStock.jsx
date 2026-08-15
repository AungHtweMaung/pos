import { Head, Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

export default function LowStock() {
    const { variants } = usePage().props;

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="h4 mb-0">Low stock</h1>
                    <p className="text-body-secondary small mb-0">
                        Variants at or below their low-stock threshold. Variants without a threshold aren't tracked here.
                    </p>
                </div>
            }
        >
            <Head title="Low stock" />

            <div className="card shadow-sm">
                <div className="table-responsive">
                    <table className="table table-hover mb-0 align-middle">
                        <thead className="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Variant</th>
                                <th>Category</th>
                                <th className="text-end">Stock</th>
                                <th className="text-end">Threshold</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {variants.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-center text-body-secondary py-4">
                                        <i className="bi bi-check-circle me-2 text-success"></i>
                                        Nothing is low-stock right now.
                                    </td>
                                </tr>
                            )}
                            {variants.map((v) => (
                                <tr key={v.id}>
                                    <td>
                                        <Link
                                            href={`/inventory/products/${v.product.id}/edit`}
                                            className="text-decoration-none fw-semibold"
                                        >
                                            {v.product.name}
                                        </Link>
                                    </td>
                                    <td>{v.label}</td>
                                    <td>
                                        {v.product.category ? (
                                            <span className="badge text-bg-light">{v.product.category}</span>
                                        ) : (
                                            <span className="text-body-secondary">—</span>
                                        )}
                                    </td>
                                    <td className="text-end">
                                        <span
                                            className={`badge ${
                                                v.stock_qty === 0 ? 'text-bg-danger' : 'text-bg-warning'
                                            }`}
                                        >
                                            {v.stock_qty}
                                        </span>
                                    </td>
                                    <td className="text-end">{v.low_stock_threshold}</td>
                                    <td className="text-end">
                                        <Link
                                            href={`/inventory/products/${v.product.id}/edit`}
                                            className="btn btn-sm btn-outline-primary"
                                        >
                                            <i className="bi bi-arrow-left-right me-1"></i>Restock
                                        </Link>
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
