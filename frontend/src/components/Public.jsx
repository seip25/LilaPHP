import React from 'react';

/**
 * Public Route Wrapper Component.
 * 
 * Passthrough wrapper for non-authenticated routes.
 * Exists as a hook point for future global logic that may apply
 * to all public routes (analytics, feature flags, A/B testing, etc.).
 * 
 * @param {object} props
 * @param {React.ReactNode} props.children - Public content to render.
 * @returns {JSX.Element}
 */
export default function Public({ children }) {
  return <>{children}</>;
}
