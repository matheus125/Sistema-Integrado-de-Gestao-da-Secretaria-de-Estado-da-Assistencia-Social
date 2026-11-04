// Read-only navigation, except login/logout audit records and the session token.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs');
const path = require('node:path');

(async () => {
  if (!process.env.SIGES_E2E_EMAIL || !process.env.SIGES_E2E_PASSWORD) throw Error('Supply SIGES_E2E_EMAIL and SIGES_E2E_PASSWORD');
  const output = path.resolve(process.env.SIGES_E2E_OUTPUT || 'backups/browser-validation');
  fs.mkdirSync(output, { recursive: true, mode: 0o700 });
  const browser = await chromium.launch({ headless: true, executablePath: process.env.SIGES_CHROMIUM });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', response => {
    if (response.status() >= 400) errors.push(`${response.status()} ${new URL(response.url()).pathname}`);
  });
  const base = process.env.SIGES_E2E_URL || 'http://127.0.0.1:9000';
  const results = [];
  try {
    await page.goto(base + '/#/login');
    await page.locator('input[type=email]').fill(process.env.SIGES_E2E_EMAIL);
    await page.locator('input[type=password]').fill(process.env.SIGES_E2E_PASSWORD);
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await page.waitForURL(url => !url.hash.includes('/login'));
    for (const route of ['', 'administracao', 'transporte', 'materiais', 'informatica', 'informatica/manutencao-preventiva', 'auditoria', 'relatorios', 'configuracoes']) {
      await page.goto(base + '/#/' + route);
      await page.waitForLoadState('networkidle');
      if (page.url().includes('/login')) throw Error(`Unexpected login redirect: ${route}`);
      await page.screenshot({ path: path.join(output, (route.replaceAll('/', '-') || 'dashboard') + '.png'), fullPage: true });
      results.push({ route: route || 'dashboard', rendered: (await page.locator('body').innerText()).length > 100 });
      if (route === 'relatorios') {
        for (const label of ['CSV', 'Excel', 'PDF']) {
          const downloadReady = page.waitForEvent('download');
          await page.getByRole('button', { name: label, exact: true }).click();
          const download = await downloadReady;
          await download.saveAs(path.join(output, download.suggestedFilename()));
          if (await download.failure()) throw Error('Download failed: ' + label);
        }
      }
    }
    const apiChecks = await page.evaluate(async () => {
      const api = location.port === '9000' ? 'http://127.0.0.1:8000/api' : '/api';
      const headers = { Authorization: `Bearer ${localStorage.getItem('siges_token')}`, Accept: 'application/json' };
      const get = async path => {
        const response = await fetch(api + path, { headers });
        if (!response.ok) throw Error(`API ${response.status}: ${path}`);
        return response;
      };
      const options = await (await get('/reports/options')).json();
      for (const report of options.reports) await get('/reports/preview?report=' + report.value);
      const tickets = await (await get('/it/tickets')).json();
      let attachments = 0;
      for (const ticket of tickets.data) {
        const files = await (await get('/attachments/it-ticket/' + ticket.id)).json();
        for (const file of files.data) {
          const response = await get('/attachments/' + file.id + '/download');
          if ((await response.arrayBuffer()).byteLength !== file.size) throw Error('Attachment size mismatch');
          attachments++;
        }
      }
      return { reports: options.reports.length, attachments };
    });
    fs.writeFileSync(path.join(output, 'api-checks.json'), JSON.stringify(apiChecks, null, 2), { mode: 0o600 });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(base + '/#/');
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: path.join(output, 'dashboard-mobile.png'), fullPage: true });
  } finally {
    await page.evaluate(async () => {
      const token = localStorage.getItem('siges_token');
      if (token) {
        const api = location.port === '9000' ? 'http://127.0.0.1:8000/api' : '/api';
        await fetch(api + '/auth/logout', { method: 'POST', headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } });
      }
      localStorage.clear();
    }).catch(() => {});
    await browser.close();
    fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify({ results, errors }, null, 2), { mode: 0o600 });
  }
  console.log(JSON.stringify({ pages: results.length, errors }));
  if (errors.length || results.some(result => !result.rendered)) process.exitCode = 1;
})().catch(error => { console.error(error.message); process.exitCode = 1; });
