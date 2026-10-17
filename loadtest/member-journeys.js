// Signed-in students using ClubSync, against the rehearsal (loadtest/docker-compose.yml).
//
//   k6 run -e SCENARIO=load loadtest/member-journeys.js
//
// Each virtual user is one fake student, signed in with a session from
// `php artisan loadtest:sessions`. They open a page, read it for 20–40 seconds, and
// open another, which is one page every ~30 seconds per student. About 1 in 20 is a
// club president working through the officer pages.
//
// SCENARIO picks the shape of the test (all measured against the same targets:
// 95% of pages in under 3 s, fewer than 1% errors):
//   smoke   10 students for 1 minute — checks the setup before a long run
//   load    ramp to USERS (800) over 5 min, hold HOLD (10m), ramp down
//   spike   60 students, then USERS arrive within 30 s and stay 3 min
//   stress  keep adding students up to 2 × USERS, to find where it breaks
//   soak    USERS for HOLD (default 2h) — does anything wear down over time?

import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { Rate } from 'k6/metrics';

// The share of page loads that met both targets at once: loaded fine and in under
// 3 seconds. The single "success rate" figure for the test report.
const withinTarget = new Rate('pages_ok_within_3s');

const BASE_URL = (__ENV.BASE_URL || 'http://localhost:8080').replace(/\/+$/, '');
const SCENARIO = __ENV.SCENARIO || 'smoke';
const USERS = Number(__ENV.USERS || 800);
const HOLD = __ENV.HOLD || (SCENARIO === 'soak' ? '2h' : '10m');
const THINK_MIN = Number(__ENV.THINK_MIN || 20);
const THINK_MAX = Number(__ENV.THINK_MAX || 40);

// user_id, role, club_id, cookie_name, cookie_value — written by loadtest:sessions.
const sessions = new SharedArray('sessions', () =>
    open(__ENV.SESSIONS || './out/sessions.csv')
        .trim()
        .split(/\r?\n/)
        .slice(1)
        .map((line) => {
            const [userId, role, clubId, cookieName, cookieValue] = line.split(',');
            return { userId, role, clubId, cookieName, cookieValue };
        }),
);
const members = sessions.filter((s) => s.role === 'member');
const officers = sessions.filter((s) => s.role !== 'member');

const shapes = {
    smoke: [{ duration: '10s', target: 10 }, { duration: '50s', target: 10 }],
    load: [{ duration: '5m', target: USERS }, { duration: HOLD, target: USERS }, { duration: '1m', target: 0 }],
    spike: [{ duration: '1m', target: 60 }, { duration: '30s', target: USERS }, { duration: '3m', target: USERS }, { duration: '30s', target: 0 }],
    stress: [{ duration: '4m', target: USERS / 2 }, { duration: '4m', target: USERS }, { duration: '4m', target: USERS * 1.5 }, { duration: '4m', target: USERS * 2 }, { duration: '1m', target: 0 }],
    soak: [{ duration: '5m', target: USERS }, { duration: HOLD, target: USERS }, { duration: '1m', target: 0 }],
};
if (!shapes[SCENARIO]) {
    throw new Error(`Unknown SCENARIO "${SCENARIO}". Use: ${Object.keys(shapes).join(', ')}`);
}

export const options = {
    scenarios: {
        students: { executor: 'ramping-vus', startVUs: 0, stages: shapes[SCENARIO], gracefulRampDown: '30s' },
    },
    thresholds: {
        http_req_duration: ['p(95)<3000'],
        http_req_failed: ['rate<0.01'],
        pages_ok_within_3s: ['rate>0.95'],
        // Per page, so a single slow page can't hide behind fast ones.
        'http_req_duration{page:member dashboard}': ['p(95)<3000'],
        'http_req_duration{page:bulletin}': ['p(95)<3000'],
        'http_req_duration{page:club page}': ['p(95)<3000'],
        'http_req_duration{page:clubs list}': ['p(95)<3000'],
        'http_req_duration{page:activity}': ['p(95)<3000'],
        'http_req_duration{page:fees tab}': ['p(95)<3000'],
        'http_req_duration{page:officer dashboard}': ['p(95)<3000'],
        'http_req_duration{page:officer fees}': ['p(95)<3000'],
        'http_req_duration{page:officer activities}': ['p(95)<3000'],
    },
    summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'max'],
    userAgent: `ClubSync-loadtest/1.0 (k6; ${SCENARIO})`,
};

const MEMBER_PAGES = [
    { weight: 25, page: 'member dashboard', path: () => '/member/dashboard' },
    { weight: 25, page: 'bulletin', path: () => '/member/bulletin' },
    { weight: 20, page: 'club page', path: (s) => `/member/clubs/${s.clubId}` },
    { weight: 10, page: 'clubs list', path: () => '/member/clubs' },
    { weight: 12, page: 'activity', path: () => '/member/activity' },
    { weight: 8, page: 'fees tab', path: () => '/member/activity?tab=fees' },
];
const OFFICER_PAGES = [
    { weight: 30, page: 'officer dashboard', path: () => '/officer/dashboard' },
    { weight: 30, page: 'officer fees', path: () => '/officer/fees' },
    { weight: 25, page: 'officer activities', path: () => '/officer/activities' },
    { weight: 15, page: 'bulletin', path: () => '/member/bulletin' },
];

function pick(pages) {
    let roll = Math.random() * pages.reduce((sum, p) => sum + p.weight, 0);
    for (const p of pages) {
        roll -= p.weight;
        if (roll <= 0) return p;
    }
    return pages[pages.length - 1];
}

// Every 20th virtual user is a president; the rest are members. Sessions are reused
// round-robin when there are more virtual users than fake students.
function me() {
    const isOfficer = __VU % 20 === 0 && officers.length > 0;
    const pool = isOfficer ? officers : members;
    return { session: pool[(__VU - 1) % pool.length], pages: isOfficer ? OFFICER_PAGES : MEMBER_PAGES };
}

export default function () {
    const { session, pages } = me();
    const p = pick(pages);

    const res = http.get(`${BASE_URL}${p.path(session)}`, {
        // What a browser sends, so compressed responses are measured as students get them.
        headers: { Accept: 'text/html', 'Accept-Encoding': 'gzip, deflate' },
        // Replaces the copy k6 keeps from the last response, so exactly one is sent.
        cookies: { [session.cookieName]: { value: session.cookieValue, replace: true } },
        // A redirect here means the session was rejected (sent to /login): a failure.
        redirects: 0,
        timeout: '30s',
        tags: { page: p.page },
    });

    const loaded = check(res, { 'page loaded (200)': (r) => r.status === 200 });
    withinTarget.add(loaded && res.timings.duration < 3000);

    sleep(THINK_MIN + Math.random() * (THINK_MAX - THINK_MIN));
}
