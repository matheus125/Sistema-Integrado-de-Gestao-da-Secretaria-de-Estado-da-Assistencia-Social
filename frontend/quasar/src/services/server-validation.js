import { reactive } from 'vue'

const errors = reactive({})

function normalizeMessages (messages) {
  if (Array.isArray(messages)) return messages.map(String).filter(Boolean)
  if (messages === null || messages === undefined || messages === '') return []
  return [String(messages)]
}

export function clearServerValidationErrors () {
  for (const field of Object.keys(errors)) delete errors[field]
}

export function setServerValidationErrors (payloadErrors) {
  clearServerValidationErrors()

  if (!payloadErrors || typeof payloadErrors !== 'object' || Array.isArray(payloadErrors)) return

  for (const [field, messages] of Object.entries(payloadErrors)) {
    const normalized = normalizeMessages(messages)
    if (normalized.length) errors[field] = normalized
  }
}

export function captureServerValidation (status, payload) {
  if (Number(status) !== 422) return false

  setServerValidationErrors(payload?.errors)
  return Object.keys(errors).length > 0
}

export function serverErrorMessage (...fields) {
  const candidates = fields.flat().filter(Boolean).map(String)
  const messages = []

  for (const candidate of candidates) {
    const exact = errors[candidate]
    if (exact?.length) messages.push(...exact)

    for (const [field, nestedMessages] of Object.entries(errors)) {
      if (field.startsWith(`${candidate}.`) && nestedMessages.length) messages.push(...nestedMessages)
    }
  }

  return [...new Set(messages)].join(' • ')
}

export function serverErrorProps (...fields) {
  const message = serverErrorMessage(...fields)

  return {
    error: Boolean(message),
    errorMessage: message || undefined
  }
}

export function serverValidationErrors () {
  return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, [...messages]]))
}
