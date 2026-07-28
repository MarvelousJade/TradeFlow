import { mkdir, mkdtemp, writeFile } from 'node:fs/promises';
import { join, resolve } from 'node:path';
import { launch } from 'chrome-launcher';
import lighthouse from 'lighthouse';
import desktopConfig from 'lighthouse/core/config/desktop-config.js';

const baseUrl = (process.env.LIGHTHOUSE_BASE_URL ?? 'http://localhost:8080').replace(
  /\/$/,
  '',
);
const pages = [
  { name: 'home', url: `${baseUrl}/` },
  {
    name: 'drain-repair-toronto',
    url: `${baseUrl}/services/drain-repair/toronto/`,
  },
];
const root = resolve('.lighthouseci');
const reportDir = join(root, 'reports');
await mkdir(reportDir, { recursive: true });

const rows = [];
let failed = false;

for (const page of pages) {
  const profile = await mkdtemp(join(root, `chrome-${page.name}-`));
  const chrome = await launch({
    chromeFlags: ['--headless=new', '--no-sandbox', '--disable-gpu'],
    userDataDir: profile,
    logLevel: 'silent',
  });

  try {
    const result = await lighthouse(
      page.url,
      {
        port: chrome.port,
        output: ['json', 'html'],
        logLevel: 'error',
      },
      desktopConfig,
    );
    if (!result) throw new Error(`Lighthouse returned no report for ${page.url}`);

    const [json, html] = result.report;
    await writeFile(join(reportDir, `${page.name}.json`), json);
    await writeFile(join(reportDir, `${page.name}.html`), html);

    const category = (name) => Math.round(result.lhr.categories[name].score * 100);
    const metric = (name) => result.lhr.audits[name]?.numericValue;
    const row = {
      page: page.url,
      performance: category('performance'),
      accessibility: category('accessibility'),
      seo: category('seo'),
      lcpMs: Math.round(metric('largest-contentful-paint')),
      cls: Number(metric('cumulative-layout-shift').toFixed(3)),
      tbtMs: Math.round(metric('total-blocking-time')),
    };
    rows.push(row);

    const inp = metric('interaction-to-next-paint');
    const budgetsPass =
      row.performance >= 90 &&
      row.accessibility >= 90 &&
      row.seo >= 90 &&
      row.lcpMs < 2500 &&
      row.cls < 0.1 &&
      row.tbtMs <= 200 &&
      (inp === undefined || inp < 200);
    failed ||= !budgetsPass;
  } finally {
    await chrome.kill();
  }
}

console.table(rows);
console.log(`Reports: ${reportDir}`);
if (failed) {
  console.error('One or more Lighthouse budgets failed.');
  process.exitCode = 1;
}

