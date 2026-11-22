const AREA_CODES = new Set([
  '11', '12', '13', '14', '15', '16', '17', '18', '19',
  '21', '22', '24', '27', '28',
  '31', '32', '33', '34', '35', '37', '38',
  '41', '42', '43', '44', '45', '46', '47', '48', '49',
  '51', '53', '54', '55',
  '61', '62', '63', '64', '65', '66', '67', '68', '69',
  '71', '73', '74', '75', '77', '79',
  '81', '82', '83', '84', '85', '86', '87', '88', '89',
  '91', '92', '93', '94', '95', '96', '97', '98', '99'
])

export function normalizeBrazilianPhoneInput (value) {
  let digits = String(value ?? '').replace(/\D/g, '')

  if (digits.length > 11 && digits.startsWith('55')) {
    digits = digits.slice(2)
  }

  return digits.slice(0, 11)
}

export function formatBrazilianPhone (value) {
  const digits = normalizeBrazilianPhoneInput(value)

  if (!digits) return ''
  if (digits.length <= 2) return `(${digits}`

  const areaCode = digits.slice(0, 2)
  const subscriber = digits.slice(2)
  const prefixLength = subscriber.startsWith('9') ? 5 : 4
  const prefix = subscriber.slice(0, prefixLength)
  const suffix = subscriber.slice(prefixLength)

  return `(${areaCode}) ${prefix}${suffix ? `-${suffix}` : ''}`
}

export function isValidBrazilianPhone (value) {
  const digits = normalizeBrazilianPhoneInput(value)

  if (![10, 11].includes(digits.length) || !AREA_CODES.has(digits.slice(0, 2))) {
    return false
  }

  const subscriber = digits.slice(2)

  return subscriber.length === 9
    ? subscriber.startsWith('9')
    : /^[2-5]/.test(subscriber)
}

export function phoneDigitIndexAtCaret (value, caret) {
  const text = String(value ?? '')
  const allDigits = text.replace(/\D/g, '')
  let digitIndex = text.slice(0, Math.max(0, caret ?? text.length)).replace(/\D/g, '').length

  if (allDigits.length > 11 && allDigits.startsWith('55')) {
    digitIndex = Math.max(0, digitIndex - 2)
  }

  return Math.min(digitIndex, 11)
}

export function phoneCaretForDigitIndex (formattedValue, digitIndex) {
  const formatted = String(formattedValue ?? '')

  if (!formatted) return 0
  if (digitIndex <= 0) return formatted.startsWith('(') ? 1 : 0

  let found = 0

  for (let index = 0; index < formatted.length; index += 1) {
    if (/\d/.test(formatted[index])) {
      found += 1
      if (found === digitIndex) return index + 1
    }
  }

  return formatted.length
}

export function removeBrazilianPhoneDigit (value, digitIndex) {
  const digits = normalizeBrazilianPhoneInput(value)

  if (digitIndex < 0 || digitIndex >= digits.length) return digits

  return digits.slice(0, digitIndex) + digits.slice(digitIndex + 1)
}
