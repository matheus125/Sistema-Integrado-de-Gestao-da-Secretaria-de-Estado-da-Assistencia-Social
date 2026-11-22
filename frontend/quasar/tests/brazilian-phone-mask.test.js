import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import test from 'node:test'
import {
  formatBrazilianPhone,
  isValidBrazilianPhone,
  normalizeBrazilianPhoneInput,
  phoneCaretForDigitIndex,
  phoneDigitIndexAtCaret,
  removeBrazilianPhoneDigit
} from '../src/utils/brazilian-phone-mask.js'

test('formata telefone fixo e celular durante digitacao', () => {
  assert.equal(formatBrazilianPhone('9232321234'), '(92) 3232-1234')
  assert.equal(formatBrazilianPhone('92999991234'), '(92) 99999-1234')
  assert.equal(formatBrazilianPhone('92999'), '(92) 999')
})

test('normaliza colagem local e com codigo do Brasil', () => {
  assert.equal(normalizeBrazilianPhoneInput('(92) 99999-1234'), '92999991234')
  assert.equal(normalizeBrazilianPhoneInput('+55 (92) 99999-1234'), '92999991234')
  assert.equal(normalizeBrazilianPhoneInput('929999912345678'), '92999991234')
})

test('valida DDD e estrutura de fixo e celular como o backend', () => {
  assert.equal(isValidBrazilianPhone('(92) 3232-1234'), true)
  assert.equal(isValidBrazilianPhone('(92) 99999-1234'), true)
  assert.equal(isValidBrazilianPhone('(20) 99999-1234'), false)
  assert.equal(isValidBrazilianPhone('(92) 89999-1234'), false)
  assert.equal(isValidBrazilianPhone('(92) 1232-1234'), false)
})

test('mantem o cursor associado ao mesmo digito depois da mascara', () => {
  const formatted = '(92) 99999-1234'

  assert.equal(phoneDigitIndexAtCaret(formatted, 9), 6)
  assert.equal(phoneCaretForDigitIndex(formatted, 6), 9)
  assert.equal(phoneDigitIndexAtCaret('+55 (92) 99999-1234', 20), 11)
})

test('exclusao e edicao removem apenas o digito selecionado', () => {
  assert.equal(removeBrazilianPhoneDigit('92999991234', 1), '9999991234')
  assert.equal(removeBrazilianPhoneDigit('92999991234', 6), '9299991234')
  assert.equal(formatBrazilianPhone(removeBrazilianPhoneDigit('9232321234', 6)), '(92) 3232-234')
})

test('componente compartilhado configura teclado mobile e os tres formularios', async () => {
  const component = await readFile(new URL('../src/components/BrazilianPhoneInput.vue', import.meta.url), 'utf8')
  const pages = await Promise.all([
    'AdminPage.vue',
    'ItHelpDeskPage.vue',
    'TransportPage.vue'
  ].map(file => readFile(new URL(`../src/pages/${file}`, import.meta.url), 'utf8')))

  assert.match(component, /inputmode="numeric"/)
  assert.match(component, /autocomplete="tel-national"/)
  assert.match(component, /@keydown="handleKeydown"/)
  pages.forEach(page => assert.match(page, /<BrazilianPhoneInput/))
})
