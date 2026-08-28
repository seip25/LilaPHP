/**
 * Lila.js — Ultra-Lightweight Reactive SPA Engine & Bluebird UI Suite for LilaPHP
 * @version 3.0.0
 * @license MIT
 * @see https://seip25.github.io/LilaPHP/
 *
 * Types & IntelliSense: frontend/js/lila.d.ts (loaded globally via jsconfig.json)
 * Globals available: window.Lila, window.App, window.toast, window.snackbar
 */
(function () {
    "use strict";

    var stores = new Map();
    var routes = [];
    var instances = new Set();
    var outlet = null;
    var currentPath = null;
    var routeInstance = null;
    var stylesReady = false;
    var beforeRouteHook = null;
    var deferredPrompt = null;

    function injectStyles() {
        if (stylesReady) return;
        stylesReady = true;
        var s = document.createElement("style");
        s.id = "lila-css";
        s.textContent =
            ".lila-enter{opacity:0;transform:translateY(6px)}" +
            ".lila-active{transition:opacity .15s ease-out,transform .15s ease-out}" +
            ".lila-leave{opacity:0;transition:opacity .1s ease-in}" +
            ".lila-skeleton{background:linear-gradient(90deg,#e2e8f0 25%,#f1f5f9 50%,#e2e8f0 75%);" +
            "background-size:200% 100%;animation:lila-pulse 1.5s infinite;border-radius:.5rem;min-height:120px}" +
            ".dark .lila-skeleton,[data-theme=dark] .lila-skeleton{" +
            "background:linear-gradient(90deg,#1e293b 25%,#334155 50%,#1e293b 75%);background-size:200% 100%}" +
            "@keyframes lila-pulse{0%{background-position:200% 0}to{background-position:-200% 0}}";
        document.head.appendChild(s);
    }

    function parseHash() {
        var raw = location.hash.slice(1) || "/";
        var i = raw.indexOf("?");
        if (i < 0) return { path: raw, query: {} };
        var q = {};
        raw
            .slice(i + 1)
            .split("&")
            .forEach(function (p) {
                var kv = p.split("=");
                if (kv[0])
                    q[decodeURIComponent(kv[0])] = decodeURIComponent(kv[1] || "");
            });
        return { path: raw.slice(0, i) || "/", query: q };
    }

    function isPlain(v) {
        return (
            v !== null &&
            typeof v === "object" &&
            (Array.isArray(v) || Object.getPrototypeOf(v) === Object.prototype)
        );
    }

    function reactive(initial) {
        var subs = new Set();
        var raw =
            typeof initial === "function" ? initial() : Object.assign({}, initial);

        function notify(key, value) {
            subs.forEach(function (fn) {
                fn(key, value);
            });
        }

        function proxyChild(value, parentKey) {
            if (!isPlain(value)) return value;
            return new Proxy(value, {
                get: function (t, k) {
                    var v = t[k];
                    if (
                        Array.isArray(t) &&
                        typeof v === "function" &&
                        [
                            "push",
                            "pop",
                            "shift",
                            "unshift",
                            "splice",
                            "sort",
                            "reverse",
                            "fill",
                        ].indexOf(k) >= 0
                    ) {
                        return function () {
                            var result = v.apply(t, arguments);
                            notify(parentKey, t);
                            return result;
                        };
                    }
                    if (isPlain(v)) return proxyChild(v, parentKey);
                    return v;
                },
                set: function (t, k, v) {
                    t[k] = v;
                    notify(parentKey, t);
                    return true;
                },
            });
        }

        return new Proxy(raw, {
            get: function (t, k) {
                if (k === "$subscribe")
                    return function (fn) {
                        subs.add(fn);
                        return function () {
                            subs.delete(fn);
                        };
                    };
                if (k === "$raw") return t;
                var v = t[k];
                if (isPlain(v)) return proxyChild(v, k);
                return v;
            },
            set: function (t, k, v) {
                if (t[k] === v) return true;
                t[k] = v;
                notify(k, v);
                return true;
            },
        });
    }

    function html(strings) {
        var args = Array.prototype.slice.call(arguments, 1);
        var res = "";
        for (var i = 0; i < strings.length; i++) {
            res += strings[i];
            if (i < args.length) {
                var v = args[i];
                res += Array.isArray(v) ? v.join("") : v != null ? v : "";
            }
        }
        return res;
    }

    function escapeHtml(text) {
        if (!text) return "";
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function morph(node, newHTML) {
        if (!node) return;
        var t = document.createElement("template");
        t.innerHTML = (newHTML || "").trim();

        function walk(oldNode, newNode) {
            if (!oldNode || !newNode) return;
            if (
                oldNode.nodeType !== newNode.nodeType ||
                oldNode.nodeName !== newNode.nodeName
            ) {
                if (oldNode.parentNode) {
                    oldNode.parentNode.replaceChild(newNode.cloneNode(true), oldNode);
                }
                return;
            }
            if (newNode.nodeType === 3) {
                if (oldNode.nodeValue !== newNode.nodeValue) {
                    oldNode.nodeValue = newNode.nodeValue;
                }
                return;
            }
            if (newNode.nodeType === 1) {
                var oldAttrs = oldNode.attributes;
                var newAttrs = newNode.attributes;
                var i;
                if (oldAttrs) {
                    for (i = oldAttrs.length - 1; i >= 0; i--) {
                        var attrName = oldAttrs[i].name;
                        if (!newNode.hasAttribute(attrName)) {
                            oldNode.removeAttribute(attrName);
                        }
                    }
                }
                if (newAttrs) {
                    for (i = 0; i < newAttrs.length; i++) {
                        var attr = newAttrs[i];
                        if (oldNode.getAttribute(attr.name) !== attr.value) {
                            if (attr.name === "value" && oldNode.tagName === "INPUT") {
                                if (document.activeElement !== oldNode) {
                                    oldNode.value = attr.value;
                                }
                            } else if (attr.name === "checked" && oldNode.tagName === "INPUT") {
                                oldNode.checked = true;
                            } else {
                                oldNode.setAttribute(attr.name, attr.value);
                            }
                        }
                    }
                }
                if (
                    !newNode.hasAttribute("checked") &&
                    oldNode.tagName === "INPUT" &&
                    oldNode.type === "checkbox"
                ) {
                    oldNode.checked = false;
                }

                var oldChildren = Array.from(oldNode.childNodes);
                var newChildren = Array.from(newNode.childNodes);
                var max = Math.max(oldChildren.length, newChildren.length);
                for (var j = 0; j < max; j++) {
                    if (oldChildren[j] && newChildren[j]) {
                        walk(oldChildren[j], newChildren[j]);
                    } else if (newChildren[j]) {
                        oldNode.appendChild(newChildren[j].cloneNode(true));
                    } else if (oldChildren[j]) {
                        oldNode.removeChild(oldChildren[j]);
                    }
                }
            }
        }

        var tempNode = document.createElement(node.tagName);
        tempNode.appendChild(t.content);

        var oldChild = Array.from(node.childNodes);
        var newChild = Array.from(tempNode.childNodes);
        var max = Math.max(oldChild.length, newChild.length);
        for (var i = 0; i < max; i++) {
            if (oldChild[i] && newChild[i]) walk(oldChild[i], newChild[i]);
            else if (newChild[i]) node.appendChild(newChild[i].cloneNode(true));
            else if (oldChild[i]) node.removeChild(oldChild[i]);
        }
    }

    function mount(target, config) {
        injectStyles();
        var el =
            typeof target === "string" ? document.querySelector(target) : target;
        if (!el) return null;

        var def = {
            template: config.template || null,
            actions: config.actions || {},
            onMount: config.onMount || null,
            onDestroy: config.onDestroy || null,
        };

        var initialState =
            typeof config.state === "function" ? config.state() : config.state || {};
        var state = reactive(initialState);
        var renderQueued = false;
        var mountCleanup = null;
        var pendingChanges = new Set();
        var templateFn =
            typeof def.template === "function"
                ? def.template
                : function () {
                      return "";
                  };

        // Event delegation for actions
        if (!el._lilaDelegated) {
            el._lilaDelegated = true;

            el.addEventListener("click", function (e) {
                var actionEl = e.target.closest("[data-action]");
                if (!actionEl || actionEl.tagName === "FORM") return;
                var actionName = actionEl.getAttribute("data-action");
                var fn = def.actions[actionName];
                if (fn) {
                    fn({
                        event: e,
                        state: state,
                        id: actionEl.dataset.id,
                        name: actionEl.dataset.name,
                        nombre: actionEl.dataset.nombre,
                        ...actionEl.dataset
                    });
                }
            });

            el.addEventListener("submit", function (e) {
                var formEl = e.target.closest("[data-action]");
                if (!formEl || formEl.tagName !== "FORM") return;
                e.preventDefault();
                var actionName = formEl.getAttribute("data-action");
                var fn = def.actions[actionName];
                if (fn) {
                    fn({
                        event: e,
                        state: state,
                        id: formEl.dataset.id,
                        ...formEl.dataset
                    });
                }
            });
        }

        function render() {
            var rendered = templateFn(state);
            morph(el, rendered);
            pendingChanges.clear();
            renderQueued = false;
        }

        function queueRender(key) {
            pendingChanges.add(key);
            if (renderQueued) return;
            renderQueued = true;
            requestAnimationFrame(render);
        }

        var unsub = state.$subscribe(function (key) {
            queueRender(key);
        });

        render();

        if (def.onMount) {
            var ret = def.onMount(state);
            if (typeof ret === "function") mountCleanup = ret;
        }

        var instance = {
            state: state,
            unmount: function () {
                unsub();
                if (mountCleanup) mountCleanup();
                if (def.onDestroy) def.onDestroy(state);
                el.innerHTML = "";
                instances.delete(instance);
            },
        };

        instances.add(instance);
        return instance;
    }

    function route(path, config) {
        routes.push({ path: path, config: config });
    }

    function setBeforeRoute(fn) {
        beforeRouteHook = fn;
    }

    async function handleRoute() {
        if (!outlet) return;
        var parsed = parseHash();
        var path = parsed.path;

        if (beforeRouteHook) {
            var allowed = await beforeRouteHook(path);
            if (allowed === false) return;
        }

        if (currentPath === path && routeInstance) return;
        currentPath = path;

        var matched = routes.find(function (r) {
            return r.path === path;
        });
        if (!matched) {
            matched = routes.find(function (r) {
                return r.path === "*";
            });
        }

        if (routeInstance) {
            routeInstance.unmount();
            routeInstance = null;
        }

        if (matched) {
            routeInstance = mount(outlet, matched.config);
        }
    }

    function navigate(path, replace) {
        var cleanPath = path ? (path.startsWith("#") ? path.slice(1) : path) : "/";
        if (!cleanPath.startsWith("/")) cleanPath = "/" + cleanPath;
        if (replace) {
            location.replace("#" + cleanPath);
        } else {
            location.hash = "#" + cleanPath;
        }
    }

    function start(targetSelector) {
        outlet =
            typeof targetSelector === "string"
                ? document.querySelector(targetSelector)
                : targetSelector;
        window.addEventListener("hashchange", handleRoute);
        handleRoute();
    }

    function store(name, initial) {
        var s = reactive(initial);
        stores.set(name, s);
        return s;
    }

    function getStore(name) {
        return stores.get(name);
    }

    // --------------------------------------------------------------------------
    // HTTP API Client with Auto-CSRF & Error Handling
    // --------------------------------------------------------------------------
    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;
        var hidden = document.querySelector('input[name="_csrf_token"], input[name="csrf"]');
        if (hidden && hidden.value) return hidden.value;
        var match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : "";
    }

    async function request(url, options) {
        options = options || {};
        var method = (options.method || "GET").toUpperCase();
        var headers = Object.assign({ Accept: "application/json" }, options.headers || {});
        var csrf = getCsrfToken();
        if (csrf && !headers["X-CSRF-TOKEN"]) {
            headers["X-CSRF-TOKEN"] = csrf;
        }

        var fetchUrl = url;
        if (!fetchUrl.startsWith("http") && !fetchUrl.startsWith("/")) {
            fetchUrl = "/api/" + fetchUrl;
        }

        var fetchOptions = {
            method: method,
            headers: headers,
            credentials: options.credentials || "include",
        };

        if (options.body) {
            if (options.body instanceof FormData) {
                fetchOptions.body = options.body;
            } else if (typeof options.body === "object") {
                headers["Content-Type"] = "application/json";
                fetchOptions.body = JSON.stringify(options.body);
            } else {
                fetchOptions.body = options.body;
            }
        }

        var res = await fetch(fetchUrl, fetchOptions);
        var data;
        var contentType = res.headers.get("content-type") || "";
        if (contentType.includes("application/json")) {
            data = await res.json().catch(function () {
                return { status: res.ok ? "ok" : "error", code: res.status };
            });
        } else {
            data = await res.text();
        }

        if (!res.ok) {
            var errMsg = (data && data.message) || `HTTP Error ${res.status}: ${res.statusText}`;
            var err = new Error(errMsg);
            err.code = res.status;
            err.data = data;
            throw err;
        }

        return data;
    }

    var api = {
        get: function (url, headers) {
            return request(url, { method: "GET", headers: headers });
        },
        post: function (url, body, headers) {
            return request(url, { method: "POST", body: body, headers: headers });
        },
        put: function (url, body, headers) {
            return request(url, { method: "PUT", body: body, headers: headers });
        },
        patch: function (url, body, headers) {
            return request(url, { method: "PATCH", body: body, headers: headers });
        },
        delete: function (url, headers) {
            return request(url, { method: "DELETE", headers: headers });
        },
    };

    // --------------------------------------------------------------------------
    // Bluebird UI Component Suite Integration
    // --------------------------------------------------------------------------
    function dismissToast(el) {
        if (!el) return;
        el.classList.add("toast-leaving");
        setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 300);
    }

    function toast(options) {
        if (typeof options === "string") {
            options = { message: options };
        }
        options = options || {};
        var position = options.position || "bottom-right";
        var container = document.querySelector(".toast-container." + position);

        if (!container) {
            container = document.createElement("div");
            container.className = "toast-container " + position;
            document.body.appendChild(container);
        }

        var toastEl = document.createElement("div");
        var typeClass = options.type ? "toast-" + options.type : "toast-info";
        toastEl.className = "toast " + typeClass;

        var titleHtml = options.title
            ? `<div class="toast-title">${escapeHtml(options.title)}</div>`
            : "";
        var descHtml = options.description
            ? `<div class="toast-description">${escapeHtml(options.description)}</div>`
            : "";
        var msgHtml = options.message ? `<div>${escapeHtml(options.message)}</div>` : "";

        toastEl.innerHTML = `
            <div class="toast-content">
                ${titleHtml}
                ${descHtml}
                ${msgHtml}
            </div>
            <button class="toast-close" aria-label="Dismiss">&times;</button>
        `;

        var closeBtn = toastEl.querySelector(".toast-close");
        if (closeBtn) {
            closeBtn.onclick = function () {
                dismissToast(toastEl);
            };
        }

        container.appendChild(toastEl);

        var duration = options.duration !== undefined ? options.duration : 4000;
        if (duration > 0) {
            setTimeout(function () {
                dismissToast(toastEl);
            }, duration);
        }
    }

    function snackbar(options) {
        if (typeof options === "string") options = { message: options };
        options = options || {};
        var el = document.getElementById("snackbar");
        if (!el) {
            el = document.createElement("div");
            el.id = "snackbar";
            document.body.appendChild(el);
        }
        el.className = "show " + (options.type || "info");
        el.textContent = options.message || "";
        setTimeout(function () {
            el.className = el.className.replace("show", "").trim();
        }, options.duration || 3000);
    }

    function modal(id, action) {
        action = action || "toggle";
        var el = typeof id === "string" ? document.getElementById(id) : id;
        if (!el) return;

        if (el.tagName === "DIALOG") {
            if (action === "open") el.showModal ? el.showModal() : (el.open = true);
            else if (action === "close") el.close ? el.close() : (el.open = false);
            else el.open ? (el.close ? el.close() : (el.open = false)) : el.showModal ? el.showModal() : (el.open = true);
        } else {
            if (action === "open") el.classList.add("open", "show");
            else if (action === "close") el.classList.remove("open", "show");
            else el.classList.toggle("open");
        }
    }

    function drawer(id, action) {
        action = action || "toggle";
        var el = typeof id === "string" ? document.getElementById(id) : id;
        if (!el) return;
        if (action === "open") el.classList.add("open");
        else if (action === "close") el.classList.remove("open");
        else el.classList.toggle("open");
    }

    function theme(action, val) {
        action = action || "toggle";
        var current = document.documentElement.getAttribute("data-theme") || "dark";
        if (action === "get") return current;
        var next = val ? val : action === "toggle" ? (current === "dark" ? "light" : "dark") : current;
        document.documentElement.setAttribute("data-theme", next);
        localStorage.setItem("theme", next);
        return next;
    }

    function tab(id) {
        var target = typeof id === "string" ? document.getElementById(id) : id;
        if (!target) return;
        var tabId = typeof id === "string" ? id : target.id;
        var parent = target.closest(".tabs") || document;
        parent.querySelectorAll(".tab-content").forEach(function (c) {
            c.classList.remove("active");
        });
        parent.querySelectorAll(".tab-trigger, [data-tab]").forEach(function (t) {
            t.classList.remove("active");
        });
        target.classList.add("active");
        if (tabId) {
            var trigger = parent.querySelector(`[data-tab="${tabId}"], a[href="#${tabId}"]`);
            if (trigger) trigger.classList.add("active");
        }
    }

    function promptInstall() {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function () {
            deferredPrompt = null;
        });
    }

    window.addEventListener("beforeinstallprompt", function (e) {
        e.preventDefault();
        deferredPrompt = e;
    });

    // Global Click Handler for [data-link] and [data-tab]
    document.addEventListener("click", function (e) {
        var tabBtn = e.target.closest("[data-tab]");
        if (tabBtn) {
            var tabTarget = tabBtn.getAttribute("data-tab");
            if (tabTarget) tab(tabTarget);
        }

        var link = e.target.closest("[data-link]");
        if (link) {
            e.preventDefault();
            var href = link.getAttribute("href");
            if (href) navigate(href, false);
        }
    });

    // --------------------------------------------------------------------------
    // Public Global Lila Instance
    // --------------------------------------------------------------------------
    var LilaInstance = {
        // SPA & Reactive
        reactive: reactive,
        state: function (initial) {
            var val = reactive({ value: initial });
            return {
                get: function () {
                    return val.value;
                },
                set: function (newVal) {
                    val.value = typeof newVal === "function" ? newVal(val.value) : newVal;
                },
                subscribe: function (fn) {
                    return val.$subscribe(function () {
                        fn(val.value);
                    });
                },
            };
        },
        mount: mount,
        route: route,
        start: start,
        navigate: navigate,
        beforeRoute: setBeforeRoute,
        store: store,
        getStore: getStore,
        html: html,
        escapeHtml: escapeHtml,

        // HTTP API
        fetch: request,
        http: request,
        api: api,

        // Bluebird UI Suite
        toast: toast,
        snackbar: snackbar,
        modal: modal,
        drawer: drawer,
        tab: tab,
        theme: theme,

        // DOM Utilities
        $: function (sel, ctx) {
            return (ctx || document).querySelector(sel);
        },
        $$: function (sel, ctx) {
            return Array.from((ctx || document).querySelectorAll(sel));
        },
        ready: function (fn) {
            if (document.readyState !== "loading") fn();
            else document.addEventListener("DOMContentLoaded", fn);
        },
    };

    window.Lila = LilaInstance;
    window.App = LilaInstance;
    window.toast = toast;
    window.snackbar = snackbar;
})();