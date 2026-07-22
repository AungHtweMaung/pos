import { Link, router, usePage } from '@inertiajs/react';
import ThemeToggle from '../Components/ThemeToggle';

// The single shared dashboard layout (spec §5, §7). Both roles get the same
// chrome; role only decides which nav items appear. Modules other than the
// dashboard aren't built yet, so their links are shown disabled to convey the
// structure without dead-ending on missing routes.
const NAV_ITEMS = [
    { key: 'pos', label: 'Sales / POS', icon: 'bi-cart', adminOnly: false, href: null },
    { key: 'inventory', label: 'Inventory', icon: 'bi-box-seam', adminOnly: true, href: '/inventory/products' },
    { key: 'low-stock', label: 'Low Stock', icon: 'bi-exclamation-triangle', adminOnly: true, href: '/inventory/low-stock' },
    { key: 'shift', label: 'End of Shift', icon: 'bi-cash-stack', adminOnly: false, href: null },
    { key: 'cashiers', label: 'Cashiers', icon: 'bi-people', adminOnly: true, href: null },
    { key: 'reports', label: 'Reports', icon: 'bi-graph-up', adminOnly: true, href: null },
];

export default function AuthenticatedLayout({ header, children }) {
    const { auth, app, flash } = usePage().props;
    const user = auth.user;

    const visibleItems = NAV_ITEMS.filter(
        (item) => !item.adminOnly || user.is_admin
    );

    const handleLogout = (e) => {
        e.preventDefault();
        router.post('/logout');
    };

    return (
        <div className="d-flex flex-column min-vh-100">
            <nav className="navbar navbar-expand-lg border-bottom bg-body-tertiary">
                <div className="container-fluid">
                    <Link className="navbar-brand fw-semibold" href="/dashboard">
                        <i className="bi bi-shop me-2"></i>
                        {app?.name || 'Grocery POS'}
                    </Link>

                    <button
                        className="navbar-toggler"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#mainNav"
                        aria-controls="mainNav"
                        aria-expanded="false"
                        aria-label="Toggle navigation"
                    >
                        <span className="navbar-toggler-icon"></span>
                    </button>

                    <div className="collapse navbar-collapse" id="mainNav">
                        <ul className="navbar-nav me-auto mb-2 mb-lg-0">
                            <li className="nav-item">
                                <Link className="nav-link active" href="/dashboard">
                                    <i className="bi bi-speedometer2 me-1"></i>
                                    Dashboard
                                </Link>
                            </li>
                            {visibleItems.map((item) => (
                                <li className="nav-item" key={item.key}>
                                    {item.href ? (
                                        <Link className="nav-link d-inline-flex align-items-center" href={item.href}>
                                            <i className={`bi ${item.icon} me-1`}></i>
                                            {item.label}
                                        </Link>
                                    ) : (
                                        <span
                                            className="nav-link disabled d-inline-flex align-items-center"
                                            aria-disabled="true"
                                            title="Coming soon"
                                        >
                                            <i className={`bi ${item.icon} me-1`}></i>
                                            {item.label}
                                            <span className="badge text-bg-secondary ms-2">soon</span>
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>

                        <div className="d-flex align-items-center gap-2">
                            <ThemeToggle className="btn-sm" />

                            <div className="dropdown">
                                <button
                                    className="btn btn-outline-secondary btn-sm dropdown-toggle d-inline-flex align-items-center"
                                    type="button"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false"
                                >
                                    <i className="bi bi-person-circle me-1"></i>
                                    {user.name}
                                    <span
                                        className={`badge ms-2 ${
                                            user.is_admin ? 'text-bg-primary' : 'text-bg-info'
                                        }`}
                                    >
                                        {user.role}
                                    </span>
                                </button>
                                <ul className="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <span className="dropdown-item-text small text-body-secondary">
                                            Signed in as <strong>{user.username}</strong>
                                        </span>
                                    </li>
                                    <li>
                                        <hr className="dropdown-divider" />
                                    </li>
                                    <li>
                                        <a
                                            className="dropdown-item"
                                            href="/logout"
                                            onClick={handleLogout}
                                        >
                                            <i className="bi bi-box-arrow-right me-2"></i>
                                            Log out
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="bg-body-tertiary border-bottom">
                    <div className="container py-3">{header}</div>
                </header>
            )}

            <main className="container flex-grow-1 py-4">
                {flash?.success && (
                    <div className="alert alert-success d-flex align-items-center" role="alert">
                        <i className="bi bi-check-circle me-2"></i>
                        <div>{flash.success}</div>
                    </div>
                )}
                {flash?.error && (
                    <div className="alert alert-danger d-flex align-items-center" role="alert">
                        <i className="bi bi-x-circle me-2"></i>
                        <div>{flash.error}</div>
                    </div>
                )}
                {children}
            </main>

            <footer className="border-top py-3 mt-auto bg-body-tertiary">
                <div className="container text-center small text-body-secondary">
                    {app?.name || 'Grocery POS'} · Point of Sale
                </div>
            </footer>
        </div>
    );
}
