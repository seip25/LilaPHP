/**
 * LilaPHP Frontend Dashboard & Live API Diagnostics Engine
 */

document.addEventListener('DOMContentLoaded', () => {
    const runBtn = document.getElementById('run-test-btn');
    const heroBtn = document.getElementById('hero-benchmark-btn');
    const jsonViewer = document.getElementById('json-viewer');

    const metricSpeed = document.getElementById('metric-speed');
    const statusSpeed = document.getElementById('status-speed');

    const metricApcu = document.getElementById('metric-apcu');
    const statusApcu = document.getElementById('status-apcu');

    const metricRedis = document.getElementById('metric-redis');
    const statusRedis = document.getElementById('status-redis');

    const metricMysql = document.getElementById('metric-mysql');
    const statusMysql = document.getElementById('status-mysql');

    async function runLiveDiagnostics() {
        if (runBtn) runBtn.textContent = 'Running Diagnostics...';
        if (heroBtn) heroBtn.querySelector('span').textContent = 'Querying Live API...';
        if (jsonViewer) jsonViewer.textContent = '⚡ Requesting /api/test from PHP 8.4+ workers...';

        try {
            const startTime = performance.now();
            const response = await fetch('/api/test', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            });

            const roundTripMs = Math.round(performance.now() - startTime);

            if (!response.ok) {
                throw new Error(`HTTP Error ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (jsonViewer) {
                jsonViewer.textContent = JSON.stringify(data, null, 2);
            }

            // Update Latency Card
            if (metricSpeed && data.performance_ms !== undefined) {
                metricSpeed.textContent = data.performance_ms;
                statusSpeed.textContent = `Roundtrip Nginx/PHP: ${roundTripMs} ms`;
                statusSpeed.className = 'stat-sub status-green';
            }

            // Update APCu Card
            if (data.drivers && data.drivers.apcu_ram) {
                if (data.drivers.apcu_ram.enabled) {
                    metricApcu.textContent = 'ACTIVE';
                    statusApcu.textContent = 'Shared RAM Worker cache ready';
                    statusApcu.className = 'stat-sub status-green';
                } else {
                    metricApcu.textContent = 'FALLBACK';
                    statusApcu.textContent = 'Using in-memory array (CLI/Dev)';
                    statusApcu.className = 'stat-sub status-yellow';
                }
            }

            // Update Redis Card
            if (data.drivers && data.drivers.redis) {
                const redis = data.drivers.redis;
                if (redis.functional) {
                    metricRedis.textContent = 'CONNECTED';
                    statusRedis.textContent = 'Redis 6379 pool responding';
                    statusRedis.className = 'stat-sub status-green';
                } else {
                    metricRedis.textContent = 'INACTIVE';
                    statusRedis.textContent = redis.status || 'Not connected in this profile';
                    statusRedis.className = 'stat-sub status-yellow';
                }
            }

            // Update MySQL Card
            if (data.drivers && data.drivers.mysql) {
                const mysql = data.drivers.mysql;
                if (mysql.functional) {
                    metricMysql.textContent = 'CONNECTED';
                    statusMysql.textContent = 'PDO Pool with MySQL 3306 ok';
                    statusMysql.className = 'stat-sub status-green';
                } else {
                    metricMysql.textContent = 'DISCONNECTED';
                    statusMysql.textContent = mysql.status || 'Verify .env variables';
                    statusMysql.className = 'stat-sub status-yellow';
                }
            }

        } catch (error) {
            console.error('Error in live diagnostics:', error);
            if (jsonViewer) {
                jsonViewer.textContent = `❌ Error contacting /api/test:\n\n${error.message}\n\nMake sure Nginx and PHP containers are running (` + "`php cli.php docker dev`" + `).`;
            }
            if (statusSpeed) {
                statusSpeed.textContent = 'Connection Error';
                statusSpeed.className = 'stat-sub status-red';
            }
        } finally {
            if (runBtn) runBtn.textContent = 'Test API Live';
            if (heroBtn) heroBtn.querySelector('span').textContent = 'Run Real-Time Diagnostics';
        }
    }

    if (runBtn) {
        runBtn.addEventListener('click', (e) => {
            e.preventDefault();
            runLiveDiagnostics();
        });
    }

    if (heroBtn) {
        heroBtn.addEventListener('click', (e) => {
            e.preventDefault();
            runLiveDiagnostics();
        });
    }
});
