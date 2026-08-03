import React, { useState } from 'react';

/**
 * Basic Home Page Component for LilaPHP.
 * Demonstrates React hydration and live PHP API cluster connection.
 * 
 * @returns {JSX.Element}
 */
export default function Home() {
  const [loading, setLoading] = useState(false);
  const [metrics, setMetrics] = useState({
    speed: '---',
    speedStatus: 'Waiting for diagnostics...',
    apcu: '---',
    apcuStatus: 'Checking memory...',
    redis: '---',
    redisStatus: 'Checking connection...',
    mysql: '---',
    mysqlStatus: 'Checking connection...',
    rawJson: 'Click "Run Real-Time Diagnostics" to trigger a live request against the LilaPHP API cluster...',
  });

  const runDiagnostics = async () => {
    setLoading(true);
    setMetrics((prev) => ({
      ...prev,
      rawJson: '⚡ Requesting /api/health from PHP 8.4+ workers...',
    }));

    try {
      const startTime = performance.now();
      const response = await fetch('/api/health', {
        method: 'GET',
        headers: {
          Accept: 'application/json',
          'Cache-Control': 'no-cache',
        },
      });

      const roundTripMs = Math.round(performance.now() - startTime);

      if (!response.ok) {
        throw new Error(`HTTP Error ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();

      setMetrics({
        speed: data.performance_ms !== undefined ? data.performance_ms : '0.5',
        speedStatus: `Roundtrip Nginx/PHP: ${roundTripMs} ms`,
        apcu: data.drivers?.apcu_ram?.enabled ? 'ACTIVE' : 'FALLBACK',
        apcuStatus: data.drivers?.apcu_ram?.enabled
          ? 'Shared RAM Worker cache ready'
          : 'Using in-memory array (CLI/Dev)',
        redis: data.drivers?.redis?.functional ? 'CONNECTED' : 'INACTIVE',
        redisStatus: data.drivers?.redis?.functional
          ? 'Redis 6379 pool responding'
          : data.drivers?.redis?.status || 'Not connected in this profile',
        mysql: data.drivers?.mysql?.functional ? 'CONNECTED' : 'DISCONNECTED',
        mysqlStatus: data.drivers?.mysql?.functional
          ? 'PDO Pool with MySQL 3306 ok'
          : data.drivers?.mysql?.status || 'Verify .env variables',
        rawJson: JSON.stringify(data, null, 2),
      });
    } catch (error) {
      setMetrics((prev) => ({
        ...prev,
        speedStatus: 'Connection Error',
        rawJson: `❌ Error contacting /api/health:\n\n${error.message}\n\nMake sure Nginx and PHP containers are running.`,
      }));
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <section className="hero">
        <div className="hero-text">
          <div className="tagline">React 19 • Vite • PHP 8.4 SSR & API</div>
          <h2 className="hero-title">
            The PHP & React engine engineered for maximum{' '}
            <span className="gradient-text">speed and concurrency</span>.
          </h2>
          <p className="hero-description">
            LilaPHP combines PHP server-side SEO pre-rendering, session middleware checks, and fast caching with React SPA hydration and Vite hot-reloading.
          </p>
          <div className="hero-actions">
            <button
              onClick={runDiagnostics}
              disabled={loading}
              className="btn btn-primary"
            >
              <span>{loading ? 'Querying Live API...' : 'Run Real-Time Diagnostics'}</span>
              <svg className="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
              </svg>
            </button>
            <a href="/api/" target="_blank" rel="noreferrer" className="btn btn-secondary">
              View JSON Endpoint (/api/)
            </a>
          </div>
        </div>
      </section>

      <section id="diagnostics" className="diagnostics-section">
        <div className="section-header">
          <h3>Real-Time Cluster Diagnostics</h3>
          <p>Live metrics returned from <code className="inline-code">/api/health</code> via Nginx FastCGI.</p>
        </div>

        <div className="stats-grid">
          <div className="stat-card">
            <div className="stat-icon speed-icon">⚡</div>
            <div className="stat-info">
              <span className="stat-label">PHP Latency</span>
              <div className="stat-value">{metrics.speed} <small>ms</small></div>
              <span className="stat-sub status-green">{metrics.speedStatus}</span>
            </div>
          </div>

          <div className="stat-card">
            <div className="stat-icon ram-icon">🧠</div>
            <div className="stat-info">
              <span className="stat-label">RAM Cache (APCu)</span>
              <div className="stat-value">{metrics.apcu}</div>
              <span className="stat-sub status-green">{metrics.apcuStatus}</span>
            </div>
          </div>

          <div className="stat-card">
            <div className="stat-icon redis-icon">💎</div>
            <div className="stat-info">
              <span className="stat-label">Redis Cluster</span>
              <div className="stat-value">{metrics.redis}</div>
              <span className="stat-sub status-green">{metrics.redisStatus}</span>
            </div>
          </div>

          <div className="stat-card">
            <div className="stat-icon db-icon">🗄️</div>
            <div className="stat-info">
              <span className="stat-label">MySQL Pool</span>
              <div className="stat-value">{metrics.mysql}</div>
              <span className="stat-sub status-green">{metrics.mysqlStatus}</span>
            </div>
          </div>
        </div>

        <div className="terminal-box">
          <div className="terminal-header">
            <span>API Response (RAW JSON)</span>
            <span className="badge-live">LIVE</span>
          </div>
          <pre className="terminal-body">{metrics.rawJson}</pre>
        </div>
      </section>
    </>
  );
}
