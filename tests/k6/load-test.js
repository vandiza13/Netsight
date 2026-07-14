import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 2 }, // Ramp up to 2 concurrent users
    { duration: '120s', target: 2 }, // Stay at 2 users for 2 minutes (max concurrency)
    { duration: '10s', target: 0 },  // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'], // 95% of requests must complete below 500ms
    http_req_failed: ['rate<0.01'],   // Error rate should be less than 1%
  },
};

const BASE_URL = __ENV.API_URL || 'https://netsight.local/api';
const TOKEN = __ENV.API_TOKEN || 'YOUR_TEST_TOKEN_HERE';
const ROUTER_ID = __ENV.ROUTER_ID || 1;
const TARGET_USER = __ENV.TARGET_USER || 'test-user';

export default function () {
  const headers = { 
    'Authorization': `Bearer ${TOKEN}`,
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  };

  // 1. Initiate Torch
  let inspectRes = http.post(`${BASE_URL}/torch/inspect`, JSON.stringify({
    router_id: ROUTER_ID,
    username: TARGET_USER
  }), { headers });

  check(inspectRes, {
    'inspect initiated successfully (200)': (r) => r.status === 200,
    'inspect rate limited properly (429)': (r) => r.status === 429,
  });

  if (inspectRes.status === 200) {
    const sessionTag = inspectRes.json('session_tag');

    // 2. Simulate Heartbeat (Every 5s) while SSE would be open
    for (let i = 0; i < 24; i++) { // 120 seconds = 24 * 5 seconds
      let hbRes = http.post(`${BASE_URL}/torch/${sessionTag}/heartbeat`, null, { headers });
      check(hbRes, { 'heartbeat successful': (r) => r.status === 200 });
      sleep(5);
    }

    // 3. Stop Inspection
    let cancelRes = http.post(`${BASE_URL}/torch/${sessionTag}/cancel`, null, { headers });
    check(cancelRes, { 'cancel successful': (r) => r.status === 200 });
  } else {
    // If we get 429, it means the guardrail successfully blocked the 3rd user.
    sleep(1);
  }
}
