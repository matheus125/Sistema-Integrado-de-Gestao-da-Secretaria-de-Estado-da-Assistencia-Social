import { defineComponent, h } from 'vue'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mountComponent, QInputStub, setInputValue } from './helpers/mount'

const mocks = vi.hoisted(() => ({
  login: vi.fn(),
  notify: vi.fn(),
  push: vi.fn(),
  auth: { loading: false }
}))

vi.mock('@/stores/auth-store', () => ({
  useAuthStore: () => ({ ...mocks.auth, login: mocks.login })
}))
vi.mock('quasar', () => ({ useQuasar: () => ({ notify: mocks.notify }) }))
vi.mock('vue-router', () => ({ useRouter: () => ({ push: mocks.push }) }))

import LoginPage from '@/pages/LoginPage.vue'

const mounted = []
const PassThrough = defineComponent({
  setup(_, { slots }) {
    return () => h('div', slots.default?.())
  }
})
const QFormStub = defineComponent({
  emits: ['submit'],
  setup(_, { emit, slots }) {
    return () => h('form', {
      onSubmit(event) {
        event.preventDefault()
        emit('submit', event)
      }
    }, slots.default?.())
  }
})
const QBtnStub = defineComponent({
  inheritAttrs: false,
  props: { type: { type: String, default: 'button' }, label: { type: String, default: '' } },
  setup(props, { attrs, slots }) {
    return () => h('button', { ...attrs, type: props.type }, props.label || slots.default?.())
  }
})

afterEach(() => {
  while (mounted.length) mounted.pop().unmount()
})

describe('formulário de login', () => {
  it('envia credenciais pela store e navega somente após autenticar', async () => {
    mocks.login.mockResolvedValue(undefined)
    const wrapper = mountComponent(LoginPage, {
      components: {
        QCard: PassThrough,
        QCardSection: PassThrough,
        QForm: QFormStub,
        QInput: QInputStub,
        QBtn: QBtnStub,
        QTooltip: PassThrough
      }
    })
    mounted.push(wrapper)
    const [email, password] = wrapper.element.querySelectorAll('input')

    await setInputValue(email, 'gestor@example.test')
    await setInputValue(password, 'segredo-ficticio')
    wrapper.element.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flushPromises()

    expect(mocks.login).toHaveBeenCalledWith({
      email: 'gestor@example.test',
      password: 'segredo-ficticio'
    })
    expect(mocks.notify).toHaveBeenCalledWith({ type: 'positive', message: 'Login realizado com sucesso.' })
    expect(mocks.push).toHaveBeenCalledWith('/')
  })

  it('mantém a tela e mostra a falha devolvida pela autenticação', async () => {
    mocks.login.mockRejectedValue(new Error('Credenciais inválidas'))
    const wrapper = mountComponent(LoginPage, {
      components: {
        QCard: PassThrough,
        QCardSection: PassThrough,
        QForm: QFormStub,
        QInput: QInputStub,
        QBtn: QBtnStub,
        QTooltip: PassThrough
      }
    })
    mounted.push(wrapper)

    wrapper.element.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flushPromises()

    expect(mocks.notify).toHaveBeenCalledWith({ type: 'negative', message: 'Credenciais inválidas' })
    expect(mocks.push).not.toHaveBeenCalled()
  })
})
