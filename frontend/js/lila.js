/**
 * Lila.js — Lightweight Reactive Engine for LilaPHP
 * @version 3.0.0
 * @license MIT
 * @see https://seip25.github.io/LilaPHP/lila-js.html
 */
(function () {
  "use strict";

  var stores = new Map();
  var routes = [];
  var instances = new Set();
  var outlet = null;
  var currentPath = null;
  var routeInstance = null;
  var transitionType = "fade";
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

  function sleep(ms) {
    return new Promise(function (r) {
      setTimeout(r, ms);
    });
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

  function morph(node, newHTML) {
    var t = document.createElement("template");
    t.innerHTML = newHTML;

    function walk(oldNode, newNode) {
      if (!oldNode || !newNode) return;
      if (
        oldNode.nodeType !== newNode.nodeType ||
        oldNode.nodeName !== newNode.nodeName
      ) {
        oldNode.parentNode.replaceChild(newNode.cloneNode(true), oldNode);
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
        for (i = oldAttrs.length - 1; i >= 0; i--) {
          var attrName = oldAttrs[i].name;
          if (!newNode.hasAttribute(attrName)) {
            oldNode.removeAttribute(attrName);
          }
        }
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
            return def.template || "";
          };

    function handleAction(e) {
      var n = e.target.closest("[data-action]");
      if (!n) return;
      var etype =
        n.getAttribute("data-event") ||
        (n.tagName === "FORM" ? "submit" : "click");
      if (e.type !== etype) return;
      if (n.tagName === "FORM") e.preventDefault();
      var name = n.getAttribute("data-action");
      if (def.actions[name]) {
        var payload = { state: state, event: e };
        for (var key in n.dataset) {
          if (key !== "action" && key !== "event") {
            payload[key] = n.dataset[key];
          }
        }
        def.actions[name](payload);
      }
    }

    function handleModel(e) {
      var n = e.target.closest("[data-model]");
      if (!n) return;
      var key = n.getAttribute("data-model");
      if (state[key] !== undefined) {
        if (n.type === "checkbox") state[key] = n.checked;
        else if (n.type === "radio") {
          if (n.checked) state[key] = n.value;
        } else state[key] = n.value;
      }
    }

    el.addEventListener("click", handleAction);
    el.addEventListener("submit", handleAction);
    el.addEventListener("input", handleModel);
    el.addEventListener("change", handleModel);

    function update(changedKeys) {
      if (!el) return;
      if (changedKeys)
        changedKeys.forEach(function (k) {
          pendingChanges.add(k);
        });
      else pendingChanges.add("*");

      if (renderQueued) return;
      renderQueued = true;

      requestAnimationFrame(function () {
        try {
          var htmlString = templateFn(state);
          morph(el, htmlString);
          pendingChanges.clear();
        } catch (e) {
          console.error("Lila: render error", e);
        } finally {
          renderQueued = false;
        }
      });
    }

    var unsub = state.$subscribe(function (key) {
      update([key]);
    });
    update();

    if (def.onMount) {
      setTimeout(function () {
        try {
          var result = def.onMount(state);
          if (result && typeof result.then === "function") {
            result.then(function (cleanup) {
              if (typeof cleanup === "function") mountCleanup = cleanup;
            });
          } else if (typeof result === "function") mountCleanup = result;
        } catch (e) {}
      }, 0);
    }

    var instance = {
      state: state,
      destroy: function () {
        if (typeof mountCleanup === "function") {
          try {
            mountCleanup();
          } catch (e) {}
        }
        if (def.onDestroy) {
          try {
            def.onDestroy(state);
          } catch (e) {}
        }
        el.removeEventListener("click", handleAction);
        el.removeEventListener("submit", handleAction);
        el.removeEventListener("input", handleModel);
        el.removeEventListener("change", handleModel);
        unsub();
        if (el) el.innerHTML = "";
        instances.delete(instance);
      },
      forceUpdate: function () {
        update();
      },
    };

    instances.add(instance);
    return instance;
  }

  function route(path, handler) {
    routes.push({ path: path, handler: handler });
  }

  function findRoute(path) {
    for (var i = 0; i < routes.length; i++) {
      if (routes[i].path === path) return routes[i];
    }
    for (var j = 0; j < routes.length; j++) {
      if (routes[j].path === "*") return routes[j];
    }
    return null;
  }

  async function handleRoute() {
    if (!outlet) return;
    var h = parseHash();

    if (beforeRouteHook) {
      var proceed = await beforeRouteHook(h.path, h.query);
      if (!proceed) return;
    }

    if (h.path === currentPath) return;
    currentPath = h.path;

    if (routeInstance) {
      try {
        routeInstance.destroy();
      } catch (e) {}
      routeInstance = null;
    }

    var match = findRoute(h.path);
    if (!match) {
      outlet.innerHTML =
        '<div style="padding:2rem;text-align:center;color:#94a3b8"><h2>404</h2><p>Page not found</p></div>';
      return;
    }

    if (transitionType !== "none" && outlet.innerHTML) {
      outlet.classList.add("lila-leave");
      await sleep(120);
      outlet.classList.remove("lila-leave");
    }

    var handler = match.handler;
    var outputHTML;

    try {
      if (typeof handler === "string") {
        outputHTML = handler;
      } else if (typeof handler === "function") {
        outlet.innerHTML = '<div class="lila-skeleton"></div>';
        var result = handler(h.query);
        outputHTML =
          result && typeof result.then === "function" ? await result : result;
      } else if (typeof handler === "object" && handler) {
        outlet.innerHTML = "";
        routeInstance = mount(outlet, handler);
        applyEnter();
        return;
      }
    } catch (e) {
      console.error("Lila: route error", e);
      outputHTML =
        '<div style="padding:2rem;color:#f87171">Error loading page</div>';
    }

    if (typeof outputHTML === "string") outlet.innerHTML = outputHTML;
    applyEnter();
  }

  function applyEnter() {
    if (!outlet || transitionType === "none") return;
    outlet.classList.add("lila-enter", "lila-active");
    void outlet.offsetHeight;
    outlet.classList.remove("lila-enter");
    setTimeout(function () {
      outlet.classList.remove("lila-active");
    }, 200);
  }

  function start(selector, options) {
    injectStyles();
    outlet =
      typeof selector === "string"
        ? document.querySelector(selector)
        : selector;
    if (!outlet) return;

    var opts = options || {};
    transitionType = opts.transition || "fade";

    if (opts.pwa) {
      setupPWA(opts.pwa);
    }

    window.addEventListener("hashchange", handleRoute);
    handleRoute();
  }

  function setupPWA(config) {
    if ("serviceWorker" in navigator && config.worker) {
      window.addEventListener("load", function () {
        navigator.serviceWorker.register(config.worker).catch(function (err) {
          console.error("Lila PWA: ServiceWorker registration failed: ", err);
        });
      });
    }
    if (config.installPrompt) {
      window.addEventListener("beforeinstallprompt", function (e) {
        e.preventDefault();
        deferredPrompt = e;
      });
    }
  }

  async function promptInstall() {
    if (deferredPrompt) {
      deferredPrompt.prompt();
      var result = await deferredPrompt.userChoice;
      deferredPrompt = null;
      return result.outcome === "accepted";
    }
    return false;
  }

  function navigate(path, isExternal) {
    if (isExternal) {
      window.location.href = path;
    } else {
      if (location.hash.slice(1) !== path) location.hash = path;
    }
  }

  function setBeforeRoute(hook) {
    beforeRouteHook = hook;
  }

  function store(name, initial) {
    if (stores.has(name)) return stores.get(name);
    var s = reactive(initial || {});
    stores.set(name, s);
    return s;
  }

  function getStore(name) {
    return stores.get(name) || null;
  }

  async function request(url, options) {
    var opts = options || {};
    var cfg = {
      method: opts.method || "GET",
      headers: Object.assign(
        { Accept: "application/json", "Content-Type": "application/json" },
        opts.headers || {},
      ),
    };
    if (opts.body)
      cfg.body =
        typeof opts.body === "string" ? opts.body : JSON.stringify(opts.body);
    var res = await fetch(url, cfg);
    if (!res.ok) {
      let data = await res.json();
      if (data.message || data.mensaje || data.msg || data.msj || data.error) {
        throw new Error(
          data.message || data.mensaje || data.msg || data.msj || data.error,
        );
      } else {
        throw new Error("HTTP " + res.status + ": " + res.statusText);
      }
    }
    var ct = res.headers.get("content-type");
    return ct && ct.indexOf("application/json") >= 0 ? res.json() : res.text();
  }

  // --- Validator Module ---
  var Validator = (function () {
    var messages = {
      es: {
        required: function (f) {
          return "El campo " + f + " es obligatorio";
        },
        email: function (f) {
          return "El campo " + f + " debe ser un email válido";
        },
        min: function (f, n) {
          return "El campo " + f + " debe tener al menos " + n + " caracteres";
        },
        max: function (f, n) {
          return (
            "El campo " + f + " no puede tener más de " + n + " caracteres"
          );
        },
        equals: function (f, v) {
          return "El campo " + f + " debe ser igual a " + v;
        },
        pattern: function (f) {
          return "El campo " + f + " no cumple el patrón requerido";
        },
      },
      en: {
        required: function (f) {
          return f + " is required";
        },
        email: function (f) {
          return f + " must be a valid email";
        },
        min: function (f, n) {
          return f + " must be at least " + n + " characters";
        },
        max: function (f, n) {
          return f + " must be at most " + n + " characters";
        },
        equals: function (f, v) {
          return f + " must equal " + v;
        },
        pattern: function (f) {
          return f + " does not match the required pattern";
        },
      },
    };

    var rules = {
      isEmpty: function (v) {
        return v === undefined || v === null || v === "";
      },
      isEmail: function (v) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
      },
      isLength: function (v, min, max) {
        var len = String(v || "").length;
        if (min !== undefined && len < min) return false;
        if (max !== undefined && len > max) return false;
        return true;
      },
      equals: function (v, c) {
        return v === c;
      },
      matches: function (v, p) {
        return p.test(v);
      },
    };

    return {
      validate: function (data, schema, options) {
        var opts = options || {};
        var lang = opts.lang || "es";
        var msg = messages[lang] || messages["en"];
        var errors = [];
        var success = true;

        for (var field in schema) {
          var config = schema[field];
          var value = data[field];

          if (config.required && rules.isEmpty(value)) {
            errors.push({ field: field, message: msg.required(field) });
            success = false;
            continue;
          }

          if (!rules.isEmpty(value)) {
            if (config.email && !rules.isEmail(value)) {
              errors.push({ field: field, message: msg.email(field) });
              success = false;
            }
            if (
              config.min !== undefined &&
              !rules.isLength(value, config.min, undefined)
            ) {
              errors.push({
                field: field,
                message: msg.min(field, config.min),
              });
              success = false;
            }
            if (
              config.max !== undefined &&
              !rules.isLength(value, undefined, config.max)
            ) {
              errors.push({
                field: field,
                message: msg.max(field, config.max),
              });
              success = false;
            }
            if (
              config.equals !== undefined &&
              !rules.equals(value, config.equals)
            ) {
              errors.push({
                field: field,
                message: msg.equals(field, config.equals),
              });
              success = false;
            }
            if (config.pattern && !rules.matches(value, config.pattern)) {
              errors.push({ field: field, message: msg.pattern(field) });
              success = false;
            }
          }
        }

        var result = { success: success, errors: errors };
        if (opts.callback) opts.callback(result);
        return result;
      },
    };
  })();

  document.addEventListener("click", function (e) {
    var link = e.target.closest("[data-link]");
    if (link) {
      e.preventDefault();
      var href = link.getAttribute("href");
      if (href) navigate(href, false);
    }
  });

  window.Lila = {
    reactive: reactive,
    mount: mount,
    route: route,
    start: start,
    navigate: navigate,
    beforeRoute: setBeforeRoute,
    store: store,
    getStore: getStore,
    fetch: request,
    html: html,
    promptInstall: promptInstall,
    Validator: Validator,
  };

  window.App = window.Lila;
})();
