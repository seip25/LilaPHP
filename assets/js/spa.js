/**
 * LilaPHP SPA Navigation Engine
 * Provides Single Page Application behavior for Twig and React.
 */
(function () {
  const CONTENT_ID = 'lila-spa-content';
  const TIMEOUT_MS = 5000;

  /**
   * Navigates to a new page using SPA logic.
   * 
   * @param {string} url - The target URL
   * @param {boolean} [push=true] - Whether to push to browser history
   * @returns {Promise<void>}
   */
  async function navigate(url, push = true) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), TIMEOUT_MS);

    try {
      const separator = url.includes('?') ? '&' : '?';
      const response = await fetch(`${url}${separator}source=frontend`, {
        signal: controller.signal
      });

      clearTimeout(timeoutId);

      if (response.status === 401 || response.status === 403) {
        window.location.href = url;
        return;
      }

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
  
      if (data.meta) {
        document.title = data.meta.title || document.title;
        ['description', 'keywords', 'author'].forEach(name => {
          const el = document.querySelector(`meta[name="${name}"]`);
          if (el && data.meta[name]) el.setAttribute("content", data.meta[name]);
        });
      }

      if (data.css && Array.isArray(data.css)) {
        data.css.forEach(href => {
          if (!document.head.querySelector(`link[href="${href}"]`)) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            document.head.appendChild(link);
          }
        });
      }
 
      if (data.scripts && Array.isArray(data.scripts)) {
        data.scripts.forEach(scriptData => {
          const src = typeof scriptData === 'string' ? scriptData : scriptData.src;
          if (src) {
            if (!document.head.querySelector(`script[src="${src}"]`)) {
              const script = document.createElement('script');
              script.src = src;
              script.type = scriptData.type || 'module';
              document.head.appendChild(script);
            }
          } else if (scriptData.content) {
            const script = document.createElement('script');
            script.textContent = scriptData.content;
            script.type = scriptData.type || 'module';
            document.head.appendChild(script);
          }
        });
      }
 
      const container = document.getElementById(CONTENT_ID);
      if (container) {
        container.innerHTML = data.body;

        const partialScripts = container.querySelectorAll('script');
        const partialLinks = container.querySelectorAll('link[rel="stylesheet"]');

        partialLinks.forEach(link => {
          if (!document.head.querySelector(`link[href="${link.href}"]`)) {
            document.head.appendChild(link.cloneNode(true));
          }
          link.remove();
        });

        partialScripts.forEach(oldScript => {
          const src = oldScript.src;
          if (src) {
            if (!document.head.querySelector(`script[src="${src}"]`)) {
              const newScript = document.createElement('script');
              Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
              document.head.appendChild(newScript);
            }
          } else {
            const newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
            newScript.textContent = oldScript.textContent;
            document.head.appendChild(newScript);
          }
          oldScript.remove();
        });

        window.scrollTo(0, 0);
      }
 
      if (push) {
        window.history.pushState({ url }, data.meta?.title || '', url);
      }
 
      document.dispatchEvent(new CustomEvent('lila:navigation', {
        detail: { url, data }
      }));

    } catch (error) {
      if (error.name === 'AbortError') {
        console.warn('SPA Navigation timeout, redirecting...');
      } else {
        console.error('SPA Navigation failed:', error);
      }
      window.location.href = url;
    }
  }

  window.lilaNav = { navigate };

  document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', (e) => {
      const link = e.target.closest('a');
      
      if (link && 
          link.href && 
          link.href.startsWith(window.location.origin) && 
          !link.hasAttribute('download') && 
          !link.hasAttribute('data-no-spa') &&
          link.target !== '_blank' &&
          !e.ctrlKey && !e.metaKey && !e.shiftKey) {
        
        e.preventDefault();
        navigate(link.href);
      }
    });

    window.addEventListener('popstate', (e) => {
      if (e.state && e.state.url) {
        navigate(e.state.url, false);
      } else {
        window.location.reload();
      }
    });
  });
})();
