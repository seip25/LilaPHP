import React from 'react';
import { createRoot } from 'react-dom/client';

// Auto-import all components from ./components
const componentModules = import.meta.glob('./pages/*.jsx', { eager: true });

const components = Object.keys(componentModules).reduce((acc, path) => {
  const name = path.split('/').pop().replace('.jsx', '');
  acc[name] = componentModules[path].default;
  return acc;
}, {});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-react-component]').forEach(el => {
      const name = el.dataset.reactComponent;
      const props = JSON.parse(el.dataset.props || '{}');
  
      const Component = components[name];
      if (Component) {
        createRoot(el).render(<Component {...props} />);
        console.log(`Hydrated component: ${name}`);
      } else {
        console.warn(`React component "${name}" not found.`);
      }
    });
  });