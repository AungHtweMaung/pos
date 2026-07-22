import { Head, Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

export default function ProductCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        category: '',
        tax_rate: '0',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/inventory/products');
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="h4 mb-0">New product</h1>
                    <Link href="/inventory/products" className="small text-body-secondary text-decoration-none">
                        <i className="bi bi-arrow-left me-1"></i>Back to inventory
                    </Link>
                </div>
            }
        >
            <Head title="New product" />

            <div className="card shadow-sm" style={{ maxWidth: '32rem' }}>
                <form onSubmit={submit} className="card-body">
                    <div className="mb-3">
                        <label htmlFor="name" className="form-label">Name</label>
                        <input
                            id="name"
                            type="text"
                            className={`form-control ${errors.name ? 'is-invalid' : ''}`}
                            value={data.name}
                            autoFocus
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        {errors.name && <div className="invalid-feedback">{errors.name}</div>}
                    </div>

                    <div className="mb-3">
                        <label htmlFor="category" className="form-label">
                            Category <span className="text-body-secondary">(optional)</span>
                        </label>
                        <input
                            id="category"
                            type="text"
                            className={`form-control ${errors.category ? 'is-invalid' : ''}`}
                            value={data.category}
                            onChange={(e) => setData('category', e.target.value)}
                        />
                        {errors.category && <div className="invalid-feedback">{errors.category}</div>}
                    </div>

                    <div className="mb-3">
                        <label htmlFor="tax_rate" className="form-label">Tax rate (%)</label>
                        <input
                            id="tax_rate"
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

                    <div className="d-flex gap-2">
                        <button type="submit" className="btn btn-primary" disabled={processing}>
                            {processing ? 'Saving…' : 'Create product'}
                        </button>
                        <Link href="/inventory/products" className="btn btn-outline-secondary">
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
