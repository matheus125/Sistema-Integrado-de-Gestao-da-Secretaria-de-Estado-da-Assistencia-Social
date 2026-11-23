import { createApp, defineComponent, h, nextTick, ref } from 'vue'

export function mountComponent(component, { props = {}, components = {} } = {}) {
  const element = document.createElement('div')
  document.body.appendChild(element)

  const app = createApp(component, props)
  for (const [name, registeredComponent] of Object.entries(components)) {
    app.component(name, registeredComponent)
  }

  const vm = app.mount(element)

  return {
    app,
    element,
    vm,
    unmount() {
      app.unmount()
      element.remove()
    }
  }
}

export const QInputStub = defineComponent({
  name: 'QInput',
  inheritAttrs: false,
  props: {
    modelValue: { type: [String, Number], default: '' },
    rules: { type: Array, default: () => [] },
    type: { type: String, default: 'text' },
    label: { type: String, default: '' }
  },
  emits: ['update:modelValue'],
  setup(props, { attrs, emit, expose }) {
    const input = ref(null)
    const currentValue = ref(props.modelValue)
    expose({ getNativeElement: () => input.value })

    function validationMessage(value) {
      for (const rule of props.rules) {
        const result = typeof rule === 'function' ? rule(value) : true
        if (result !== true) return String(result)
      }

      return ''
    }

    return () => h('input', {
      ...attrs,
      ref: input,
      type: props.type,
      value: currentValue.value ?? '',
      'aria-label': props.label || attrs['aria-label'],
      'data-validation-error': validationMessage(currentValue.value),
      onInput(event) {
        currentValue.value = event.target.value
        emit('update:modelValue', event.target.value)
      },
      onKeydown(event) {
        attrs.onKeydown?.(event)
      }
    })
  }
})

export async function setInputValue(input, value) {
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
  await nextTick()
}

export async function flushPromises() {
  await Promise.resolve()
  await Promise.resolve()
  await nextTick()
}
