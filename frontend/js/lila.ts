/**
 * Lila.js — TypeScript Source & Complete Type Definitions
 * Unified Reactive SPA Engine, HTTP API Client & Bluebird UI Suite for LilaPHP
 * 
 * @module Lila
 */

export interface ToastOptions {
  title?: string;
  description?: string;
  message?: string;
  type?: "info" | "success" | "error" | "warning";
  position?:
    | "top-left"
    | "top-right"
    | "bottom-left"
    | "bottom-right"
    | "top-center"
    | "bottom-center";
  duration?: number;
}

export interface SnackbarOptions {
  message?: string;
  type?: "info" | "success" | "error" | "warning";
  duration?: number;
}

export interface ActionContext<S = any> {
  event: Event;
  state: S;
  id?: string;
  nombre?: string;
  [key: string]: any;
}

export interface RouteConfig<S = any> {
  state?: (() => S) | S;
  template?: (state: S) => string;
  onMount?: (state: S) => void | (() => void) | Promise<void | (() => void)>;
  onDestroy?: (state: S) => void;
  actions?: Record<string, (context: ActionContext<S>) => void | Promise<void>>;
}

export interface ComponentInstance<S = any> {
  state: S;
  unmount: () => void;
}

export interface ApiResponse<T = any> {
  status?: string;
  error?: boolean;
  code?: number;
  message?: string;
  data?: T;
  [key: string]: any;
}

export interface RequestOptions extends RequestInit {
  params?: Record<string, string | number | boolean>;
}

export interface StateSignal<T> {
  get: () => T;
  set: (val: T | ((prev: T) => T)) => void;
  subscribe: (fn: (val: T) => void) => () => void;
}

export interface ApiClient {
  get<T = any>(url: string, headers?: Record<string, string>): Promise<T>;
  post<T = any>(url: string, body?: any, headers?: Record<string, string>): Promise<T>;
  put<T = any>(url: string, body?: any, headers?: Record<string, string>): Promise<T>;
  patch<T = any>(url: string, body?: any, headers?: Record<string, string>): Promise<T>;
  delete<T = any>(url: string, headers?: Record<string, string>): Promise<T>;
}

export interface LilaEngine {
  // SPA & Reactive Core
  reactive<T extends object>(initial: T | (() => T)): T;
  state<T>(initial: T): StateSignal<T>;
  mount<S = any>(target: string | HTMLElement, config: RouteConfig<S>): ComponentInstance<S> | null;
  route<S = any>(path: string, config: RouteConfig<S>): void;
  start(targetSelector: string | HTMLElement): void;
  navigate(path: string, replace?: boolean): void;
  beforeRoute(fn: (path: string) => boolean | Promise<boolean>): void;
  store<T extends object>(name: string, initial: T): T;
  getStore<T extends object>(name: string): T | undefined;
  html(strings: TemplateStringsArray, ...values: any[]): string;
  escapeHtml(text: string | null | undefined): string;

  // HTTP API Client
  fetch<T = any>(url: string, options?: RequestOptions): Promise<T>;
  http<T = any>(url: string, options?: RequestOptions): Promise<T>;
  api: ApiClient;

  // Bluebird UI Suite
  toast(options: ToastOptions | string): void;
  snackbar(options: SnackbarOptions | string): void;
  modal(id: string | HTMLElement, action?: "open" | "close" | "toggle"): void;
  drawer(id: string | HTMLElement, action?: "open" | "close" | "toggle"): void;
  tab(id: string | HTMLElement): void;
  theme(action?: "get" | "set" | "toggle", val?: "light" | "dark" | "auto"): string;

  // DOM Helpers
  $(selector: string, context?: Document | HTMLElement): HTMLElement | null;
  $$(selector: string, context?: Document | HTMLElement): HTMLElement[];
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

export const Lila: LilaEngine = (typeof window !== "undefined" ? window.Lila : {}) as LilaEngine;
export default Lila;
