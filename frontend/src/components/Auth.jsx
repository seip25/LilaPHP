import React, { useState, useEffect } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { authConfig } from '../routes';

/**
 * Authentication Guard Wrapper Component.
 * 
 * Verifies user authentication by calling the configured API endpoint
 * before rendering protected child components. Shows a simple loading
 * spinner/skeleton while the auth check is in progress.
 * 
 * @param {object} props
 * @param {React.ReactNode} props.children - Protected content to render on success.
 * @param {string} [props.redirectTo] - Redirect path on auth failure.
 * @returns {JSX.Element}
 */
export default function Auth({ children, redirectTo }) {
  const [status, setStatus] = useState('checking'); // 'checking' | 'authenticated' | 'unauthenticated'
  const location = useLocation();
  const redirect = redirectTo || authConfig.defaultRedirect || '/login';

  useEffect(() => {
    let cancelled = false;

    async function checkAuth() {
      try {
        const res = await fetch(authConfig.endpoint, {
          method: authConfig.method || 'GET',
          credentials: 'include',
          headers: { 'Accept': 'application/json' },
        });

        if (!cancelled) {
          setStatus(res.ok ? 'authenticated' : 'unauthenticated');
        }
      } catch {
        if (!cancelled) {
          setStatus('unauthenticated');
        }
      }
    }

    checkAuth();
    return () => { cancelled = true; };
  }, [location.pathname]);

  if (status === 'checking') {
    return (
      <div style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: '60vh',
        fontFamily: 'system-ui, sans-serif',
      }}>
        <div style={{ textAlign: 'center' }}>
          <div style={{
            width: '36px',
            height: '36px',
            border: '3px solid rgba(255,255,255,.1)',
            borderTopColor: 'rgba(255,255,255,.5)',
            borderRadius: '50%',
            animation: 'lila-spin .6s linear infinite',
            margin: '0 auto 12px',
          }} />
          <p style={{ color: 'rgba(255,255,255,.4)', fontSize: '13px', margin: 0 }}>
            Verifying session...
          </p>
        </div>
      </div>
    );
  }

  if (status === 'unauthenticated') {
    return <Navigate to={redirect} replace state={{ from: location }} />;
  }

  return <>{children}</>;
}
