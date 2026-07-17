/**
 * LilaPHP Real-Time WebSocket Client (`LilaWS`).
 *
 * Socket.IO style real-time wrapper with auto-reconnection, heartbeat, room joining,
 * and custom event broadcasting over Workerman (`Core\Ws`).
 */
class LilaWS {
  /**
   * @param {string|null} url Optional WebSocket URL (defaults to ws:// or wss:// current host/ws)
   * @param {object} options Options for reconnection and heartbeat
   */
  constructor(url = null, options = {}) {
    if (!url) {
      const protocol = window.location.protocol === "https:" ? "wss:" : "ws:";
      url =
        window.location.port === "8080" ||
        window.location.port === "80" ||
        window.location.port === "443" ||
        !window.location.port
          ? `${protocol}//${window.location.host}/ws`
          : `${protocol}//${window.location.hostname}:8001`;
    }

    this.url = url;
    this.options = Object.assign(
      {
        reconnect: true,
        reconnectInterval: 2000,
        maxRetries: 15,
        heartbeatInterval: 25000,
      },
      options,
    );

    this.socket = null;
    this.listeners = new Map();
    this.retries = 0;
    this.isConnected = false;
    this.heartbeatTimer = null;
    this.joinedRooms = new Set();
    this.sendQueue = [];

    this.connect();
  }

  /**
   * Establishes connection to the WebSocket server.
   */
  connect() {
    try {
      this.socket = new WebSocket(this.url);
    } catch (e) {
      console.error("[LilaWS] Connection error:", e);
      this.scheduleReconnect();
      return;
    }

    this.socket.onopen = () => {
      console.log(`[LilaWS] ⚡ Connected to ${this.url}`);
      this.isConnected = true;
      this.retries = 0;
      this.startHeartbeat();

      this.joinedRooms.forEach((room) => {
        this.sendAction("join", { room });
      });

      while (this.sendQueue.length > 0) {
        const packet = this.sendQueue.shift();
        this.socket.send(JSON.stringify(packet));
      }

      this.trigger("connect");
    };

    this.socket.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data);
        if (data && data.event) {
          if (data.event === "pong") {
            return;
          }
          this.trigger(data.event, data.data || data, data.room || null);
        }
      } catch (e) {
        this.trigger("message", event.data);
      }
    };

    this.socket.onclose = () => {
      console.warn("[LilaWS] Disconnected from server.");
      this.isConnected = false;
      this.stopHeartbeat();
      this.trigger("disconnect");
      this.scheduleReconnect();
    };

    this.socket.onerror = (error) => {
      console.error("[LilaWS] Socket error:", error);
      this.trigger("error", error);
    };
  }

  /**
   * Subscribes to an event emitted from Workerman or PHP-FPM API routes (`Core\Ws::publish`).
   *
   * @param {string} event Name of the event
   * @param {Function} callback Handler function(data, room)
   */
  on(event, callback) {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, []);
    }
    this.listeners.get(event).push(callback);
    return this;
  }

  /**
   * Unsubscribes from an event.
   */
  off(event, callback = null) {
    if (!callback) {
      this.listeners.delete(event);
    } else if (this.listeners.has(event)) {
      const updated = this.listeners.get(event).filter((cb) => cb !== callback);
      this.listeners.set(event, updated);
    }
    return this;
  }

  /**
   * Triggers registered event callbacks.
   */
  trigger(event, data = null, room = null) {
    if (this.listeners.has(event)) {
      this.listeners.get(event).forEach((cb) => {
        try {
          cb(data, room);
        } catch (e) {
          console.error(`[LilaWS] Error in handler for event "${event}":`, e);
        }
      });
    }
  }

  /**
   * Joins a WebSocket room.
   *
   * @param {string} room Room identifier (e.g., 'orders', 'chat_room_1')
   */
  join(room) {
    this.joinedRooms.add(room);
    if (this.isConnected) {
      this.sendAction("join", { room });
    }
    return this;
  }

  /**
   * Leaves a WebSocket room.
   */
  leave(room) {
    this.joinedRooms.delete(room);
    if (this.isConnected) {
      this.sendAction("leave", { room });
    }
    return this;
  }

  /**
   * Emits an event to the server to be broadcasted to others in the room/server.
   *
   * @param {string} event Event name
   * @param {object} data Payload data
   * @param {string|null} room Optional room name
   */
  emit(event, data = {}, room = null) {
    return this.sendAction("emit", { event, data, room });
  }

  /**
   * Emits an event to be broadcasted to EVERYONE, including the sender socket.
   *
   * @param {string} event Event name
   * @param {object} data Payload data
   * @param {string|null} room Optional room name
   */
  broadcastAll(event, data = {}, room = null) {
    return this.sendAction("broadcastAll", { event, data, room });
  }

  /**
   * Internal helper to send JSON formatted actions over WebSocket.
   */
  sendAction(action, payload = {}) {
    const packet = Object.assign({ action }, payload);
    if (
      !this.isConnected ||
      !this.socket ||
      this.socket.readyState !== WebSocket.OPEN
    ) {
      this.sendQueue.push(packet);
      return false;
    }
    this.socket.send(JSON.stringify(packet));
    return true;
  }

  /**
   * Heartbeat loop to keep connection alive across Nginx proxy.
   */
  startHeartbeat() {
    this.stopHeartbeat();
    this.heartbeatTimer = setInterval(() => {
      this.sendAction("ping");
    }, this.options.heartbeatInterval);
  }

  /**
   * Stops heartbeat loop.
   */
  stopHeartbeat() {
    if (this.heartbeatTimer) {
      clearInterval(this.heartbeatTimer);
      this.heartbeatTimer = null;
    }
  }

  /**
   * Schedules automatic reconnection attempts.
   */
  scheduleReconnect() {
    if (
      !this.options.reconnect ||
      (this.options.maxRetries > 0 && this.retries >= this.options.maxRetries)
    ) {
      console.error("[LilaWS] Reconnection aborted after max retries.");
      return;
    }
    this.retries++;
    const delay = Math.min(
      this.options.reconnectInterval * Math.pow(1.2, this.retries),
      10000,
    );
    console.log(
      `[LilaWS] Reconnecting in ${Math.round(delay / 1000)}s (Attempt ${this.retries}/${this.options.maxRetries})...`,
    );
    setTimeout(() => this.connect(), delay);
  }

  /**
   * Disconnects explicitly without reconnecting.
   */
  disconnect() {
    this.options.reconnect = false;
    this.stopHeartbeat();
    if (this.socket) {
      this.socket.close();
    }
  }
}

if (typeof window !== "undefined") {
  window.LilaWS = LilaWS;
}
