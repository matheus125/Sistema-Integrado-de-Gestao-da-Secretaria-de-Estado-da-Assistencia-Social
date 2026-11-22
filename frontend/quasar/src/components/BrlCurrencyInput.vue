<template>
  <q-input
    ref="inputRef"
    v-bind="$attrs"
    :model-value="formattedValue"
    type="text"
    inputmode="decimal"
    autocomplete="off"
    :maxlength="inputMaxlength"
    :rules="combinedRules"
    @update:model-value="updateValue"
    @keydown="handleKeydown"
  />
</template>

<script setup>
import { computed, nextTick, ref } from 'vue'
import {
  decimalMoneyToMinorDigits,
  formatBrlCurrency,
  isValidBrlCurrencyValue,
  moneyCaretForDigitsAfter,
  moneyDigitsAfterCaret,
  parseBrlCurrencyInput,
  removeDecimalMoneyDigit
} from '@/utils/brl-currency'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  modelValue: { type: [String, Number], default: null },
  maxIntegerDigits: { type: Number, default: 10 },
  rules: { type: Array, default: () => [] }
})
const emit = defineEmits(['update:modelValue'])
const inputRef = ref(null)
const formattedValue = computed(() => formatBrlCurrency(props.modelValue))
const inputMaxlength = computed(() => 6 + props.maxIntegerDigits + Math.floor((props.maxIntegerDigits - 1) / 3))
const currencyRule = value => !value || isValidBrlCurrencyValue(value, props.maxIntegerDigits) || 'Informe um valor monetário válido'
const combinedRules = computed(() => [...props.rules, currencyRule])

function nativeInput () {
  return inputRef.value?.getNativeElement?.() || null
}

function updateValue (value) {
  const input = nativeInput()
  const caret = input?.selectionStart ?? String(value ?? '').length
  const digitsAfter = moneyDigitsAfterCaret(value, caret)
  const decimal = parseBrlCurrencyInput(value)

  emit('update:modelValue', decimal)
  restoreCaret(digitsAfter, decimal)
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

  const digitsBefore = formattedValue.value.slice(0, start).replace(/\D/g, '').length
  const digits = decimalMoneyToMinorDigits(props.modelValue)
  const removeIndex = event.key === 'Backspace' ? digitsBefore - 1 : digitsBefore

  if (removeIndex < 0 || removeIndex >= digits.length) return

  event.preventDefault()
  const decimal = removeDecimalMoneyDigit(props.modelValue, removeIndex)
  const digitsAfter = event.key === 'Backspace'
    ? digits.length - digitsBefore
    : digits.length - digitsBefore - 1

  emit('update:modelValue', decimal)
  restoreCaret(digitsAfter, decimal)
}

function restoreCaret (digitsAfter, value) {
  nextTick(() => {
    const input = nativeInput()
    const position = moneyCaretForDigitsAfter(formatBrlCurrency(value), digitsAfter)
    input?.setSelectionRange?.(position, position)
  })
}
</script>
