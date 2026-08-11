/**
 * LilaPHP React SPA Route Configuration.
 * 
 * Defines route-to-page mappings, authentication wrappers, and
 * redirect behavior for the client-side React Router.
 * 
 * Each page file in `pages/` is auto-discovered and lazy-loaded.
 * Wrap routes with `auth: true` to require authentication via /api/auth.
 * 
 * @module routes
 */

/**
 * Auto-import all page components from `pages/` directory.
 * Vite's import.meta.glob returns { './pages/Home.jsx': () => import(...) }
 */
const pageModules = import.meta.glob('./pages/**/*.jsx');

/**
 * Converts file path to route path (Next.js style).
 * 
 * Examples:
 *   ./pages/Home.jsx        → /
 *   ./pages/About.jsx       → /about
 *   ./pages/Login.jsx       → /login
 *   ./pages/Dashboard.jsx   → /dashboard
 *   ./pages/users/Index.jsx → /users
 *   ./pages/users/Edit.jsx  → /users/edit
 * 
 * @param {string} filePath
 * @returns {string}
 */
function filePathToRoute(filePath) {
  let route = filePath
    .replace('./pages/', '/')
    .replace(/\.jsx$/i, '')
    .toLowerCase();

  // /home → / (index route)
  if (route === '/home') return '/';
  // /something/index → /something
  if (route.endsWith('/index')) {
    route = route.replace(/\/index$/, '') || '/';
  }
  return route;
}

/**
 * Route configuration overrides.
 * 
 * Use this object to mark specific routes as authenticated,
 * set custom redirect paths, or provide additional metadata.
 * Routes not listed here default to `{ auth: false }`.
 */
export const routeConfig = {
  '/dashboard': {
    auth: true,
    redirectTo: '/login',
  },
  // Add more protected routes:
  // '/settings': { auth: true, redirectTo: '/login' },
  // '/admin': { auth: true, redirectTo: '/' },
};

/**
 * Auth configuration for the entire application.
 */
export const authConfig = {
  /** API endpoint to verify authentication */
  endpoint: '/api/auth',
  /** Default redirect when auth fails (overridable per route) */
  defaultRedirect: '/login',
  /** HTTP method for auth check */
  method: 'GET',
};

/**
 * Generates the route definitions array for React Router.
 * Each page module is lazy-loaded for code splitting.
 * 
 * @returns {Array<{path: string, module: () => Promise, auth: boolean, redirectTo: string}>}
 */
export function generateRoutes() {
  const routes = [];

  for (const [filePath, importFn] of Object.entries(pageModules)) {
    const path = filePathToRoute(filePath);
    const config = routeConfig[path] || {};

    routes.push({
      path,
      module: importFn,
      auth: config.auth || false,
      redirectTo: config.redirectTo || authConfig.defaultRedirect,
    });
  }

  // Sort: index route first, then alphabetical
  routes.sort((a, b) => {
    if (a.path === '/') return -1;
    if (b.path === '/') return 1;
    return a.path.localeCompare(b.path);
  });

  return routes;
}
