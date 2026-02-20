import { createRoot } from 'react-dom/client';

const componentModules = import.meta.glob('./pages/*.jsx');


document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-react-component]').forEach(async (el) => {

    const name = el.dataset.reactComponent;
    const props = JSON.parse(el.dataset.props || '{}');

    const importer = componentModules[`./pages/${name}.jsx`];

    if (importer) {
      const module = await importer();
      const Component = module.default;
      createRoot(el).render(<Component {...props} />);
    }
  });
});

