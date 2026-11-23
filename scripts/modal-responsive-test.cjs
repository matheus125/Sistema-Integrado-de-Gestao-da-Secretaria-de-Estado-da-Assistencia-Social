const fs = require('node:fs');
const path = require('node:path');

const playwrightModule = process.env.PLAYWRIGHT_MODULE || 'playwright';
const { chromium } = require(playwrightModule);

const base = process.env.SIGES_E2E_URL || 'http://127.0.0.1:9000';
const apiBase = process.env.SIGES_E2E_API_URL || 'http://127.0.0.1:8000/api';
const email = process.env.SIGES_E2E_EMAIL;
const password = process.env.SIGES_E2E_PASSWORD;
const output = path.resolve(process.env.SIGES_E2E_OUTPUT || 'backups/modal-validation');
const viewports = [
  { name: 'mobile-375', width: 375, height: 667 },
  { name: 'tablet-768', width: 768, height: 900 },
  { name: 'desktop', width: 1440, height: 1000 }
];
const desktop = viewports.at(-1);

if (!email || !password) throw new Error('Supply SIGES_E2E_EMAIL and SIGES_E2E_PASSWORD');

fs.mkdirSync(output, { recursive: true, mode: 0o700 });

function slug(value) {
  return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

async function clickTab(page, name) {
  await page.getByRole('tab', { name, exact: true }).click();
  await page.waitForTimeout(150);
}

async function button(page, name) {
  return page.getByRole('button', { name, exact: true }).first();
}

async function navigate(page, route) {
  await page.setViewportSize(desktop);
  await page.goto(`${base}/#/${route}`);
  await page.waitForLoadState('networkidle');
  if (page.url().includes('/login')) throw new Error(`Unexpected login redirect: ${route}`);
}

function cases() {
  return [
    { name: 'Busca global', route: '', open: async page => { const trigger = page.locator('input[placeholder="Buscar no SIGES"]'); await trigger.fill('modal'); await trigger.press('Enter'); return trigger; } },
    { name: 'Notificações', route: '', open: async page => { const trigger = await button(page, 'Abrir notificações'); await trigger.click(); return trigger; } },
    { name: 'Sessões', route: '', open: async page => { const menu = await button(page, 'Abrir menu do usuário'); await menu.evaluate(element => element.setAttribute('data-modal-test-fallback', 'Sessões')); await menu.click(); const trigger = page.getByRole('button', { name: 'Dispositivos e sessoes', exact: true }).first(); await trigger.click(); return trigger; } },
    { name: 'Novo usuário', route: 'administracao', open: async page => { const trigger = await button(page, 'Novo usuario'); await trigger.click(); return trigger; } },
    { name: 'Novo setor', route: 'administracao', open: async page => { const trigger = await button(page, 'Novo setor'); await trigger.click(); return trigger; } },
    { name: 'Novo perfil', route: 'administracao', open: async page => { const trigger = await button(page, 'Novo perfil'); await trigger.click(); return trigger; } },
    { name: 'Abrir chamado', route: 'informatica', open: async page => { const trigger = await button(page, 'Abrir chamado'); await trigger.click(); return trigger; } },
    { name: 'Nova categoria', route: 'informatica', open: async page => { await clickTab(page, 'Categorias'); const trigger = await button(page, 'Nova categoria'); await trigger.click(); return trigger; } },
    { name: 'Nova regra SLA', route: 'informatica', open: async page => { await clickTab(page, 'SLA'); const trigger = await button(page, 'Nova regra'); await trigger.click(); return trigger; } },
    { name: 'Novo calendário SLA', route: 'informatica', persistent: true, open: async page => { await clickTab(page, 'SLA'); const trigger = await button(page, 'Novo calendário'); await trigger.click(); return trigger; } },
    { name: 'Novo checklist', route: 'informatica', open: async page => { await clickTab(page, 'Checklists'); const trigger = await button(page, 'Novo template'); await trigger.click(); return trigger; } },
    { name: 'Novo template de resposta', route: 'informatica', open: async page => { await clickTab(page, 'Respostas'); const trigger = await button(page, 'Novo template'); await trigger.click(); return trigger; } },
    { name: 'Nova automação', route: 'informatica', open: async page => { await clickTab(page, 'Automações'); const trigger = await button(page, 'Nova regra'); await trigger.click(); return trigger; } },
    { name: 'Novo serviço', route: 'informatica', open: async page => { await clickTab(page, 'Catálogo'); const trigger = await button(page, 'Novo serviço'); await trigger.click(); return trigger; } },
    { name: 'Detalhe do chamado', route: 'informatica', open: async page => { await clickTab(page, 'Chamados'); const trigger = await button(page, 'Abrir histórico e atendimento'); await trigger.click(); return trigger; } },
    { name: 'Novo plano preventivo', route: 'informatica/manutencao-preventiva', open: async page => { const trigger = await button(page, 'Novo plano'); await trigger.click(); return trigger; } },
    { name: 'Novo veículo', route: 'transporte', open: async page => { const trigger = await button(page, 'Novo veiculo'); await trigger.click(); return trigger; } },
    { name: 'Novo motorista', route: 'transporte', open: async page => { const trigger = await button(page, 'Novo motorista'); await trigger.click(); return trigger; } },
    { name: 'Nova manutenção', route: 'transporte', open: async page => { const trigger = await button(page, 'Manutencao'); await trigger.click(); return trigger; } },
    { name: 'Nova solicitação', route: 'transporte', open: async page => { const trigger = await button(page, 'Solicitar veiculo'); await trigger.click(); return trigger; } },
    { name: 'Detalhe da solicitação', route: 'transporte', open: async page => { await clickTab(page, 'Solicitacoes'); const trigger = await button(page, 'Detalhar solicitação'); await trigger.click(); return trigger; } },
    { name: 'Checklist de saída', route: 'transporte', nested: true, open: async page => { await clickTab(page, 'Solicitacoes'); await (await button(page, 'Detalhar solicitação')).click(); const trigger = page.locator('.q-dialog:visible').last().getByRole('button', { name: /em deslocamento/i }).first(); await trigger.click(); return trigger; } },
    { name: 'Novo produto', route: 'materiais', open: async page => { const trigger = await button(page, 'Novo produto'); await trigger.click(); return trigger; } },
    { name: 'Movimentar estoque', route: 'materiais', open: async page => { const trigger = await button(page, 'Movimentar estoque'); await trigger.click(); return trigger; } },
    { name: 'Novo patrimônio', route: 'materiais', open: async page => { const trigger = await button(page, 'Novo patrimonio'); await trigger.click(); return trigger; } },
    { name: 'Movimentar patrimônio', route: 'materiais', open: async page => { await clickTab(page, 'Movimentacoes'); const trigger = await button(page, 'Nova movimentacao'); await trigger.click(); return trigger; } },
    { name: 'Detalhe da auditoria', route: 'auditoria', open: async page => { const trigger = await button(page, 'Ver detalhes da auditoria'); await trigger.click(); return trigger; } }
  ];
}

async function visibleDialog(page, minimumCount = 1) {
  const visible = page.locator('.q-dialog:visible');
  await page.waitForFunction(count => document.querySelectorAll('.q-dialog:has(.q-dialog__inner)').length >= count, minimumCount);
  await visible.nth(minimumCount - 1).waitFor({ state: 'visible' });
  await page.waitForTimeout(350);
  await page.evaluate(() => {
    const dialogs = [...document.querySelectorAll('.q-dialog')].filter(element => element.getClientRects().length > 0);
    dialogs.forEach(element => element.removeAttribute('data-modal-test-target'));
    const focused = dialogs.find(element => element.contains(document.activeElement));
    const target = focused || dialogs.at(-1);
    target?.setAttribute('data-modal-test-target', 'true');
  });
  return page.locator('.q-dialog[data-modal-test-target="true"]');
}

async function measure(page, dialog) {
  return dialog.evaluate(root => {
    const card = root.querySelector('.q-card');
    const inner = card?.closest('.q-dialog__inner');
    if (!card || !inner) return { valid: false, reason: 'dialog card not found' };
    const rect = card.getBoundingClientRect();
    const cardStyle = getComputedStyle(card);
    const innerStyle = getComputedStyle(inner);
    const verticalOverflow = card.scrollHeight > card.clientHeight + 2;
    const scrollable = !verticalOverflow || ['auto', 'scroll'].includes(cardStyle.overflowY) || ['auto', 'scroll'].includes(innerStyle.overflowY);
    const focusedInside = root.contains(document.activeElement);
    return {
      valid: true,
      bounds: rect.left >= -1 && rect.top >= -1 && rect.right <= innerWidth + 1 && rect.bottom <= innerHeight + 1,
      documentOverflow: document.documentElement.scrollWidth > innerWidth + 1,
      horizontalCut: card.scrollWidth > card.clientWidth + 2 && !['auto', 'scroll'].includes(cardStyle.overflowX),
      verticalOverflow,
      scrollable,
      focusedInside,
      rect: { left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom, width: rect.width, height: rect.height },
      viewport: { width: innerWidth, height: innerHeight }
      , activeElement: {
        tag: document.activeElement?.tagName,
        className: document.activeElement?.className,
        ariaLabel: document.activeElement?.getAttribute('aria-label')
      }
    };
  });
}

async function closeAllDialogs(page) {
  for (let index = 0; index < 4 && await page.locator('.q-dialog:visible').count(); index += 1) {
    await page.keyboard.press('Escape');
    await page.waitForTimeout(400);
    if (await page.locator('.q-dialog:visible').count()) {
      const cancel = page.locator('.q-dialog:visible').last().getByRole('button', { name: /Cancelar|Fechar/ }).first();
      if (await cancel.count()) {
        await cancel.click({ timeout: 2000 }).catch(() => {});
        await page.waitForTimeout(400);
      }
    }
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.SIGES_CHROMIUM });
  const page = await browser.newPage({ viewport: desktop });
  const results = [];
  const failures = [];
  const runtimeErrors = [];
  const filter = process.env.SIGES_MODAL_FILTER;
  const selectedCases = filter
    ? cases().filter(testCase => testCase.name.toLocaleLowerCase('pt-BR').includes(filter.toLocaleLowerCase('pt-BR')))
    : cases();

  if (!selectedCases.length) throw new Error(`No modal matched SIGES_MODAL_FILTER=${filter}`);

  page.on('pageerror', error => runtimeErrors.push(error.message));
  page.on('response', response => {
    if (response.status() >= 500) runtimeErrors.push(`${response.status()} ${new URL(response.url()).pathname}`);
  });

  try {
    await page.goto(`${base}/#/login`);
    await page.locator('input[type=email]').fill(email);
    await page.locator('input[type=password]').fill(password);
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await page.waitForURL(url => !url.hash.includes('/login'));

    for (const testCase of selectedCases) {
      console.log(`Testing: ${testCase.name}`);
      await closeAllDialogs(page);
      await navigate(page, testCase.route);
      const trigger = await testCase.open(page);
      await trigger.evaluate((element, name) => element.setAttribute('data-modal-test-trigger', name), testCase.name);
      const dialog = await visibleDialog(page, testCase.nested ? 2 : 1);
      // Quasar moves focus after the dialog transition finishes.
      await page.waitForTimeout(450);
      const sizes = [];

      for (const viewport of viewports) {
        await page.setViewportSize(viewport);
        await page.waitForTimeout(120);
        const metrics = await measure(page, dialog);
        sizes.push({ name: viewport.name, ...metrics });
        if (!metrics.valid || !metrics.bounds || metrics.documentOverflow || metrics.horizontalCut || !metrics.scrollable || !metrics.focusedInside) {
          failures.push({ modal: testCase.name, viewport: viewport.name, metrics });
          console.log(`Failure: ${testCase.name} / ${viewport.name} ${JSON.stringify(metrics)}`);
          await page.screenshot({ path: path.join(output, `${slug(testCase.name)}-${viewport.name}.png`), fullPage: true });
        }
      }

      await page.setViewportSize(desktop);
      await page.waitForTimeout(100);
      for (let index = 0; index < 12; index += 1) {
        await page.keyboard.press('Tab');
        // Focus guards redirect asynchronously after the browser reaches a sentinel.
        await page.waitForTimeout(30);
        const trapped = await dialog.evaluate(root => root.contains(document.activeElement));
        if (!trapped) {
          const activeElement = await page.evaluate(() => ({ tag: document.activeElement?.tagName, className: document.activeElement?.className, ariaLabel: document.activeElement?.getAttribute('aria-label') }));
          failures.push({ modal: testCase.name, viewport: 'desktop', reason: `focus escaped after Tab ${index + 1}`, activeElement });
          console.log(`Failure: ${testCase.name} / focus escaped after Tab ${index + 1} ${JSON.stringify(activeElement)}`);
        }
      }

      if (testCase.persistent) {
        await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
      } else {
        await page.keyboard.press('Escape');
        await dialog.waitFor({ state: 'hidden', timeout: 1000 }).catch(() => {});
        if (await dialog.isVisible().catch(() => false)) {
          const close = dialog.getByRole('button', { name: /Fechar|Cancelar/ }).first();
          if (await close.count()) await close.click();
        }
      }
      await dialog.waitFor({ state: 'hidden' });
      await page.waitForTimeout(150);
      const restored = await page.evaluate(name => {
        const active = document.activeElement;
        return active?.closest?.('[data-modal-test-trigger]')?.getAttribute('data-modal-test-trigger') === name
          || active?.closest?.('[data-modal-test-fallback]')?.getAttribute('data-modal-test-fallback') === name;
      }, testCase.name);
      if (!restored) {
        const activeElement = await page.evaluate(() => ({ tag: document.activeElement?.tagName, className: document.activeElement?.className, ariaLabel: document.activeElement?.getAttribute('aria-label'), text: document.activeElement?.textContent?.trim().slice(0, 80) }));
        failures.push({ modal: testCase.name, viewport: 'desktop', reason: 'focus was not restored to trigger', activeElement });
        console.log(`Failure: ${testCase.name} / focus was not restored ${JSON.stringify(activeElement)}`);
      }
      results.push({ modal: testCase.name, sizes, focusTrapped: !failures.some(item => item.modal === testCase.name && String(item.reason || '').includes('focus escaped')), focusRestored: restored });
      await closeAllDialogs(page);
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

  const report = { generatedAt: new Date().toISOString(), base, viewports, modals: results.length, checks: results.length * viewports.length, results, failures, runtimeErrors };
  fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify(report, null, 2), { mode: 0o600 });
  console.log(JSON.stringify({ modals: report.modals, checks: report.checks, failures: failures.length, runtimeErrors }));
  if (failures.length || runtimeErrors.length || results.length !== selectedCases.length) process.exitCode = 1;
})().catch(error => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
