/**
 * Lila.js — TypeScript Source & Type Definitions
 * Unified Reactive SPA Engine, HTTP API Client & Bluebird UI Suite for LilaPHP.
 *
 * @version 3.0.0
 * @license MIT
 * @see https://seip25.github.io/LilaPHP/
 * @module Lila
 */

/**
 * Options for a toast notification.
 * @example
 * Lila.toast({ title: 'Saved!', type: 'success' });
 * Lila.toast('Quick message');
 */
export interface ToastOptions {
  title?: string;
  description?: string;
  message?: string;
  /** @default 'info' */
  type?: "info" | "success" | "error" | "warning";
  /** @default 'bottom-right' */
  position?:
    | "top-left" | "top-right"
    | "bottom-left" | "bottom-right"
    | "top-center" | "bottom-center";
  /** Auto-dismiss delay in ms. 0 = manual close only. @default 4000 */
  duration?: number;
}

/**
 * Options for a simple snackbar.
 * @example Lila.snackbar({ message: 'Done!', type: 'success' });
 */
export interface SnackbarOptions {
  message?: string;
  /** @default 'info' */
  type?: "info" | "success" | "error" | "warning";
  /** @default 3000 */
  duration?: number;
}

/**
 * Context object injected into every route action handler.
 * @template S Shape of the current route reactive state.
 * @example
 * actions: {
 *   deleteUser: async (ctx) => {
 *     await Lila.fetch(`/api/users/${ctx.id}`, { method: 'DELETE' });
 *     ctx.state.users = ctx.state.users.filter(u => u.id != ctx.id);
 *   }
 * }
 */
export interface ActionContext<S = any> {
  event: Event;
  state: S;
  /** Value of the element data-id attribute. */
  id?: string;
  /** Value of the element data-nombre attribute. */
  nombre?: string;
  [key: string]: any;
}

/**
 * Configuration object for `Lila.route()` and `Lila.mount()`.
 * @template S Shape of the reactive state.
 * @example
 * Lila.route('/users', {
 *   state: () => ({ users: [], loading: true }),
 *   template: (s) => Lila.html`
 *     <ul>${s.users.map(u => `<li>${Lila.escapeHtml(u.name)}</li>`)}</ul>
 *   `,
 *   onMount: async (state) => {
 *     const res = await Lila.api.get('/users');
 *     state.users   = res.data ?? [];
 *     state.loading = false;
 *   },
 *   actions: {
 *     deleteUser: async (ctx) => {
 *       await Lila.fetch(`/api/users/${ctx.id}`, { method: 'DELETE' });
 *     }
 *   }
 * });
 */
export interface RouteConfig<S = any> {
  state?: (() => S) | S;
  template?: (state: S) => string;
  onMount?: (state: S) => void | (() => void) | Promise<void | (() => void)>;
  onDestroy?: (state: S) => void;
  /** Handlers triggered by data-action="name" on DOM elements. */
  actions?: Record<string, (context: ActionContext<S>) => void | Promise<void>>;
}

/**
 * Instance returned by `Lila.mount()`.
 * @template S Shape of the component reactive state.
 */
export interface ComponentInstance<S = any> {
  /** Live reactive state proxy. Mutate to trigger re-renders. */
  state: S;
  /** Unmounts the component and clears the DOM. */
  unmount: () => void;
}

/**
 * Standard JSON envelope from LilaPHP REST endpoints.
 * @template T Type of the data payload.
 * @example
 * const res = await Lila.api.get('/users');
 * const users = res.data ?? [];
 */
export interface ApiResponse<T = any> {
  status?: string;
  error?: boolean;
  code?: number;
  message?: string;
  data?: T;
  [key: string]: any;
}

/**
 * RequestInit extended with a query-string params helper.
 * @example
 * Lila.fetch('/api/users', { params: { page: 1, limit: 20 } });
 */
export interface RequestOptions extends RequestInit {
  /** Key-value pairs appended as URL query parameters. */
  params?: Record<string, string | number | boolean>;
}

/**
 * Reactive primitive signal wrapping a single value.
 * @template T Type of the tracked value.
 * @example
 * const count = Lila.state(0);
 * count.subscribe(n => { el.textContent = n; });
 * count.set(n => n + 1);
 */
export interface StateSignal<T> {
  /** Returns the current value. */
  get: () => T;
  /**
   * Sets a new value or derives from previous.
   * @example counter.set(prev => prev + 1);
   */
  set: (val: T | ((prev: T) => T)) => void;
  /**
   * Subscribes to changes. Fires immediately with the current value.
   * @returns Unsubscribe function.
   * @example const unsub = count.subscribe(v => console.log(v));
   */
  subscribe: (fn: (val: T) => void) => () => void;
}

/**
 * REST client attached to `Lila.api`.
 * Auto-injects Accept: application/json and the CSRF token.
 * Base URL: /api — paths are auto-prefixed.
 * @example
 * await Lila.api.get('/users');
 * await Lila.api.post('/users', { name: 'Alice' });
 * await Lila.api.patch('/users/1', { role: 'Admin' });
 * await Lila.api.delete('/users/42');
 */
export interface ApiClient {
  /**
   * GET request.
   * @example const health = await Lila.api.get('/health');
   */
  get<T = any>(url: string, headers?: Record<string, string>): Promise<T>;
  /**
   * POST request with JSON body.
   * @example await Lila.api.post('/users', { name: 'Bob' });
   */
  post<T = any>(url: string, body?: any, headers?: Record<string, string>): Promise<T>;
  /**
   * PUT request (full replacement).
   * @example await Lila.api.put('/users/1', { name: 'Bob', email: 'b@e.com' });
   */
  put<T = any>(url: string, body?: any, headers?: Record<string, string>): Promise<T>;
  /**
   * PATCH request (partial update).
   * @example await Lila.api.patch('/users/1', { role: 'Developer' });
   */
  patch<T = any>(url: string, body?: any, headers?: Record<string, string>): Promise<T>;
  /**
   * DELETE request.
   * @example await Lila.api.delete('/users/42');
   */
  delete<T = any>(url: string, headers?: Record<string, string>): Promise<T>;
}

/**
 * **Lila.js** global reactive SPA engine.
 * Available as `window.Lila` and `window.App` after loading `/js/lila.js`.
 * @example
 * Lila.route('/', { template: () => `<h1>Home</h1>` });
 * Lila.start('#app');
 */
export interface LilaEngine {
  /**
   * Creates a deep reactive Proxy. Nested mutations auto-notify subscribers.
   * @param initial Plain object or factory function.
   * @example
   * const state = Lila.reactive({ count: 0, items: [] });
   * state.$subscribe((key, val) => console.log(key, val));
   * state.count++;
   */
  reactive<T extends object>(initial: T | (() => T)): T;

  /**
   * Creates a reactive signal for a single value.
   * @param initial Starting value.
   * @returns StateSignal with .get(), .set(), .subscribe().
   * @example
   * const count = Lila.state(0);
   * count.subscribe(n => { el.textContent = n; });
   */
  state<T>(initial: T): StateSignal<T>;

  /**
   * Imperatively mounts a component into a DOM element.
   * @param target CSS selector or HTMLElement.
   * @param config Component configuration.
   * @returns ComponentInstance or null if target not found.
   * @example
   * const w = Lila.mount('#widget', { template: () => '<p>Hello</p>' });
   * w.unmount();
   */
  mount<S = any>(target: string | HTMLElement, config: RouteConfig<S>): ComponentInstance<S> | null;

  /**
   * Registers a client-side hash route.
   * Use '*' as a catch-all 404 route.
   * @param path Hash path: '/', '/users', '/users/detail', '*'
   * @param config Component configuration.
   * @example
   * Lila.route('/users', {
   *   state: () => ({ users: [] }),
   *   template: (s) => Lila.html`<ul>${s.users.map(u => `<li>${u.name}</li>`)}</ul>`,
   *   onMount: async (state) => { state.users = (await Lila.api.get('/users')).data; }
   * });
   */
  route<S = any>(path: string, config: RouteConfig<S>): void;

  /**
   * Starts the hash router. Call after all route() registrations.
   * @param targetSelector CSS selector or HTMLElement for route output.
   * @example Lila.start('#app');
   */
  start(targetSelector: string | HTMLElement): void;

  /**
   * Navigates to a hash route programmatically.
   * @param path Target path, e.g. '/users'.
   * @param replace If true, replaces history entry (no back button).
   * @example
   * Lila.navigate('/users');
   * Lila.navigate('/login', true);
   */
  navigate(path: string, replace?: boolean): void;

  /**
   * Registers an async route guard. Return false to cancel navigation.
   * @param fn Guard function. Return false to block.
   * @example
   * Lila.beforeRoute(async (path) => {
   *   if (!isLoggedIn() && path !== '/login') {
   *     Lila.navigate('/login', true);
   *     return false;
   *   }
   * });
   */
  beforeRoute(fn: (path: string) => boolean | void | Promise<boolean | void>): void;

  /**
   * Creates or retrieves a named global reactive store shared across routes.
   * @param name Unique store id.
   * @param initial Initial state (only used on first creation).
   * @example
   * const auth = Lila.store('auth', { user: null, token: '' });
   * auth.user = { id: 1, name: 'Alice' };
   */
  store<T extends object>(name: string, initial: T): T;

  /**
   * Retrieves a named global store.
   * @param name Store id passed to Lila.store().
   * @returns Reactive state proxy or undefined.
   * @example const auth = Lila.getStore('auth');
   */
  getStore<T extends object>(name: string): T | undefined;

  /**
   * Tagged template for building HTML strings.
   * Arrays are auto-joined; null/undefined are omitted.
   * Does NOT escape — use escapeHtml() for user content.
   * @example
   * Lila.html`<ul>${items.map(i => Lila.html`<li>${Lila.escapeHtml(i)}</li>`)}</ul>`
   */
  html(strings: TemplateStringsArray, ...values: any[]): string;

  /**
   * Escapes a string for safe HTML embedding (prevents XSS).
   * @param text Raw user input string.
   * @returns HTML-safe string.
   * @example const safe = Lila.escapeHtml('<script>alert(1)</script>');
   */
  escapeHtml(text: string | null | undefined): string;

  /**
   * Low-level fetch wrapper. Auto-injects CSRF token and throws structured errors.
   * @param url URL or path (auto-prefixed with /api/ if needed).
   * @param options RequestInit + params for query strings.
   * @returns Parsed JSON body.
   * @throws { error: true, code, message, data } on HTTP error responses.
   * @example
   * const health = await Lila.fetch('/api/health');
   * await Lila.fetch('/api/users/1', { method: 'DELETE' });
   */
  fetch<T = any>(url: string, options?: RequestOptions): Promise<T>;

  /** Alias for Lila.fetch(). @see LilaEngine.fetch */
  http<T = any>(url: string, options?: RequestOptions): Promise<T>;

  /** High-level REST client. @see ApiClient */
  api: ApiClient;

  /**
   * Shows a dismissible toast. Multiple toasts stack automatically.
   * @param options ToastOptions or plain string.
   * @example
   * Lila.toast({ title: 'Saved!', type: 'success' });
   * Lila.toast('Quick message');
   */
  toast(options: ToastOptions | string): void;

  /**
   * Shows a simple bottom snackbar.
   * @param options SnackbarOptions or plain string.
   * @example Lila.snackbar({ message: 'Copied!', type: 'success' });
   */
  snackbar(options: SnackbarOptions | string): void;

  /**
   * Opens, closes, or toggles an HTML dialog or element with .open class.
   * @param id Element id string or HTMLElement reference.
   * @param action 'open' | 'close' | 'toggle'. @default 'toggle'
   * @example Lila.modal('my-dialog', 'open');
   */
  modal(id: string | HTMLElement, action?: "open" | "close" | "toggle"): void;

  /**
   * Opens, closes, or toggles a side drawer by toggling .open class.
   * @param id Drawer element id or reference.
   * @param action 'open' | 'close' | 'toggle'. @default 'toggle'
   * @example Lila.drawer('nav-drawer', 'open');
   */
  drawer(id: string | HTMLElement, action?: "open" | "close" | "toggle"): void;

  /**
   * Activates a tab panel. Deactivates all siblings in the parent .tabs container.
   * @param id Tab content panel id or reference.
   * @example Lila.tab('tab-settings');
   */
  tab(id: string | HTMLElement): void;

  /**
   * Gets, sets, or toggles the color theme on the html element.
   * Persists to localStorage.
   * @param action 'get' | 'set' | 'toggle'. @default 'toggle'
   * @param val Theme name when action is 'set'.
   * @returns Active theme name.
   * @example
   * Lila.theme('get');       // 'dark'
   * Lila.theme('toggle');    // flips dark/light
   * Lila.theme('set','dark');
   */
  theme(action?: "get" | "set" | "toggle", val?: "light" | "dark" | "auto"): string;

  /**
   * Typed querySelector alias.
   * @param selector CSS selector.
   * @param context Root element. @default document
   * @returns First match or null.
   * @example const btn = Lila.$('#submit');
   */
  $(selector: string, context?: Document | HTMLElement): HTMLElement | null;

  /**
   * Typed querySelectorAll alias — returns a plain Array.
   * @param selector CSS selector.
   * @param context Root element. @default document
   * @returns Array of matching elements (empty if none).
   * @example Lila.$$('tr').forEach(r => r.classList.toggle('selected'));
   */
  $$(selector: string, context?: Document | HTMLElement): HTMLElement[];

  /**
   * Runs callback when DOM is ready. Fires synchronously if already loaded.
   * @param fn Callback to execute.
   * @example
   * Lila.ready(() => {
   *   Lila.route('/', { template: () => '<h1>Hello</h1>' });
   *   Lila.start('#app');
   * });
   */
  ready(fn: () => void): void;
}

declare global {
  interface Window {
    Lila: LilaEngine;
    App: LilaEngine;
    toast: (options: ToastOptions | string) => void;
    snackbar: (options: SnackbarOptions | string) => void;
  }
}

export declare const Lila: LilaEngine;
export default Lila;
