import { afterEach, describe, expect, it, vi } from 'vitest'
import BrazilianPhoneInput from '@/components/BrazilianPhoneInput.vue'
import BrazilianVehiclePlateInput from '@/components/BrazilianVehiclePlateInput.vue'
import BrlCurrencyInput from '@/components/BrlCurrencyInput.vue'
import { mountComponent, QInputStub, setInputValue } from './helpers/mount'

const mounted = []

afterEach(() => {
  while (mounted.length) mounted.pop().unmount()
})

function mountInput(component, props) {
  const onUpdate = vi.fn()
  const wrapper = mountComponent(component, {
    props: { ...props, 'onUpdate:modelValue': onUpdate },
    components: { QInput: QInputStub }
  })
  mounted.push(wrapper)

  return { input: wrapper.element.querySelector('input'), onUpdate }
}

describe('componentes dos formulários', () => {
  it('formata e normaliza telefone brasileiro antes de atualizar o formulário', async () => {
    const { input, onUpdate } = mountInput(BrazilianPhoneInput, { modelValue: '92999998888', label: 'Telefone' })

    expect(input.value).toBe('(92) 99999-8888')
    await setInputValue(input, '(92) 98888-7777')

    expect(onUpdate).toHaveBeenLastCalledWith('92988887777')
    await setInputValue(input, '123')
    expect(input.dataset.validationError).toContain('telefone brasileiro válido')
  })

  it('emite placa canônica e rejeita formato inválido', async () => {
    const { input, onUpdate } = mountInput(BrazilianVehiclePlateInput, { modelValue: 'abc1d23', label: 'Placa' })

    expect(input.value).toBe('ABC1D23')
    await setInputValue(input, 'abc-1234')

    expect(onUpdate).toHaveBeenLastCalledWith('ABC-1234')
    await setInputValue(input, 'ABC12')
    expect(input.dataset.validationError).toContain('placa brasileira válida')
  })

  it('converte moeda exibida em decimal canônico e aplica o limite configurado', async () => {
    const { input, onUpdate } = mountInput(BrlCurrencyInput, {
      modelValue: '1234.56',
      label: 'Valor',
      maxIntegerDigits: 5
    })

    expect(input.value).toBe('R$ 1.234,56')
    await setInputValue(input, 'R$ 9.876,54')

    expect(onUpdate).toHaveBeenLastCalledWith('9876.54')
    await setInputValue(input, 'R$ 123.456,78')
    expect(input.dataset.validationError).toContain('valor monetário válido')
  })
})
