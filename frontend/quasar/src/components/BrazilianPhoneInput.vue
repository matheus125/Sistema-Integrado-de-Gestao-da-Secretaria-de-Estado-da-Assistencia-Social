<template>
  <q-input
    ref="inputRef"
    v-bind="$attrs"
    :model-value="formattedValue"
    type="tel"
    inputmode="numeric"
    autocomplete="tel-national"
    :maxlength="19"
    :rules="combinedRules"
    @update:model-value="updateValue"
    @keydown="handleKeydown"
  />
</template>

<script setup>
import { computed, nextTick, ref } from 'vue'
import {
  formatBrazilianPhone,
  isValidBrazilianPhone,
  normalizeBrazilianPhoneInput,
  phoneCaretForDigitIndex,
  phoneDigitIndexAtCaret,
  removeBrazilianPhoneDigit
} from '@/utils/brazilian-phone-mask'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  rules: { type: Array, default: () => [] }
})
const emit = defineEmits(['update:modelValue'])
const inputRef = ref(null)
const formattedValue = computed(() => formatBrazilianPhone(props.modelValue))
const phoneRule = value => !normalizeBrazilianPhoneInput(value) || isValidBrazilianPhone(value) || 'Informe um telefone brasileiro válido com DDD'
const combinedRules = computed(() => [...props.rules, phoneRule])

function nativeInput () {
  return inputRef.value?.getNativeElement?.() || null
}

function updateValue (value) {
  const input = nativeInput()
  const caret = input?.selectionStart ?? String(value ?? '').length
  const digitIndex = phoneDigitIndexAtCaret(value, caret)
  const digits = normalizeBrazilianPhoneInput(value)

  emit('update:modelValue', digits)
  restoreCaret(digitIndex, digits)
}

function handleKeydown (event) {
  if (!['Backspace', 'Delete'].includes(event.key)) return

  const input = nativeInput()
  const start = input?.selectionStart
  const end = input?.selectionEnd

  if (start === null || start === undefined || start !== end) return

  const adjacentIndex = event.key === 'Backspace' ? start - 1 : start
  const adjacentCharacter = formattedValue.value[adjacentIndex]

  if (adjacentIndex < 0 || /\d/.test(adjacentCharacter || '')) return

  const digitIndex = phoneDigitIndexAtCaret(formattedValue.value, start)
  const removeIndex = event.key === 'Backspace' ? digitIndex - 1 : digitIndex

  if (removeIndex < 0 || removeIndex >= normalizeBrazilianPhoneInput(props.modelValue).length) return

  event.preventDefault()
  const digits = removeBrazilianPhoneDigit(props.modelValue, removeIndex)

  emit('update:modelValue', digits)
  restoreCaret(event.key === 'Backspace' ? digitIndex - 1 : digitIndex, digits)
}

function restoreCaret (digitIndex, value) {
  nextTick(() => {
    const input = nativeInput()
    const position = phoneCaretForDigitIndex(formatBrazilianPhone(value), digitIndex)
    input?.setSelectionRange?.(position, position)
  })
}
</script>
