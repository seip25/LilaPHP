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

  const toggleTheme = () => {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-bs-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    html.setAttribute('data-bs-theme', newTheme);
    html.setAttribute('data-theme', newTheme);
  };

  return (
    <>
      <header>
        <nav className='navbar bg-body-tertiary'>
          <div className='container'>
            <a className='navbar-brand' href='#'>LilaPHP</a>
            <button className='btn btn-outline' onClick={toggleTheme}>
              Toggle Theme
            </button>
          </div>
        </nav>
      </header>
      <section className="container text-center py-5 mt-5">
        <div className="row justify-content-center">
          <div className="col-lg-8">
            <div className="text-uppercase text-muted fw-bold mb-3 small">React 19 • Vite • PHP 8.4 SSR & API</div>
            <h2 className="display-5 fw-bold mb-4">
              The PHP & React engine engineered for maximum{' '}
              <span className="text-primary">speed and concurrency</span>.
            </h2>
            <p className="lead text-muted mb-5">
              LilaPHP combines PHP server-side SEO pre-rendering, session middleware checks, and fast caching with React SPA hydration and Vite hot-reloading.
            </p>
            <div className="d-flex gap-3 justify-content-center flex-column flex-sm-row">
              <button
                onClick={runDiagnostics}
                disabled={loading}
                className="btn btn-primary btn-lg px-4"
              >
                <span>{loading ? 'Querying Live API...' : 'Run Real-Time Diagnostics'}</span>
              </button>
              <a href="/api/" target="_blank" rel="noreferrer" className="btn btn-secondary btn-lg px-4">
                View JSON Endpoint (/api/)
              </a>
            </div>
          </div>
        </div>
      </section>

      <section id="diagnostics" className="container py-5 mb-5">
        <div className="text-center mb-5">
          <h3 className="fw-bold">Real-Time Cluster Diagnostics</h3>
          <p className="text-muted">Live metrics returned from
            <code className="bg-success-subtle text-success px-2 py-2 rounded">/api/health</code> via Nginx FastCGI.</p>
        </div>

        <div className="row g-4 mb-5">
          <div className="col-12 col-md-6 col-lg-3">
            <div className="card h-100 shadow-sm border-0">
              <div className="card-body d-flex align-items-center">
                <div className="fs-1 me-3">⚡</div>
                <div>
                  <span className="text-muted small text-uppercase fw-bold d-block">PHP Latency</span>
                  <div className="fs-4 fw-bold text-dark">{metrics.speed} <small className="text-muted fs-6">ms</small></div>
                  <span className="text-success small">{metrics.speedStatus}</span>
                </div>
              </div>
            </div>
          </div>

          <div className="col-12 col-md-6 col-lg-3">
            <div className="card h-100 shadow-sm border-0">
              <div className="card-body d-flex align-items-center">
                <div className="fs-1 me-3">🧠</div>
                <div>
                  <span className="text-muted small text-uppercase fw-bold d-block">RAM Cache (APCu)</span>
                  <div className="fs-4 fw-bold text-dark">{metrics.apcu}</div>
                  <span className="text-success small">{metrics.apcuStatus}</span>
                </div>
              </div>
            </div>
          </div>

          <div className="col-12 col-md-6 col-lg-3">
            <div className="card h-100 shadow-sm border-0">
              <div className="card-body d-flex align-items-center">
                <div className="fs-1 me-3">💎</div>
                <div>
                  <span className="text-muted small text-uppercase fw-bold d-block">Redis Cluster</span>
                  <div className="fs-4 fw-bold text-dark">{metrics.redis}</div>
                  <span className="text-success small">{metrics.redisStatus}</span>
                </div>
              </div>
            </div>
          </div>

          <div className="col-12 col-md-6 col-lg-3">
            <div className="card h-100 shadow-sm border-0">
              <div className="card-body d-flex align-items-center">
                <div className="fs-1 me-3">🗄️</div>
                <div>
                  <span className="text-muted small text-uppercase fw-bold d-block">MySQL Pool</span>
                  <div className="fs-4 fw-bold text-dark">{metrics.mysql}</div>
                  <span className="text-success small">{metrics.mysqlStatus}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className="card text-dark text-dark border-0 shadow-lg">
          <div className="card-header text-dark border-secondary d-flex justify-content-between align-items-center py-3">
            <span className="fw-semibold">API Response (RAW JSON)</span>
            <span className="badge bg-danger rounded-pill px-3 py-2">LIVE</span>
          </div>
          <div className="card-body p-0">
            <pre className="m-0 p-4 overflow-auto" style={{ maxHeight: '400px' }}>
              <code className="text-success">{metrics.rawJson}</code>
            </pre>
          </div>
        </div>
      </section>
    </>
  );
}
