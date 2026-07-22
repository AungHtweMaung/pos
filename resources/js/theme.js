// Light / dark theme handling for Bootstrap's native `data-bs-theme` (spec §7).
// The initial theme is applied inline in app.blade.php to avoid a flash; this
// module handles reads and user-driven toggles thereafter.

const STORAGE_KEY = 'theme';

export function getPreferredTheme() {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'light' || stored === 'dark') {
        return stored;
    }
    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

export function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
}

export function setTheme(theme) {
    localStorage.setItem(STORAGE_KEY, theme);
    applyTheme(theme);
}

export function toggleTheme() {
    const next = getPreferredTheme() === 'dark' ? 'light' : 'dark';
    setTheme(next);
    return next;
}
