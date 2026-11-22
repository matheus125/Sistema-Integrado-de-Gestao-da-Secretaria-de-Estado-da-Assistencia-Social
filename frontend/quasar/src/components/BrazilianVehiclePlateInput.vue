<template>
  <q-input
    ref="inputRef"
    v-bind="$attrs"
    :model-value="formattedValue"
    type="text"
    inputmode="text"
    autocapitalize="characters"
    autocomplete="off"
    spellcheck="false"
    :maxlength="8"
    :rules="combinedRules"
    @update:model-value="updateValue"
    @keydown="handleKeydown"
  />
</template>

<script setup>
import { computed, nextTick, ref } from 'vue'
import {
  formatBrazilianVehiclePlate,
  isValidBrazilianVehiclePlate,
  normalizeBrazilianVehiclePlateInput,
  plateCaretForCharacterIndex,
  plateCharacterIndexAtCaret,
  removeBrazilianVehiclePlateCharacter
} from '@/utils/brazilian-vehicle-plate-mask'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  rules: { type: Array, default: () => [] }
})
const emit = defineEmits(['update:modelValue'])
const inputRef = ref(null)
const formattedValue = computed(() => formatBrazilianVehiclePlate(props.modelValue))
const plateRule = value => !normalizeBrazilianVehiclePlateInput(value) || isValidBrazilianVehiclePlate(value) || 'Informe uma placa brasileira válida'
const combinedRules = computed(() => [...props.rules, plateRule])

function nativeInput () {
  return inputRef.value?.getNativeElement?.() || null
}

function updateValue (value) {
  const input = nativeInput()
  const caret = input?.selectionStart ?? String(value ?? '').length
  const characterIndex = plateCharacterIndexAtCaret(value, caret)
  const formatted = formatBrazilianVehiclePlate(value)

  emit('update:modelValue', formatted)
  restoreCaret(characterIndex, formatted)
}

function handleKeydown (event) {
  if (!['Backspace', 'Delete'].includes(event.key)) return

  const input = nativeInput()
  const start = input?.selectionStart
  const end = input?.selectionEnd

  if (start === null || start === undefined || start !== end) return

  const adjacentIndex = event.key === 'Backspace' ? start - 1 : start
  const adjacentCharacter = formattedValue.value[adjacentIndex]

  if (adjacentIndex < 0 || /[A-Z0-9]/i.test(adjacentCharacter || '')) return

  const characterIndex = plateCharacterIndexAtCaret(formattedValue.value, start)
  const removeIndex = event.key === 'Backspace' ? characterIndex - 1 : characterIndex

  if (removeIndex < 0 || removeIndex >= normalizeBrazilianVehiclePlateInput(props.modelValue).length) return

  event.preventDefault()
  const compact = removeBrazilianVehiclePlateCharacter(props.modelValue, removeIndex)
  const formatted = formatBrazilianVehiclePlate(compact)

  emit('update:modelValue', formatted)
  restoreCaret(event.key === 'Backspace' ? characterIndex - 1 : characterIndex, formatted)
}

function restoreCaret (characterIndex, value) {
  nextTick(() => {
    const input = nativeInput()
    const position = plateCaretForCharacterIndex(formatBrazilianVehiclePlate(value), characterIndex)
    input?.setSelectionRange?.(position, position)
  })
}
</script>
