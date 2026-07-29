let cart = Lila.store("cart", { items: [], total: 0 });
const { html } = Lila;

Lila.mount("#cart-count", {
  state: { count: 0 },
  template: (s) => html`${s.count}`,
  onMount: (state) => {
    let c = Lila.getStore("cart");
    return c.$subscribe(() => {
      state.count = c.$raw.items.length;
    });
  },
});

Lila.route("/", {
  state: () => ({ count: 0, name: "", doubled: 0 }),
  template: (s) => html`
    <div class="page">
      <div class="hero-banner">
        <h2>Lila<span class="accent">.js</span> Interactive Demo</h2>
        <p class="subtitle">
          Lightweight reactive engine for LilaPHP — hash routing, island
          reactivity, zero dependencies.
        </p>
      </div>

      <div class="demo-grid">
        <div class="card">
          <h3>⚡ Reactive Counter</h3>
          <p class="muted">
            State updates propagate instantly to all bound elements.
          </p>
          <div class="counter-row">
            <button data-action="decrement" class="btn btn-icon">−</button>
            <span class="counter-value">${s.count}</span>
            <button data-action="increment" class="btn btn-icon">+</button>
          </div>
          <p class="muted">Doubled: <strong>${s.doubled}</strong></p>
        </div>

        <div class="card">
          <h3>🔗 Two-Way Binding</h3>
          <p class="muted">
            <code>data-model</code> syncs input ↔ state automatically.
          </p>
          <input
            data-model="name"
            placeholder="Type your name..."
            class="input"
            value="${s.name}"
          />

          ${s.name
            ? html`
                <div class="greeting">
                  Hello, <strong>${s.name}</strong>! 👋
                </div>
              `
            : html`
                <div class="muted" style="margin-top:1rem;">
                  Start typing above...
                </div>
              `}
        </div>
      </div>
    </div>
  `,
  actions: {
    increment: (ctx) => {
      ctx.state.count++;
      ctx.state.doubled = ctx.state.count * 2;
    },
    decrement: (ctx) => {
      ctx.state.count--;
      ctx.state.doubled = ctx.state.count * 2;
    },
  },
});

Lila.route("/products", {
  state: () => ({ products: [], loading: true }),
  template: (s) => html`
    <div class="page">
      <h2>🛍️ Products</h2>
      <p class="subtitle">
        Simulated API fetch with skeleton loading & ES6 Template mapping.
      </p>

      ${s.loading
        ? html`
            <div class="skeleton-grid">
              ${'<div class="lila-skeleton"></div>'.repeat(6)}
            </div>
          `
        : html`
            <div class="product-grid">
              ${s.products.map(
                (item) => html`
                  <div class="card product-card">
                    <div class="product-emoji">${item.icon}</div>
                    <h4>${item.name}</h4>
                    <p class="price">$${item.price}</p>
                    <button
                      data-action="addToCart"
                      data-id="${item.id}"
                      class="btn btn-primary"
                    >
                      Add to Cart
                    </button>
                  </div>
                `,
              )}
            </div>
          `}
    </div>
  `,
  actions: {
    addToCart: (ctx) => {
      let c = Lila.getStore("cart");
      let id = parseInt(ctx.id);
      let item = ctx.state.products.find((p) => p.id === id);
      let exists = c.$raw.items.find((i) => i.id === id);

      if (!exists && item) {
        c.items.push(Object.assign({}, item));
        c.total = c.$raw.items.reduce((sum, i) => sum + i.price, 0);
      }
    },
  },
  onMount: (state) => {
    return new Promise((resolve) => {
      setTimeout(() => {
        state.products = [
          { id: 1, name: "Mechanical Keyboard", price: 89.99, icon: "⌨️" },
          { id: 2, name: "Wireless Mouse", price: 49.99, icon: "🖱️" },
          { id: 3, name: "USB-C Hub", price: 34.99, icon: "🔌" },
          { id: 4, name: "4K Monitor", price: 399.99, icon: "🖥️" },
          { id: 5, name: "Webcam HD", price: 59.99, icon: "📷" },
          { id: 6, name: "LED Desk Lamp", price: 29.99, icon: "💡" },
        ];
        state.loading = false;
        resolve();
      }, 800);
    });
  },
});

Lila.route("/cart", {
  state: () => ({ items: [], total: 0 }),
  template: (s) => html`
    <div class="page">
      <h2>🛒 Shopping Cart</h2>

      ${s.items.length === 0
        ? html`
            <div class="empty-state">
              <div class="empty-icon">🛒</div>
              <p>Your cart is empty</p>
              <a href="#/products" data-link class="btn btn-primary"
                >Browse Products</a
              >
            </div>
          `
        : html`
            <div>
              <div class="cart-list">
                ${s.items.map(
                  (item, index) => html`
                    <div class="cart-item">
                      <span class="cart-item-icon">${item.icon}</span>
                      <div class="cart-item-info">
                        <strong>${item.name}</strong>
                        <span class="price">$${item.price}</span>
                      </div>
                      <button
                        data-action="remove"
                        data-index="${index}"
                        class="btn btn-danger btn-sm"
                      >
                        Remove
                      </button>
                    </div>
                  `,
                )}
              </div>
              <div class="cart-footer">
                <span class="cart-total"
                  >Total: $<strong>${s.total.toFixed(2)}</strong></span
                >
                <button data-action="clear" class="btn btn-outline">
                  Clear Cart
                </button>
              </div>
            </div>
          `}
    </div>
  `,
  actions: {
    remove: (ctx) => {
      let idx = parseInt(ctx.index);
      let c = Lila.getStore("cart");
      c.items.splice(idx, 1);
      c.total = c.$raw.items.reduce((sum, i) => sum + i.price, 0);
      ctx.state.items = c.$raw.items.slice();
      ctx.state.total = c.$raw.total;
    },
    clear: (ctx) => {
      let c = Lila.getStore("cart");
      c.items.splice(0, c.$raw.items.length);
      c.total = 0;
      ctx.state.items = [];
      ctx.state.total = 0;
    },
  },
  onMount: (state) => {
    let c = Lila.getStore("cart");
    state.items = c.$raw.items.slice();
    state.total = c.$raw.total;
  },
});

Lila.route(
  "*",
  () => html`
    <div class="page" style="text-align:center;padding:4rem 2rem">
      <h2 style="font-size:3rem;margin-bottom:1rem">404</h2>
      <p class="subtitle">Page not found</p>
      <a href="#/" data-link class="btn btn-primary" style="margin-top:1.5rem"
        >Go Home</a
      >
    </div>
  `,
);

Lila.start("#app", { transition: "fade" });
