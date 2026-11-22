function dateParts (value) {
  const match = String(value ?? '').trim().match(/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/)

  if (!match) return null

  const [, year, month, day, hour, minute] = match
  const probe = new Date(Date.UTC(Number(year), Number(month) - 1, Number(day)))
  const validDate = probe.getUTCFullYear() === Number(year) &&
    probe.getUTCMonth() === Number(month) - 1 &&
    probe.getUTCDate() === Number(day)
  const validTime = hour === undefined || (Number(hour) <= 23 && Number(minute) <= 59)

  return validDate && validTime ? { year, month, day, hour, minute } : null
}

function zonedDateTimeParts (value, timeZone) {
  const normalized = String(value).replace(/(\.\d{3})\d+(?=Z|[+-]\d{2}:?\d{2}$)/, '$1')
  const date = new Date(normalized)

  if (Number.isNaN(date.getTime())) return null

  const formatter = new Intl.DateTimeFormat('pt-BR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    ...(timeZone ? { timeZone } : {})
  })
  const parts = Object.fromEntries(
    formatter.formatToParts(date)
      .filter(part => part.type !== 'literal')
      .map(part => [part.type, part.value])
  )

  return parts
}

export function formatDate (value) {
  const parts = dateParts(value)

  return parts ? `${parts.day}/${parts.month}/${parts.year}` : '-'
}

export function formatDateTime (value, options = {}) {
  const text = String(value ?? '').trim()
  const parts = dateParts(text)

  if (!parts) return '-'
  if (parts.hour === undefined) return `${parts.day}/${parts.month}/${parts.year}`

  const hasTimezone = /(?:Z|[+-]\d{2}:?\d{2})$/i.test(text)
  const display = hasTimezone
    ? zonedDateTimeParts(text, options.timeZone)
    : parts

  if (!display) return '-'

  return `${display.day}/${display.month}/${display.year} ${display.hour}:${display.minute}`
}
