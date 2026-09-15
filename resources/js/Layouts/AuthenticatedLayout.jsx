import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ThemeToggle from '../Components/ThemeToggle';

// The single shared dashboard shell (spec §5, §7). Navigation lives in a
// left sidebar that is always visible on large screens and collapses into a
// slide-in drawer (Bootstrap responsive offcanvas) below the `xl` breakpoint.
// Role only decides which items appear.
const NAV_ITEMS = [
    { key: 'dashboard', label: 'Dashboard', icon: 'bi-speedometer2', adminOnly: false, href: '/dashboard' },
    { key: 'pos', label: 'Sales / POS', icon: 'bi-cart', adminOnly: false, href: '/pos' },
    { key: 'my-sales', label: 'My Sales', icon: 'bi-receipt-cutoff', adminOnly: false, href: '/my-sales' },
    { key: 'sales', label: 'Sales history', icon: 'bi-receipt', adminOnly: true, href: '/sales' },
    { key: 'inventory', label: 'Inventory', icon: 'bi-box-seam', adminOnly: true, href: '/inventory/products' },
    { key: 'low-stock', label: 'Low Stock', icon: 'bi-exclamation-triangle', adminOnly: true, href: '/inventory/low-stock' },
    { key: 'shift', label: 'End of Shift', icon: 'bi-cash-stack', adminOnly: false, href: '/shift' },
    { key: 'cashiers', label: 'Cashiers', icon: 'bi-people', adminOnly: true, href: '/cashiers' },
    { key: 'reports', label: 'Reports', icon: 'bi-graph-up', adminOnly: true, href: '/reports' },
];

export default function AuthenticatedLayout({ header, children }) {
    const page = usePage();
    const { auth, app, flash } = page.props;
    const user = auth.user;

    // Drawer open state for narrow screens. The sidebar is React-controlled
    // (Bootstrap's offcanvas JS conflicts with Inertia navigation), toggling
    // the `.show` class + a backdrop only below the xl breakpoint.
    const [drawerOpen, setDrawerOpen] = useState(false);
    const closeDrawer = () => setDrawerOpen(false);

    // Current path (minus query) drives the active nav highlight.
    const path = (page.url || '').split('?')[0];
    const isActive = (href) =>
        href === '/dashboard'
            ? path === '/dashboard' || path === '/'
            : path.startsWith(href);

    const visibleItems = NAV_ITEMS.filter(
        (item) => !item.adminOnly || user.is_admin
    );

    const handleLogout = (e) => {
        e.preventDefault();
        router.post('/logout');
    };

    const brand = (
        <Link className="navbar-brand fw-semibold m-0 d-inline-flex align-items-center" href="/dashboard">
            <i className="bi bi-shop me-2"></i>
            {app?.name || 'Grocery POS'}
        </Link>
    );

    return (
        <div className="d-flex min-vh-100">
            {/* Backdrop — only below xl, only while the drawer is open */}
            {drawerOpen && (
                <div
                    className="offcanvas-backdrop fade show d-xl-none"
                    onClick={closeDrawer}
                ></div>
            )}

            {/* Sidebar — static column on xl+, slide-in drawer below xl */}
            <aside
                className={`app-sidebar d-flex flex-column bg-body-tertiary border-end ${
                    drawerOpen ? 'show' : ''
                }`}
                id="sidebar"
                aria-label="Main navigation"
            >
                <div className="d-flex align-items-center justify-content-between border-bottom p-3">
                    {brand}
                    <button
                        type="button"
                        className="btn-close d-xl-none"
                        aria-label="Close"
                        onClick={closeDrawer}
                    ></button>
                </div>

                <div className="flex-grow-1 overflow-y-auto">
                    <ul className="nav nav-pills flex-column p-2 gap-1">
                        {visibleItems.map((item) => (
                            <li className="nav-item" key={item.key}>
                                {item.href ? (
                                    <Link
                                        href={item.href}
                                        onClick={closeDrawer}
                                        className={`nav-link d-flex align-items-center gap-2 ${
                                            isActive(item.href) ? 'active' : 'text-body'
                                        }`}
                                    >
                                        <i className={`bi ${item.icon}`}></i>
                                        {item.label}
                                    </Link>
                                ) : (
                                    <span
                                        className="nav-link disabled d-flex align-items-center gap-2"
                                        aria-disabled="true"
                                    >
                                        <i className={`bi ${item.icon}`}></i>
                                        {item.label}
                                        <span className="badge text-bg-secondary ms-auto">soon</span>
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            </aside>

            {/* Main column */}
            <div className="d-flex flex-column flex-grow-1 min-vh-100" style={{ minWidth: 0 }}>
                {/* Top bar: hamburger (mobile) + brand (mobile) + controls */}
                <div className="navbar bg-body-tertiary border-bottom px-3">
                    <div className="d-flex align-items-center gap-2">
                        <button
                            className="btn btn-outline-secondary btn-sm d-xl-none"
                            type="button"
                            aria-controls="sidebar"
                            aria-expanded={drawerOpen}
                            aria-label="Toggle navigation"
                            onClick={() => setDrawerOpen((v) => !v)}
                        >
                            <i className="bi bi-list"></i>
                        </button>
                        <span className="d-xl-none">{brand}</span>
                    </div>

                    <div className="d-flex align-items-center gap-2 ms-auto">
                        <ThemeToggle className="btn-sm" />

                        <div className="dropdown">
                            <button
                                className="btn btn-outline-secondary btn-sm dropdown-toggle d-inline-flex align-items-center"
                                type="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                            >
                                <i className="bi bi-person-circle me-1"></i>
                                <span className="d-none d-sm-inline">{user.name}</span>
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
                                    <a className="dropdown-item" href="/logout" onClick={handleLogout}>
                                        <i className="bi bi-box-arrow-right me-2"></i>
                                        Log out
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                {header && (
                    <header className="bg-body-tertiary border-bottom">
                        <div className="container-fluid py-3 px-3 px-md-4">{header}</div>
                    </header>
                )}

                <main className="flex-grow-1 py-4 px-3 px-md-4">
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
                    <div className="text-center small text-body-secondary">
                        {app?.name || 'Grocery POS'} · Point of Sale
                    </div>
                </footer>
            </div>
        </div>
    );
}
