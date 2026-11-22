export function normalizeBrazilianVehiclePlateInput (value) {
  return String(value ?? '')
    .toUpperCase()
    .replace(/[^A-Z0-9]/g, '')
    .slice(0, 7)
}

export function formatBrazilianVehiclePlate (value) {
  const compact = normalizeBrazilianVehiclePlateInput(value)

  if (compact.length <= 3) return compact

  if (/^[A-Z]{3}[0-9][A-Z]/.test(compact)) {
    return compact
  }

  return `${compact.slice(0, 3)}-${compact.slice(3)}`
}

export function isValidBrazilianVehiclePlate (value) {
  const compact = normalizeBrazilianVehiclePlateInput(value)

  return /^[A-Z]{3}[0-9]{4}$/.test(compact) ||
    /^[A-Z]{3}[0-9][A-Z][0-9]{2}$/.test(compact)
}

export function plateCharacterIndexAtCaret (value, caret) {
  const text = String(value ?? '')

  return Math.min(
    text.slice(0, Math.max(0, caret ?? text.length)).replace(/[^A-Z0-9]/gi, '').length,
    7
  )
}

export function plateCaretForCharacterIndex (formattedValue, characterIndex) {
  const formatted = String(formattedValue ?? '')

  if (characterIndex <= 0) return 0

  let found = 0

  for (let index = 0; index < formatted.length; index += 1) {
    if (/[A-Z0-9]/i.test(formatted[index])) {
      found += 1
      if (found === characterIndex) return index + 1
    }
  }

  return formatted.length
}

export function removeBrazilianVehiclePlateCharacter (value, characterIndex) {
  const compact = normalizeBrazilianVehiclePlateInput(value)

  if (characterIndex < 0 || characterIndex >= compact.length) return compact

  return compact.slice(0, characterIndex) + compact.slice(characterIndex + 1)
}
