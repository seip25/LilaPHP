import React, { Suspense, lazy } from 'react';
import ReactDOM from 'react-dom/client';
import { createBrowserRouter, RouterProvider } from 'react-router-dom';
import { generateRoutes } from './routes';
import Auth from './components/Auth';
import Public from './components/Public';
import App from './App';
import './style.css';

/**
 * Loading fallback component shown while lazy pages load.
 */
function PageLoader() {
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
          Loading...
        </p>
      </div>
    </div>
  );
}

/**
 * Wraps a lazy-loaded page component with the appropriate guard.
 * 
 * @param {object} routeDef - Route definition from generateRoutes()
 * @returns {JSX.Element}
 */
function wrapRoute(routeDef) {
  const LazyPage = lazy(() => routeDef.module());

  const element = (
    <Suspense fallback={<PageLoader />}>
      <LazyPage />
    </Suspense>
  );

  if (routeDef.auth) {
    return <Auth redirectTo={routeDef.redirectTo}>{element}</Auth>;
  }
  return <Public>{element}</Public>;
}

/**
 * Build React Router routes dynamically from pages/ directory.
 * 
 * Each .jsx file in pages/ generates a route automatically.
 * Routes marked with `auth: true` in routes.jsx config are
 * wrapped with the Auth guard component.
 */
const dynamicRoutes = generateRoutes();

const children = dynamicRoutes.map((routeDef) => {
  if (routeDef.path === '/') {
    return {
      index: true,
      element: wrapRoute(routeDef),
    };
  }
  return {
    path: routeDef.path,
    element: wrapRoute(routeDef),
  };
});

const router = createBrowserRouter([
  {
    path: '/',
    element: <App />,
    children,
  },
]);

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <RouterProvider router={router} />
  </React.StrictMode>
);
