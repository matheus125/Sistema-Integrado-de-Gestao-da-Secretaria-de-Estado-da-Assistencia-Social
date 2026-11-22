function normalizeIntegerPart (value) {
  return String(value).replace(/^0+(?=\d)/, '') || '0'
}

export function normalizeDecimalMoney (value) {
  if (value === null || value === undefined || value === '') return null

  const text = String(value).trim()

  if (!/^\d+(?:\.\d{0,2})?$/.test(text)) return null

  const [integer, fraction = ''] = text.split('.')

  return `${normalizeIntegerPart(integer)}.${fraction.padEnd(2, '0')}`
}

export function minorDigitsToDecimalMoney (value) {
  let digits = String(value ?? '').replace(/\D/g, '')

  if (!digits) return null

  digits = digits.replace(/^0+(?=\d{3})/, '').padStart(3, '0')

  return `${normalizeIntegerPart(digits.slice(0, -2))}.${digits.slice(-2)}`
}

export function decimalMoneyToMinorDigits (value) {
  const decimal = normalizeDecimalMoney(value)

  if (decimal === null) return ''

  const digits = decimal.replace('.', '')

  return digits.replace(/^0+(?=\d{3})/, '').padStart(3, '0')
}

export function parseBrlCurrencyInput (value) {
  const text = String(value ?? '').trim()

  if (!text || text.startsWith('-')) return null

  if (/^\d+\.\d{1,2}$/.test(text)) {
    return normalizeDecimalMoney(text)
  }

  return minorDigitsToDecimalMoney(text)
}

export function formatBrlCurrency (value) {
  const decimal = normalizeDecimalMoney(value)

  if (decimal === null) return ''

  const [integer, fraction] = decimal.split('.')
  const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.')

  return `R$ ${grouped},${fraction}`
}

export function isValidBrlCurrencyValue (value, maxIntegerDigits = 10) {
  const text = String(value ?? '').trim()
  const decimal = /R\$|,/.test(text)
    ? parseBrlCurrencyInput(text)
    : normalizeDecimalMoney(text)

  if (decimal === null) return false

  return decimal.split('.')[0].length <= maxIntegerDigits
}

export function moneyDigitsAfterCaret (value, caret) {
  const text = String(value ?? '')
  const position = Math.max(0, caret ?? text.length)

  return text.slice(position).replace(/\D/g, '').length
}

export function moneyCaretForDigitsAfter (formattedValue, digitsAfter) {
  const formatted = String(formattedValue ?? '')

  if (!formatted || digitsAfter <= 0) return formatted.length

  let found = 0

  for (let index = formatted.length - 1; index >= 0; index -= 1) {
    if (/\d/.test(formatted[index])) {
      found += 1
      if (found === digitsAfter) return index
    }
  }

  const firstDigit = formatted.search(/\d/)

  return firstDigit >= 0 ? firstDigit : formatted.length
}

export function removeDecimalMoneyDigit (value, digitIndex) {
  const digits = decimalMoneyToMinorDigits(value)

  if (digitIndex < 0 || digitIndex >= digits.length) return normalizeDecimalMoney(value)

  return minorDigitsToDecimalMoney(digits.slice(0, digitIndex) + digits.slice(digitIndex + 1))
}
