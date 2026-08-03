import React from 'react';
import { Link, Outlet } from 'react-router-dom';

/**
 * Main Layout Component with persistent navigation and router outlet.
 * 
 * @returns {JSX.Element}
 */
export default function App() {
  return (
    <>
      <div className="background-glow glow-1"></div>
      <div className="background-glow glow-2"></div>

      <header className="navbar">
        <div className="container navbar-content">
          <Link to="/" className="brand" style={{ textDecoration: 'none' }}>
            <span className="brand-badge">⚡ PHP 8.4 + React</span>
            <h1 className="brand-title">
              Lila<span className="accent">PHP</span>
            </h1>
          </Link>
          <nav className="nav-links">
            <Link to="/" className="nav-link">
              Home
            </Link>
            <a
              href="/debug"
              className="nav-link"
              style={{ color: '#a78bfa', fontWeight: '600' }}
            >
              🛠️ Debug
            </a>
          </nav>
        </div>
      </header>

      <main className="container main-content">
        <Outlet />
      </main>

      <footer className="footer">
        <div className="container footer-content">
          <p>© 2026 LilaPHP Framework. High-Performance API & React Engine.</p>
          <div className="footer-meta">PHP 8.4+ • Vite • Nginx FastCGI</div>
        </div>
      </footer>
    </>
  );
}
