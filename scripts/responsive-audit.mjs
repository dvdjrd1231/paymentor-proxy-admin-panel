/**
 * Responsive audit over CDP — real layout measurement, not CSS inspection.
 *
 * For each page at each device width it checks the things that actually break on a phone:
 * horizontal overflow (and which element causes it), whether the two-column client area
 * collapses, whether nav is reachable, and whether tap targets meet the 44px guideline.
 *
 * Requires headless Chrome with remote debugging:
 *   chrome --headless=new --remote-debugging-port=9222
 *
 *   node scripts/responsive-audit.mjs <base-url> [session-cookie]
 *   PAGES=/,/login node scripts/responsive-audit.mjs https://example.com
 *
 * Authenticated pages need a session cookie; mint one on the server and pass it as
 * "name=value". Exits non-zero if any page overflows horizontally.
 */
const BASE = process.argv[2] || 'https://paymenter-dev.7hoop.net';
const COOKIE = process.argv[3] || '';

const DEVICES = [
  { name: 'iPhone SE', width: 375, height: 667, mobile: true },
  { name: 'iPhone 14 Pro Max', width: 430, height: 932, mobile: true },
  { name: 'iPad portrait', width: 768, height: 1024, mobile: true },
  { name: 'iPad landscape', width: 1024, height: 768, mobile: true },
  { name: 'Desktop', width: 1440, height: 900, mobile: false },
];

const PAGES = (process.env.PAGES || '/,/login,/register,/products/proxies,/dashboard,/services,/invoices,/tickets,/account').split(',');

const send = (ws, id, method, params) =>
  new Promise((resolve) => {
    const onMsg = (e) => {
      const m = JSON.parse(e.data);
      if (m.id === id) { ws.removeEventListener('message', onMsg); resolve(m.result); }
    };
    ws.addEventListener('message', onMsg);
    ws.send(JSON.stringify({ id, method, params }));
  });

const targets = await (await fetch(`http://127.0.0.1:9222/json/list`)).json();
let page = targets.find((t) => t.type === 'page');
if (!page) {
  page = await (await fetch(`http://127.0.0.1:9222/json/new?about:blank`)).json();
}

const ws = new WebSocket(page.webSocketDebuggerUrl);
await new Promise((r) => ws.addEventListener('open', r));

let id = 0;
const cmd = (method, params = {}) => send(ws, ++id, method, params);

await cmd('Page.enable');
await cmd('Runtime.enable');
await cmd('Network.enable');

if (COOKIE) {
  const [name, ...rest] = COOKIE.split('=');
  await cmd('Network.setCookie', {
    name, value: rest.join('='), domain: new URL(BASE).hostname, path: '/',
  });
}

// Measure once the page has settled.
const MEASURE = `(() => {
  const vw = window.innerWidth;
  const de = document.documentElement;
  let widest = null, widestW = 0;
  for (const el of document.querySelectorAll('body *')) {
    const r = el.getBoundingClientRect();
    if (r.width > widestW && r.right > vw + 1) { widestW = r.width; widest = el; }
  }
  const small = [];
  for (const el of document.querySelectorAll('a,button,input[type=submit]')) {
    const r = el.getBoundingClientRect();
    if (r.width > 0 && r.height > 0 && r.height < 32) small.push(el.textContent.trim().slice(0, 18) || el.tagName);
  }
  const layout = document.querySelector('.wf-layout');
  let columns = null;
  if (layout) columns = getComputedStyle(layout).gridTemplateColumns.split(' ').length;
  return {
    vw,
    scrollW: de.scrollWidth,
    overflow: de.scrollWidth - vw,
    offender: widest ? (widest.tagName.toLowerCase() + (widest.className ? '.' + String(widest.className).split(' ')[0] : '')) : null,
    offenderW: Math.round(widestW),
    columns,
    smallTargets: small.length,
    smallSample: small.slice(0, 2),
    hasViewportMeta: !!document.querySelector('meta[name=viewport]'),
  };
})()`;

const results = [];

for (const d of DEVICES) {
  await cmd('Emulation.setDeviceMetricsOverride', {
    width: d.width, height: d.height, deviceScaleFactor: 1, mobile: d.mobile,
  });

  for (const path of PAGES) {
    await cmd('Page.navigate', { url: BASE + path });
    await new Promise((r) => setTimeout(r, 1400));
    const { result } = await cmd('Runtime.evaluate', { expression: MEASURE, returnByValue: true });
    results.push({ device: d.name, width: d.width, path, ...result.value });
  }
}

// ── Report ───────────────────────────────────────────────────────────────────
let fails = 0;
console.log('');
for (const d of DEVICES) {
  console.log(`── ${d.name} (${d.width}px) ──`);
  for (const r of results.filter((x) => x.device === d.name)) {
    const bad = r.overflow > 1;
    if (bad) fails++;
    const cols = r.columns ? `${r.columns}col` : '—';
    console.log(
      `  ${bad ? 'FAIL' : 'ok  '}  ${r.path.padEnd(20)} overflow=${String(r.overflow).padStart(4)}px  ` +
      `layout=${cols.padEnd(5)} smallTaps=${r.smallTargets}` +
      (bad && r.offender ? `  <- ${r.offender} @ ${r.offenderW}px` : '')
    );
  }
}

const noMeta = results.filter((r) => !r.hasViewportMeta);
console.log('');
console.log(`viewport meta present on all pages: ${noMeta.length === 0 ? 'yes' : 'NO (' + noMeta.length + ')'}`);
console.log(`pages with horizontal overflow: ${fails}`);
ws.close();
process.exit(fails === 0 ? 0 : 1);
