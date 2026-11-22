import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import test from 'node:test'
import {
  formatBrazilianVehiclePlate,
  isValidBrazilianVehiclePlate,
  normalizeBrazilianVehiclePlateInput,
  plateCaretForCharacterIndex,
  plateCharacterIndexAtCaret,
  removeBrazilianVehiclePlateCharacter
} from '../src/utils/brazilian-vehicle-plate-mask.js'

test('formata placas antiga e Mercosul durante a digitacao', () => {
  assert.equal(formatBrazilianVehiclePlate('abc'), 'ABC')
  assert.equal(formatBrazilianVehiclePlate('abc1'), 'ABC-1')
  assert.equal(formatBrazilianVehiclePlate('abc1234'), 'ABC-1234')
  assert.equal(formatBrazilianVehiclePlate('abc1d'), 'ABC1D')
  assert.equal(formatBrazilianVehiclePlate('abc1d23'), 'ABC1D23')
})

test('normaliza colagem com caixa, espaco e hifen', () => {
  assert.equal(normalizeBrazilianVehiclePlateInput('abc-1234'), 'ABC1234')
  assert.equal(normalizeBrazilianVehiclePlateInput('abc 1d23'), 'ABC1D23')
  assert.equal(formatBrazilianVehiclePlate('abc-1d23'), 'ABC1D23')
  assert.equal(normalizeBrazilianVehiclePlateInput('abc@12345'), 'ABC1234')
})

test('valida os dois formatos com as mesmas posicoes do backend', () => {
  assert.equal(isValidBrazilianVehiclePlate('ABC-1234'), true)
  assert.equal(isValidBrazilianVehiclePlate('ABC1D23'), true)
  assert.equal(isValidBrazilianVehiclePlate('ABC12D3'), false)
  assert.equal(isValidBrazilianVehiclePlate('ABCD123'), false)
  assert.equal(isValidBrazilianVehiclePlate('123-ABCD'), false)
})

test('mantem o cursor associado ao mesmo caractere ao inserir ou remover hifen', () => {
  assert.equal(plateCharacterIndexAtCaret('ABC-1234', 5), 4)
  assert.equal(plateCaretForCharacterIndex('ABC-1234', 4), 5)
  assert.equal(plateCharacterIndexAtCaret('ABC1D23', 5), 5)
  assert.equal(plateCaretForCharacterIndex('ABC1D23', 5), 5)
})

test('exclusao e edicao removem somente o caractere selecionado', () => {
  assert.equal(removeBrazilianVehiclePlateCharacter('ABC-1234', 2), 'AB1234')
  assert.equal(removeBrazilianVehiclePlateCharacter('ABC1D23', 4), 'ABC123')
  assert.equal(formatBrazilianVehiclePlate(removeBrazilianVehiclePlateCharacter('ABC-1234', 3)), 'ABC-234')
})

test('componente compartilhado configura entrada e formulario de veiculo', async () => {
  const component = await readFile(new URL('../src/components/BrazilianVehiclePlateInput.vue', import.meta.url), 'utf8')
  const page = await readFile(new URL('../src/pages/TransportPage.vue', import.meta.url), 'utf8')

  assert.match(component, /autocapitalize="characters"/)
  assert.match(component, /@keydown="handleKeydown"/)
  assert.match(component, /emit\('update:modelValue', formatted\)/)
  assert.match(page, /<BrazilianVehiclePlateInput/)
})
