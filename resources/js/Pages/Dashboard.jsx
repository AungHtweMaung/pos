import { Head, Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '../Layouts/AuthenticatedLayout';

// Cards mirror the §8 modules. `admin` marks admin-only areas; the grid is
// filtered by role so a cashier sees only what they're permitted to reach.
const CARDS = [
    {
        key: 'pos',
        title: 'New Sale',
        text: 'Scan or search items, take payment, print a receipt.',
        icon: 'bi-cart-plus',
        admin: false,
        href: '/pos',
    },
    {
        key: 'sales',
        title: 'Sales history',
        text: 'Every completed, voided or refunded sale.',
        icon: 'bi-receipt',
        admin: true,
        href: '/sales',
    },
    {
        key: 'shift',
        title: 'End of Shift',
        text: 'Count the drawer and reconcile against expected cash.',
        icon: 'bi-cash-stack',
        admin: false,
    },
    {
        key: 'inventory',
        title: 'Inventory',
        text: 'Products, variants, sale units and stock adjustments.',
        icon: 'bi-box-seam',
        admin: true,
        href: '/inventory/products',
    },
    {
        key: 'low-stock',
        title: 'Low Stock',
        text: 'Variants at or below their re-order threshold.',
        icon: 'bi-exclamation-triangle',
        admin: true,
        href: '/inventory/low-stock',
    },
    {
        key: 'cashiers',
        title: 'Cashiers',
        text: 'Create and manage cashier & admin accounts.',
        icon: 'bi-people',
        admin: true,
    },
    {
        key: 'reports',
        title: 'Reports',
        text: 'Daily sales, best-sellers, void/refund log.',
        icon: 'bi-graph-up',
        admin: true,
    },
];

export default function Dashboard() {
    const { auth } = usePage().props;
    const user = auth.user;

    const cards = CARDS.filter((card) => !card.admin || user.is_admin);

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 className="h4 mb-0">Dashboard</h1>
                        <p className="text-body-secondary small mb-0">
                            Welcome back, {user.name}.
                        </p>
                    </div>
                    <span
                        className={`badge fs-6 ${
                            user.is_admin ? 'text-bg-primary' : 'text-bg-info'
                        }`}
                    >
                        <i className="bi bi-person-badge me-1"></i>
                        {user.role}
                    </span>
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="row g-3">
                {cards.map((card) => (
                    <div className="col-12 col-sm-6 col-lg-4" key={card.key}>
                        <div className="card h-100 shadow-sm">
                            <div className="card-body">
                                <div className="d-flex align-items-center mb-2">
                                    <span className="d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary me-3" style={{ width: '2.5rem', height: '2.5rem' }}>
                                        <i className={`bi ${card.icon} fs-5`}></i>
                                    </span>
                                    <h2 className="h5 card-title mb-0">{card.title}</h2>
                                </div>
                                <p className="card-text text-body-secondary small">
                                    {card.text}
                                </p>
                            </div>
                            <div className="card-footer bg-transparent border-0 pb-3">
                                {card.href ? (
                                    <Link className="btn btn-sm btn-outline-primary" href={card.href}>
                                        Open <i className="bi bi-arrow-right ms-1"></i>
                                    </Link>
                                ) : (
                                    <button className="btn btn-sm btn-outline-primary" disabled>
                                        Coming soon
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            <div className="alert alert-info mt-4 d-flex align-items-start" role="alert">
                <i className="bi bi-info-circle me-2 mt-1"></i>
                <div>
                    Authentication and the shared dashboard shell are in place.
                    The module screens above are the next build steps.
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
