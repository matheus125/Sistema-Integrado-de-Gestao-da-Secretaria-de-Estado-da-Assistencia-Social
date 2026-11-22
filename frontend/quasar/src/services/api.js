import { clearAuthStorage, getAccessToken } from '@/services/auth-storage'
import { captureServerValidation, clearServerValidationErrors } from '@/services/server-validation'

export const API_URL = import.meta.env.QCLI_API_URL || (import.meta.env.PROD ? '/api' : 'http://127.0.0.1:8000/api')

function apiErrorMessage(payload, fallback) {
  const firstError = Object.values(payload.errors || {})?.[0]?.[0]
  return firstError || payload.message || fallback
}

export async function apiFetch(path, options = {}) {
  if (isMutation(options.method)) clearServerValidationErrors()

  const token = getAccessToken()
  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...(options.headers || {})
  }

  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers
  })

  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    handleUnauthorized(response)
    captureServerValidation(response.status, payload)
    const message = apiErrorMessage(payload, 'Nao foi possivel concluir a acao.')
    const error = new Error(message)
    error.status = response.status
    error.payload = payload
    throw error
  }

  return payload
}

export async function apiUpload(path, formData) {
  clearServerValidationErrors()

  const token = getAccessToken()
  const headers = { Accept: 'application/json' }

  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  const response = await fetch(`${API_URL}${path}`, {
    method: 'POST',
    headers,
    body: formData
  })

  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    handleUnauthorized(response)
    captureServerValidation(response.status, payload)
    const message = apiErrorMessage(payload, 'Nao foi possivel enviar o arquivo.')
    const error = new Error(message)
    error.status = response.status
    error.payload = payload
    throw error
  }

  return payload
}

function isMutation(method) {
  return ['POST', 'PUT', 'PATCH'].includes(String(method || 'GET').toUpperCase())
}

export async function apiDownload(path, filename) {
  clearServerValidationErrors()

  const token = getAccessToken()
  const response = await fetch(`${API_URL}${path}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {}
  })

  if (!response.ok) {
    handleUnauthorized(response)
    const payload = await response.json().catch(() => ({}))
    captureServerValidation(response.status, payload)
    const error = new Error(apiErrorMessage(payload, 'Nao foi possivel baixar o arquivo.'))
    error.status = response.status
    error.payload = payload
    throw error
  }

  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

function handleUnauthorized(response) {
  if (response.status !== 401) return

  clearAuthStorage()

  if (typeof window !== 'undefined') {
    window.dispatchEvent(new Event('siges:unauthenticated'))
  }
}
