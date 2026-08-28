/**
 * # Lila.js — Ambient Type Declarations
 *
 * Ultra-Lightweight Reactive SPA Engine & Bluebird UI Suite for LilaPHP.
 * All globals (`Lila`, `App`, `toast`, `snackbar`) are available in any
 * `<script>` block after loading `/js/lila.js`.
 *
 * @version 3.0.0
 * @license MIT
 * @see https://seip25.github.io/LilaPHP/
 * @module Lila
 */

// ---------------------------------------------------------------------------
// UI Component Interfaces
// ---------------------------------------------------------------------------

/**
 * Options for a toast notification.
 * @example
 * Lila.toast({ title: 'Saved!', description: 'Record updated.', type: 'success' });
 * Lila.toast({ message: 'Error', type: 'error', duration: 6000 });
 * Lila.toast('Quick info');  // shorthand
 */
export interface ToastOptions {
  /** Header title displayed in bold at the top of the toast. */
  title?: string;
  /** Secondary subtitle / description text below the title. */
  description?: string;
  /** Simple single-line message (alternative to title+description). */
  message?: string;
  /** Visual variant that controls color. @default 'info' */
  type?: "info" | "success" | "error" | "warning";
  /** Screen anchor position for the toast container. @default 'bottom-right' */
  position?:
    | "top-left"
    | "top-right"
    | "bottom-left"
    | "bottom-right"
    | "top-center"
    | "bottom-center";
  /** Auto-dismiss delay in ms. Pass 0 to keep open manually. @default 4000 */
  duration?: number;
}

/**
 * Options for a simple snackbar notification.
 * @example
 * Lila.snackbar({ message: 'Saved!', type: 'success', duration: 2500 });
 * Lila.snackbar('Quick message');
 */
export interface SnackbarOptions {
  /** Text displayed inside the snackbar. */
  message?: string;
  /** Visual variant. @default 'info' */
  type?: "info" | "success" | "error" | "warning";
  /** Auto-dismiss delay in ms. @default 3000 */
  duration?: number;
}

/**
 * Context object passed to every route `action` handler.
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
  /** The original DOM Event that triggered the action. */
  event: Event;
  /** The current reactive state proxy for this route/component. */
  state: S;
  /** Value of the element data-id attribute (if present). */
  id?: string;
  /** Value of the element data-nombre attribute (if present). */
  nombre?: string;
  /** Any additional data-* attribute as a key-value pair. */
  [key: string]: any;
}

/**
 * Configuration object passed to `Lila.route()` or `Lila.mount()`.
 * @template S Shape of the reactive state for this route.
 * @example
 * Lila.route('/users', {
 *   state: () => ({ users: [], loading: true }),
 *   template: (s) => Lila.html`
 *     <ul>${s.users.map(u => `<li>${Lila.escapeHtml(u.name)}</li>`)}</ul>
 *   `,
 *   onMount: async (state) => {
 *     const res   = await Lila.api.get('/users');
 *     state.users   = res.data ?? [];
 *     state.loading = false;
 *   },
 *   actions: {
 *     deleteUser: async (ctx) => {
 *       await Lila.fetch(`/api/users/${ctx.id}`, { method: 'DELETE' });
 *       ctx.state.users = ctx.state.users.filter(u => u.id != ctx.id);
 *     }
 *   }
 * });
 */
export interface RouteConfig<S = any> {
  /**
   * Initial state factory or plain object.
   * Factory `() => ({})` guarantees a fresh object per route activation.
   */
  state?: (() => S) | S;
  /**
   * Pure function returning an HTML string. Called on every state change
   * (batched via `requestAnimationFrame`).
   */
  template?: (state: S) => string;
  /**
   * Called once after the component mounts. Can return a cleanup function.
   * Ideal for data fetching and side effects.
   * @example
   * onMount: async (state) => {
   *   const res = await Lila.api.get('/users');
   *   state.users = res.data ?? [];
   *   const timer = setInterval(() => state.count++, 1000);
   *   return () => clearInterval(timer); // cleanup on unmount
   * }
   */
  onMount?: (state: S) => void | (() => void) | Promise<void | (() => void)>;
  /**
   * Called before the component unmounts.
   * Use it to cancel timers or subscriptions.
   */
  onDestroy?: (state: S) => void;
  /**
   * Map of action names to handler functions.
   * Triggered by `data-action="name"` on DOM elements.
   * Forms receive submit events; all other elements receive click events.
   * @example
   * // In template:   `<button data-action="save" data-id="${u.id}">Save</button>`
   * // In actions:    save: (ctx) => console.log('saving', ctx.id)
   */
  actions?: Record<string, (context: ActionContext<S>) => void | Promise<void>>;
}

/**
 * Instance returned by `Lila.mount()`.
 * @template S Shape of the component reactive state.
 */
export interface ComponentInstance<S = any> {
  /** The live reactive state proxy. Mutate to trigger re-renders. */
  state: S;
  /**
   * Unmounts the component: unsubscribes reactivity, calls onDestroy, clears DOM.
   * @example instance.unmount();
   */
  unmount: () => void;
}

/**
 * Standard JSON envelope returned by LilaPHP REST endpoints.
 * @template T Shape of the data payload.
 * @example
 * const res = await Lila.api.get<{ data: User[] }>('/users');
 * const users = res.data ?? [];
 */
export interface ApiResponse<T = any> {
  /** 'ok' on success, 'error' on failure. */
  status?: string;
  /** true when the server returned an error response. */
  error?: boolean;
  /** HTTP status code mirrored in the JSON body. */
  code?: number;
  /** Human-readable error or info message. */
  message?: string;
  /** Actual data payload typed by T. */
  data?: T;
  [key: string]: any;
}

/**
 * Extends native RequestInit with a query-string params helper.
 * @example
 * Lila.fetch('/api/users', { params: { page: 1, limit: 20 } });
 */
export interface RequestOptions extends RequestInit {
  /**
   * Key-value pairs appended as URL query parameters.
   * @example { page: 2, search: 'alice' }  becomes  ?page=2&search=alice
   */
  params?: Record<string, string | number | boolean>;
}

/**
 * Reactive primitive signal wrapping a single value of type T.
 * @template T Type of the value being tracked.
 * @example
 * const counter = Lila.state(0);
 * counter.subscribe(val => { el.textContent = val; });
 * counter.set(n => n + 1);  // increment
 * counter.set(0);            // reset
 * const n = counter.get();   // read current value
 */
export interface StateSignal<T> {
  /**
   * Returns the current value of the signal.
   * @returns Current value of type T.
   * @example const n = counter.get(); // 42
   */
  get: () => T;
  /**
   * Sets a new value or derives it from the previous one.
   * @param val New value, or updater function `(prev: T) => T`.
   * @example
   * counter.set(10);
   * counter.set(prev => prev + 1);
   */
  set: (val: T | ((prev: T) => T)) => void;
  /**
   * Subscribes to value changes. Fires immediately with the current value.
   * @param fn Callback receiving the new value on every change.
   * @returns Unsubscribe function — call it to stop listening.
   * @example
   * const unsub = counter.subscribe(val => console.log(val));
   * unsub(); // stop
   */
  subscribe: (fn: (val: T) => void) => () => void;
}

/**
 * High-level REST client attached to `Lila.api`.
 * All requests auto-inject `Accept: application/json` and the CSRF token.
 * Base URL: `/api` — paths are auto-prefixed.
 * @example
 * const users = await Lila.api.get('/users');
 * await Lila.api.post('/users', { name: 'Alice', email: 'a@example.com' });
 * await Lila.api.patch('/users/1', { role: 'Admin' });
 * await Lila.api.delete('/users/42');
 */
export interface ApiClient {
  /**
   * Sends a GET request.
   * @param url Endpoint path, e.g. '/users' or '/users/42'.
   * @param headers Optional extra request headers.
   * @returns Parsed JSON response.
   * @example const health = await Lila.api.get('/health');
   */
  get<T = any>(url: string, headers?: Record<string, string>): Promise<T>;

  /**
   * Sends a POST request with a JSON body.
   * @param url Endpoint path.
   * @param body Data to send as JSON. Also accepts FormData.
   * @param headers Optional extra request headers.
   * @returns Parsed JSON response.
   * @example
   * await Lila.api.post('/users', { name: 'Bob', role: 'Admin' });
   */
  post<T = any>(url: string, body?: any, headers?: Record<string, string>): Promise<T>;

  /**
   * Sends a PUT request (full resource replacement).
   * @param url Endpoint path, typically includes the resource ID.
   * @param body Full updated resource object.
   * @returns Parsed JSON response.
   * @example await Lila.api.put('/users/1', { name: 'Bob', email: 'b@e.com' });
   */
  put<T = any>(url: string, body?: any, headers?: Record<string, string>): Promise<T>;

  /**
   * Sends a PATCH request (partial resource update).
   * @param url Endpoint path, typically includes the resource ID.
   * @param body Partial fields to update.
   * @returns Parsed JSON response.
   * @example await Lila.api.patch('/users/1', { role: 'Developer' });
   */
  patch<T = any>(url: string, body?: any, headers?: Record<string, string>): Promise<T>;

  /**
   * Sends a DELETE request.
   * @param url Endpoint path including the resource ID.
   * @returns Parsed JSON response, typically { success: true }.
   * @example await Lila.api.delete('/users/42');
   */
  delete<T = any>(url: string, headers?: Record<string, string>): Promise<T>;
}

// ---------------------------------------------------------------------------
// Main Engine Interface
// ---------------------------------------------------------------------------

/**
 * **Lila.js** global reactive SPA engine.
 *
 * Available as `window.Lila` and `window.App` after loading `/js/lila.js`.
 * @example
 * Lila.route('/', { template: () => `<h1>Home</h1>` });
 * Lila.route('/about', { template: () => `<h1>About</h1>` });
 * Lila.start('#app');
 */
export interface LilaEngine {

  // ── Reactive Core ─────────────────────────────────────────────────────────

  /**
   * Creates a deep reactive Proxy from a plain object or factory.
   * Nested property mutations and array mutations auto-notify subscribers.
   * @param initial Plain object, array, or factory function returning one.
   * @returns A reactive proxy of the same shape as initial.
   * @example
   * const state = Lila.reactive({ count: 0, items: [] });
   * state.$subscribe((key, val) => console.log(key, 'changed to', val));
   * state.count++;        // triggers subscriber
   * state.items.push(1);  // triggers subscriber
   */
  reactive<T extends object>(initial: T | (() => T)): T;

  /**
   * Creates a lightweight reactive signal for a single value.
   * Simpler than `reactive()` — ideal for counters, toggles, and selected IDs.
   * @param initial Starting value.
   * @returns StateSignal with .get(), .set(), and .subscribe().
   * @example
   * const count = Lila.state(0);
   * count.subscribe(n => { el.textContent = n; });
   * count.set(n => n + 1);
   */
  state<T>(initial: T): StateSignal<T>;

  /**
   * Mounts a component into a DOM element imperatively (outside the router).
   * @param target CSS selector string or HTMLElement reference.
   * @param config Component configuration (same shape as Lila.route()).
   * @returns ComponentInstance with .state and .unmount(), or null if target not found.
   * @example
   * const widget = Lila.mount('#sidebar', {
   *   state: () => ({ open: false }),
   *   template: (s) => `<div class="${s.open ? 'open' : ''}">...</div>`,
   * });
   * widget.state.open = true; // triggers re-render
   * widget.unmount();
   */
  mount<S = any>(target: string | HTMLElement, config: RouteConfig<S>): ComponentInstance<S> | null;

  /**
   * Registers a client-side hash route (#/path).
   * Routes are matched in registration order. Use '*' as a catch-all 404 route.
   * @param path Hash path to match, e.g. '/', '/users', '*'.
   * @param config Component configuration with state, template, onMount, actions.
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
   *   }
   * });
   */
  route<S = any>(path: string, config: RouteConfig<S>): void;

  /**
   * Starts the hash router attached to the given outlet element.
   * Must be called after all Lila.route() registrations.
   * @param targetSelector CSS selector or HTMLElement that hosts route output.
   * @example
   * Lila.route('/', { template: () => `<h1>Home</h1>` });
   * Lila.start('#app');
   */
  start(targetSelector: string | HTMLElement): void;

  /**
   * Programmatically navigates to a hash route.
   * @param path Hash path, e.g. '/users' or '/login'.
   * @param replace If true, replaces the current history entry instead of pushing a new one.
   * @example
   * Lila.navigate('/users');
   * Lila.navigate('/login', true); // replace history (no back button)
   */
  navigate(path: string, replace?: boolean): void;

  /**
   * Registers an async route guard that runs before every navigation.
   * Return false (or navigate away) to cancel.
   * @param fn Guard receiving the target path. Return false to block navigation.
   * @example
   * Lila.beforeRoute(async (path) => {
   *   const ok = await checkSession();
   *   if (!ok && path !== '/login') {
   *     Lila.navigate('/login', true);
   *     return false;
   *   }
   * });
   */
  beforeRoute(fn: (path: string) => boolean | void | Promise<boolean | void>): void;

  /**
   * Creates or retrieves a named global reactive store — shared across all routes.
   * @param name Unique store identifier string.
   * @param initial Initial state (used only on first creation).
   * @returns The reactive state proxy for the named store.
   * @example
   * const auth = Lila.store('auth', { user: null, token: '' });
   * auth.user = { id: 1, name: 'Alice' };
   */
  store<T extends object>(name: string, initial: T): T;

  /**
   * Retrieves a previously created global store by name.
   * @param name Store identifier used in Lila.store().
   * @returns The reactive state proxy, or undefined if not found.
   * @example
   * const auth = Lila.getStore('auth');
   * if (auth?.user) console.log('Logged in as', auth.user.name);
   */
  getStore<T extends object>(name: string): T | undefined;

  // ── Templating ────────────────────────────────────────────────────────────

  /**
   * Tagged template literal helper for building HTML strings.
   * Arrays are auto-joined with ''; null/undefined values are silently omitted.
   * Does NOT escape values — use Lila.escapeHtml() for user-supplied content.
   * @param strings Template string parts (injected automatically by tagged-template syntax).
   * @param values Interpolated values: strings, numbers, or arrays of strings.
   * @returns Concatenated HTML string.
   * @example
   * template: (s) => Lila.html`
   *   <ul>
   *     ${s.users.map(u => Lila.html`<li>${Lila.escapeHtml(u.name)}</li>`)}
   *   </ul>
   * `
   */
  html(strings: TemplateStringsArray, ...values: any[]): string;

  /**
   * Escapes a string for safe HTML embedding, preventing XSS.
   * Converts &, <, >, ", ' to HTML entities.
   * @param text Raw user-supplied string (null/undefined returns empty string).
   * @returns HTML-safe string.
   * @example
   * const safe = Lila.escapeHtml(user.name); // "<script>" becomes "&lt;script&gt;"
   * return Lila.html`<p>${safe}</p>`;
   */
  escapeHtml(text: string | null | undefined): string;

  // ── HTTP Client ───────────────────────────────────────────────────────────

  /**
   * Low-level fetch wrapper with auto CSRF injection and structured error throwing.
   * Paths not starting with 'http' or '/' are auto-prefixed with '/api/'.
   * @param url Absolute URL or relative path.
   * @param options Standard RequestInit extended with params for query strings.
   * @returns Parsed JSON response body.
   * @throws { error: true, code: number, message: string, data: any } on HTTP errors.
   * @example
   * const health = await Lila.fetch('/api/health');
   * await Lila.fetch('/api/users/1', { method: 'DELETE' });
   * await Lila.fetch('/api/users', {
   *   method: 'POST',
   *   body: JSON.stringify({ name: 'Alice' })
   * });
   */
  fetch<T = any>(url: string, options?: RequestOptions): Promise<T>;

  /**
   * Alias for Lila.fetch(). Identical behavior.
   * @see LilaEngine.fetch
   * @example const data = await Lila.http('/api/health');
   */
  http<T = any>(url: string, options?: RequestOptions): Promise<T>;

  /**
   * High-level REST client with dedicated methods per HTTP verb.
   * Auto-prefixes paths with /api. Auto-injects CSRF token.
   * @example
   * const users = await Lila.api.get('/users');
   * await Lila.api.post('/users', { name: 'Alice' });
   * await Lila.api.patch('/users/1', { role: 'Admin' });
   * await Lila.api.delete('/users/1');
   */
  api: ApiClient;

  // ── Bluebird UI Suite ─────────────────────────────────────────────────────

  /**
   * Shows a dismissible toast notification. Multiple toasts stack automatically.
   * @param options Configuration object or plain string shorthand.
   * @example
   * Lila.toast({ title: 'Saved!', type: 'success', duration: 3000 });
   * Lila.toast({ title: 'Error', description: 'Something failed.', type: 'error' });
   * Lila.toast('Quick message');
   */
  toast(options: ToastOptions | string): void;

  /**
   * Shows a simple snackbar at the bottom of the screen.
   * @param options Configuration object or plain string.
   * @example
   * Lila.snackbar({ message: 'Copied!', type: 'success' });
   * Lila.snackbar('Done');
   */
  snackbar(options: SnackbarOptions | string): void;

  /**
   * Opens, closes, or toggles an HTML dialog element or any element with .open/.show classes.
   * @param id Element id string or direct HTMLElement reference.
   * @param action 'open' | 'close' | 'toggle'. @default 'toggle'
   * @example
   * Lila.modal('confirm-dialog', 'open');
   * Lila.modal('confirm-dialog', 'close');
   * // In HTML: <dialog id="confirm-dialog">...</dialog>
   */
  modal(id: string | HTMLElement, action?: "open" | "close" | "toggle"): void;

  /**
   * Opens, closes, or toggles a side drawer by toggling the .open class.
   * @param id Drawer element id or direct reference.
   * @param action 'open' | 'close' | 'toggle'. @default 'toggle'
   * @example
   * Lila.drawer('nav-drawer', 'open');
   * Lila.drawer('nav-drawer', 'close');
   */
  drawer(id: string | HTMLElement, action?: "open" | "close" | "toggle"): void;

  /**
   * Activates a tab panel by id. Deactivates all sibling .tab-content elements
   * and removes .active from all .tab-trigger elements in the parent .tabs container.
   * @param id The id of the tab content panel to activate.
   * @example
   * Lila.tab('tab-settings');
   * // HTML: <div class="tabs">
   * //   <button class="tab-trigger" onclick="Lila.tab('tab-home')">Home</button>
   * //   <div id="tab-home" class="tab-content active">...</div>
   * // </div>
   */
  tab(id: string | HTMLElement): void;

  /**
   * Gets, sets, or toggles the current color theme via data-theme on html element.
   * Persists the selection to localStorage.
   * @param action 'get' returns current theme. 'set' applies val. 'toggle' flips dark/light.
   * @param val Target theme when action is 'set'.
   * @returns The active theme name ('dark' or 'light').
   * @example
   * const current = Lila.theme('get');   // 'dark'
   * Lila.theme('toggle');                 // switches to 'light'
   * Lila.theme('set', 'dark');            // forces dark mode
   */
  theme(action?: "get" | "set" | "toggle", val?: "light" | "dark" | "auto"): string;

  // ── DOM Helpers ───────────────────────────────────────────────────────────

  /**
   * Typed alias for document.querySelector().
   * @param selector Any valid CSS selector string.
   * @param context Optional root element to search within. @default document
   * @returns First matching HTMLElement, or null if not found.
   * @example
   * const btn   = Lila.$('#submit-btn');
   * const input = Lila.$('input[name="email"]', form);
   */
  $(selector: string, context?: Document | HTMLElement): HTMLElement | null;

  /**
   * Typed alias for document.querySelectorAll() — returns a plain Array.
   * @param selector Any valid CSS selector string.
   * @param context Optional root element to search within. @default document
   * @returns Array of matching HTMLElements (empty array if none found).
   * @example
   * const rows = Lila.$$('tbody tr');
   * rows.forEach(row => row.classList.toggle('selected'));
   */
  $$(selector: string, context?: Document | HTMLElement): HTMLElement[];

  /**
   * Runs the callback when the DOM is fully loaded.
   * If the DOM is already ready, the callback fires synchronously.
   * @param fn Callback to execute on DOM ready.
   * @example
   * Lila.ready(() => {
   *   Lila.route('/', { template: () => '<h1>Hello</h1>' });
   *   Lila.start('#app');
   * });
   */
  ready(fn: () => void): void;
}

// ---------------------------------------------------------------------------
// Global Declarations
// ---------------------------------------------------------------------------

declare global {
  interface Window {
    /** Global Lila.js SPA engine instance. */
    Lila: LilaEngine;
    /** Alias for window.Lila. */
    App: LilaEngine;
    /** Shorthand for Lila.toast(). */
    toast: (options: ToastOptions | string) => void;
    /** Shorthand for Lila.snackbar(). */
    snackbar: (options: SnackbarOptions | string) => void;
  }
  /** Global Lila.js SPA engine. Available after loading /js/lila.js. */
  const Lila: LilaEngine;
  /** Alias for Lila. */
  const App: LilaEngine;
  /** Shorthand for Lila.toast(). */
  const toast: (options: ToastOptions | string) => void;
  /** Shorthand for Lila.snackbar(). */
  const snackbar: (options: SnackbarOptions | string) => void;
}

export const Lila: LilaEngine;
export default Lila;
