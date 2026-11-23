const fs = require('node:fs');
const path = require('node:path');

const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const base = process.env.SIGES_E2E_URL || 'http://127.0.0.1:9000';
const apiBase = process.env.SIGES_E2E_API_URL || 'http://127.0.0.1:8000/api';
const email = process.env.SIGES_E2E_EMAIL;
const password = process.env.SIGES_E2E_PASSWORD;
const axePath = process.env.AXE_CORE_PATH;
const output = path.resolve(process.env.SIGES_E2E_OUTPUT || 'backups/accessibility-validation');

const routes = [
  { name: 'dashboard', path: '' },
  { name: 'administracao', path: 'administracao' },
  { name: 'transporte', path: 'transporte' },
  { name: 'materiais', path: 'materiais' },
  { name: 'informatica', path: 'informatica' },
  { name: 'manutencao-preventiva', path: 'informatica/manutencao-preventiva' },
  { name: 'auditoria', path: 'auditoria' },
  { name: 'relatorios', path: 'relatorios' },
  { name: 'configuracoes', path: 'configuracoes' }
];

const viewports = [
  { name: 'desktop', width: 1440, height: 1000 },
  { name: 'mobile-375', width: 375, height: 667 }
];

if (!email || !password) throw new Error('Supply SIGES_E2E_EMAIL and SIGES_E2E_PASSWORD');
if (!axePath || !fs.existsSync(axePath)) throw new Error('Supply AXE_CORE_PATH pointing to axe.min.js');

const axeSource = fs.readFileSync(axePath, 'utf8');
fs.mkdirSync(output, { recursive: true, mode: 0o700 });

function summarizeViolation(violation) {
  return {
    id: violation.id,
    impact: violation.impact,
    help: violation.help,
    helpUrl: violation.helpUrl,
    nodes: violation.nodes.map(node => ({ target: node.target, html: node.html, failureSummary: node.failureSummary }))
  };
}

async function runAxe(page, pageName, viewportName) {
  await page.addScriptTag({ content: axeSource });
  const result = await page.evaluate(async () => {
    return window.axe.run(document, {
      runOnly: {
        type: 'tag',
        values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']
      },
      resultTypes: ['violations', 'incomplete']
    });
  });

  return {
    page: pageName,
    viewport: viewportName,
    violations: result.violations.map(summarizeViolation),
    incomplete: result.incomplete.map(summarizeViolation)
  };
}

async function waitForStableUi(page) {
  await page.locator('.q-notification').last().waitFor({ state: 'hidden', timeout: 7000 }).catch(() => {});
  await page.waitForFunction(() => {
    return ![...document.querySelectorAll('.q-btn--loading, .q-inner-loading')]
      .some(element => element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden');
  }, null, { timeout: 10000 }).catch(() => {});
  await page.waitForTimeout(100);
}

async function checkKeyboard(page, pageName, viewportName) {
  await page.evaluate(() => document.activeElement?.blur());
  const stops = [];
  const failures = [];
  const stopCount = await page.evaluate(() => {
    const selector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [role="button"], [tabindex]:not([tabindex="-1"])';
    return [...document.querySelectorAll(selector)].filter(element => {
      const rect = element.getBoundingClientRect();
      const style = getComputedStyle(element);
      return rect.width > 0 && rect.height > 0 && rect.right > 0 && rect.bottom > 0
        && rect.left < innerWidth && rect.top < innerHeight
        && style.visibility !== 'hidden' && style.display !== 'none';
    }).length;
  });

  for (let index = 0; index < Math.min(stopCount, 12); index += 1) {
    await page.keyboard.press('Tab');
    await page.waitForTimeout(25);
    const stop = await page.evaluate(() => {
      const element = document.activeElement;
      if (!element) return null;
      const rect = element.getBoundingClientRect();
      const style = getComputedStyle(element);
      const field = element.closest('.q-field');
      const focusVisible = element.matches(':focus-visible')
        || field?.classList.contains('q-field--focused')
        || (style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0);
      return {
        tag: element.tagName,
        ariaLabel: element.getAttribute('aria-label'),
        text: element.textContent?.trim().replace(/\s+/g, ' ').slice(0, 80) || '',
        visible: rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none',
        focusVisible
      };
    });
    stops.push(stop);
    if (stop?.tag === 'BODY' && stops.length > 1) break;
    if (!stop || stop.tag === 'BODY' || !stop.visible || !stop.focusVisible) {
      failures.push({ step: index + 1, stop });
    }
  }

  return { page: pageName, viewport: viewportName, stops, failures };
}

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.SIGES_CHROMIUM });
  const context = await browser.newContext({ viewport: viewports[0], bypassCSP: true });
  const page = await context.newPage();
  const scans = [];
  const keyboard = [];
  const runtimeErrors = [];

  page.on('pageerror', error => runtimeErrors.push(error.message));
  page.on('response', response => {
    if (response.status() >= 500) runtimeErrors.push(`${response.status()} ${new URL(response.url()).pathname}`);
  });

  try {
    await page.goto(`${base}/#/login`);
    await page.waitForLoadState('networkidle');
    await waitForStableUi(page);
    scans.push(await runAxe(page, 'login', 'desktop'));
    keyboard.push(await checkKeyboard(page, 'login', 'desktop'));

    await page.locator('input[type=email]').fill(email);
    await page.locator('input[type=password]').fill(password);
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await page.waitForURL(url => !url.hash.includes('/login'));

    for (const viewport of viewports) {
      await page.setViewportSize(viewport);
      for (const route of routes) {
        await page.goto(`${base}/#/${route.path}`);
        await page.waitForLoadState('networkidle');
        if (page.url().includes('/login')) throw new Error(`Unexpected login redirect: ${route.path}`);
        await waitForStableUi(page);
        scans.push(await runAxe(page, route.name, viewport.name));
        keyboard.push(await checkKeyboard(page, route.name, viewport.name));
      }
    }
  } finally {
    await page.evaluate(async api => {
      const token = sessionStorage.getItem('siges_token');
      if (token) {
        await fetch(`${api}/auth/logout`, { method: 'POST', headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } });
      }
      sessionStorage.clear();
      localStorage.clear();
    }, apiBase).catch(() => {});
    await browser.close();
  }

  const violations = scans.flatMap(scan => scan.violations.map(violation => ({ page: scan.page, viewport: scan.viewport, ...violation })));
  const seriousOrCritical = violations.filter(violation => ['serious', 'critical'].includes(violation.impact));
  const keyboardFailures = keyboard.flatMap(check => check.failures.map(failure => ({ page: check.page, viewport: check.viewport, ...failure })));
  const report = {
    generatedAt: new Date().toISOString(),
    base,
    scans: scans.length,
    routes: routes.length + 1,
    viewports,
    violations,
    seriousOrCritical,
    keyboard,
    keyboardFailures,
    runtimeErrors
  };

  fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify(report, null, 2), { mode: 0o600 });
  console.log(JSON.stringify({
    scans: report.scans,
    violations: violations.length,
    seriousOrCritical: seriousOrCritical.length,
    keyboardFailures: keyboardFailures.length,
    runtimeErrors
  }));
  if (seriousOrCritical.length || keyboardFailures.length || runtimeErrors.length) process.exitCode = 1;
})().catch(error => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
