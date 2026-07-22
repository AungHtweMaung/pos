import { useEffect, useState } from 'react';
import { getPreferredTheme, toggleTheme } from '../theme';

export default function ThemeToggle({ className = '' }) {
    const [theme, setThemeState] = useState('light');

    useEffect(() => {
        setThemeState(getPreferredTheme());
    }, []);

    const handleToggle = () => {
        setThemeState(toggleTheme());
    };

    const isDark = theme === 'dark';

    return (
        <button
            type="button"
            className={`btn btn-outline-secondary ${className}`}
            onClick={handleToggle}
            aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
            title={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
        >
            <i className={`bi ${isDark ? 'bi-sun-fill' : 'bi-moon-stars-fill'}`}></i>
        </button>
    );
}
