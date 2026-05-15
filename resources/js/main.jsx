import { createRoot } from 'react-dom/client';

const componentModules = import.meta.glob('./components/*.jsx');
const pageModules = import.meta.glob('./pages/*.jsx');
const mountedRoots = new Map();

/**
 * Renders or re-renders React components found in the DOM.
 * 
 * @param {string} [name='all'] - The name of the component to render (matching data-react-component). Use "all" to render everything.
 */
window.renderReactComponent = async (name = 'all', type = 'component') => {
  if (!name || name === '') name = 'all';
  document.querySelectorAll(`[data-react-${type}]`).forEach(async (el) => {
    const componentName = el.getAttribute(`data-react-${type}`);
    if (name !== 'all' && componentName !== name) return;

    const props = JSON.parse(el.dataset.props || '{}');
    const importer = type === "component" ? componentModules[`./components/${componentName}.jsx`] : pageModules[`./pages/${componentName}.jsx`];

    if (importer) {
      try {
        const module = await importer();
        const Component = module.default;

        if (!mountedRoots.has(el)) {
          mountedRoots.set(el, createRoot(el));
        }
        mountedRoots.get(el).render(<Component {...props} key={Math.random()} />);
      } catch (error) {
        console.error(`Failed to render React component: ${componentName}`, error);
      }
    }
    else {
      console.log(`Component ${componentName} ${type} not found`);
    }
  });
};

const bootReact = () => {
  window.renderReactComponent();
  window.renderReactComponent('all', 'page');
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootReact);
} else {
  bootReact();
}

document.addEventListener('lila:navigation', (e) => {
  bootReact();
});

