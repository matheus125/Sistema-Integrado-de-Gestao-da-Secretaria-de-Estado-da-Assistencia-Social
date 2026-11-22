import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import test from 'node:test'
import {
  decimalMoneyToMinorDigits,
  formatBrlCurrency,
  isValidBrlCurrencyValue,
  moneyCaretForDigitsAfter,
  moneyDigitsAfterCaret,
  normalizeDecimalMoney,
  parseBrlCurrencyInput,
  removeDecimalMoneyDigit
} from '../src/utils/brl-currency.js'

test('formata decimal canonico como moeda brasileira', () => {
  assert.equal(formatBrlCurrency('1234.56'), 'R$ 1.234,56')
  assert.equal(formatBrlCurrency('0.10'), 'R$ 0,10')
  assert.equal(formatBrlCurrency('9999999999.99'), 'R$ 9.999.999.999,99')
  assert.equal(formatBrlCurrency(null), '')
})

test('digitacao progressiva produz string decimal de duas casas', () => {
  assert.equal(parseBrlCurrencyInput('1'), '0.01')
  assert.equal(parseBrlCurrencyInput('12'), '0.12')
  assert.equal(parseBrlCurrencyInput('123456'), '1234.56')
})

test('colagem BRL e decimal de API preservam os centavos', () => {
  assert.equal(parseBrlCurrencyInput('R$ 1.234,56'), '1234.56')
  assert.equal(parseBrlCurrencyInput('1.234,56'), '1234.56')
  assert.equal(parseBrlCurrencyInput('1234.5'), '1234.50')
  assert.equal(normalizeDecimalMoney('001234.50'), '1234.50')
})

test('valida precisao e limite sem converter para ponto flutuante', async () => {
  const utility = await readFile(new URL('../src/utils/brl-currency.js', import.meta.url), 'utf8')

  assert.equal(isValidBrlCurrencyValue('R$ 9.999.999.999,99', 10), true)
  assert.equal(isValidBrlCurrencyValue('10000000000.00', 10), false)
  assert.equal(isValidBrlCurrencyValue('1.001', 10), false)
  assert.equal(normalizeDecimalMoney('0.30000000000000004'), null)
  assert.doesNotMatch(utility, /Number\(|parseFloat\(/)
})

test('mantem a posicao relativa do cursor ao aplicar agrupamento', () => {
  const formatted = 'R$ 1.234,56'

  assert.equal(moneyDigitsAfterCaret(formatted, 6), 4)
  assert.equal(moneyCaretForDigitsAfter(formatted, 4), 6)
  assert.equal(moneyCaretForDigitsAfter(formatted, 0), formatted.length)
})

test('exclusao remove somente o digito monetario selecionado', () => {
  assert.equal(decimalMoneyToMinorDigits('1234.56'), '123456')
  assert.equal(removeDecimalMoneyDigit('1234.56', 1), '134.56')
  assert.equal(removeDecimalMoneyDigit('0.01', 2), '0.00')
})

test('componente compartilhado envia decimal canonico nos dois formularios', async () => {
  const component = await readFile(new URL('../src/components/BrlCurrencyInput.vue', import.meta.url), 'utf8')
  const pages = await Promise.all([
    'MaterialsPage.vue',
    'TransportPage.vue'
  ].map(file => readFile(new URL(`../src/pages/${file}`, import.meta.url), 'utf8')))

  assert.match(component, /inputmode="decimal"/)
  assert.match(component, /emit\('update:modelValue', decimal\)/)
  assert.match(component, /@keydown="handleKeydown"/)
  pages.forEach(page => assert.match(page, /<BrlCurrencyInput/))
})
