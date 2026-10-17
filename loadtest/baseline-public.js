// Step 1 baseline: how many requests per second the deployed app can serve before
// it slows past the 3-second target, using public pages only — no logins, no forms,
// no data changes.
//
//   k6 run -e BASE_URL=https://<service>.onrender.com loadtest/baseline-public.js
//
// Optional: -e EDGE_IP=216.24.57.1 sends traffic to that Render edge address, for a
// network that cannot reach the one DNS returns (see loadtest/README.md).
//
// The load rises in steps. Each step is its own scenario, so the summary shows the
// 95th-percentile time and error rate at every rate side by side. The test stops
// itself once the app is clearly overloaded, to keep the slowdown for anyone using
// the live site short.

import http from 'k6/http';
import { check, fail, sleep } from 'k6';

const BASE_URL = (__ENV.BASE_URL || '').replace(/\/+$/, '');
if (!BASE_URL) {
    throw new Error('Set BASE_URL, e.g. -e BASE_URL=https://<service>.onrender.com');
}
const HOST = (BASE_URL.match(/^https?:\/\/([^/:]+)/) || [])[1];

// Requests per second at each step, and how long each step lasts.
const STEPS = (__ENV.STEPS || '0.5,1,2,3,5,8,12').split(',').map(Number);
const STEP_SECONDS = Number(__ENV.STEP_SECONDS || 40);

const scenarios = {};
const thresholds = {
    // Safety stop: once a fifth of requests fail or the slowest 5% take over
    // 15 seconds, the app is past its limit and more load only hurts real users.
    http_req_failed: [{ threshold: 'rate<0.20', abortOnFail: true, delayAbortEval: '20s' }],
    http_req_duration: [{ threshold: 'p(95)<15000', abortOnFail: true, delayAbortEval: '20s' }],
};

STEPS.forEach((rate, i) => {
    const name = `rps_${String(rate).replace('.', '_')}`;

    // Whole requests per time unit: 0.5/s is 1 request every 2 seconds.
    const perTwoSeconds = rate * 2;
    scenarios[name] = {
        executor: 'constant-arrival-rate',
        rate: Number.isInteger(rate) ? rate : perTwoSeconds,
        timeUnit: Number.isInteger(rate) ? '1s' : '2s',
        duration: `${STEP_SECONDS}s`,
        startTime: `${i * STEP_SECONDS}s`,
        preAllocatedVUs: Math.max(2, Math.ceil(rate * 3)),
        maxVUs: Math.max(10, Math.ceil(rate * 20)),
        exec: 'browse',
    };

    // Recorded per step. Not stop conditions — they show which steps met the targets.
    thresholds[`http_req_duration{scenario:${name}}`] = ['p(95)<3000'];
    thresholds[`http_req_failed{scenario:${name}}`] = ['rate<0.01'];
});

export const options = {
    scenarios,
    thresholds,
    hosts: __ENV.EDGE_IP && HOST ? { [HOST]: __ENV.EDGE_IP } : {},
    userAgent: 'ClubSync-loadtest/1.0 (k6; baseline-public)',
    summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'max'],
    setupTimeout: '180s',
};

// A request still unanswered after 30 seconds counts as failed.
const PARAMS = { timeout: '30s', redirects: 0 };

// Wake the free instance (it sleeps after 15 idle minutes and takes about a minute
// to start), then refuse to go on unless the target really is ClubSync.
export function setup() {
    let wake;
    for (let attempt = 1; attempt <= 3; attempt++) {
        wake = http.get(`${BASE_URL}/login`, { timeout: '90s', tags: { name: 'warmup' } });
        if (wake.status === 200) break;
        sleep(5);
    }

    if (wake.status !== 200 || !/ClubSync/i.test(wake.body || '')) {
        fail(`Not load-testing ${BASE_URL}: /login answered ${wake.status} without the ClubSync page. Check BASE_URL.`);
    }

    // One unloaded sample of each page, for comparison with the loaded steps.
    const idle = {};
    for (const path of ['/login', '/ping']) {
        const r = http.get(`${BASE_URL}${path}`, { ...PARAMS, tags: { name: 'idle' } });
        idle[path] = `${r.status} in ${Math.round(r.timings.duration)} ms`;
    }
    console.log(`Target is ClubSync. Unloaded: /login ${idle['/login']}, /ping ${idle['/ping']}`);
}

// One visit: mostly the sign-in page (a full page render), sometimes the health
// probe (one database round trip) — about what the connection banner adds.
export function browse() {
    if (Math.random() < 0.7) {
        const r = http.get(`${BASE_URL}/login`, { ...PARAMS, tags: { name: 'login page' } });
        check(r, { 'login page 200': (res) => res.status === 200 });
    } else {
        const r = http.get(`${BASE_URL}/ping`, { ...PARAMS, tags: { name: 'ping' } });
        check(r, { 'ping 204': (res) => res.status === 204 });
    }
}
