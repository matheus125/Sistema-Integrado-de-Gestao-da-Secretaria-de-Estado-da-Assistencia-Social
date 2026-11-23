import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiDownload, apiFetch, apiUpload } from '@/services/api'
import {
  clearServerValidationErrors,
  serverValidationErrors,
  setServerValidationErrors
} from '@/services/server-validation'

function jsonResponse(status, payload) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: vi.fn().mockResolvedValue(payload)
  }
}

describe('serviço API', () => {
  beforeEach(() => {
    window.localStorage.clear()
    window.sessionStorage.clear()
    clearServerValidationErrors()
    vi.stubGlobal('fetch', vi.fn())
  })

  it('envia token e JSON e devolve o payload de sucesso', async () => {
    window.sessionStorage.setItem('siges_token', 'token-ficticio')
    fetch.mockResolvedValue(jsonResponse(200, { id: 10 }))

    await expect(apiFetch('/it/tickets', { method: 'POST', body: '{"x":1}' })).resolves.toEqual({ id: 10 })
    expect(fetch).toHaveBeenCalledWith(expect.stringMatching(/\/api\/it\/tickets$/), expect.objectContaining({
      method: 'POST',
      headers: expect.objectContaining({
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: 'Bearer token-ficticio'
      })
    }))
  })

  it('preserva status, payload e todos os erros 422 por campo', async () => {
    setServerValidationErrors({ antigo: ['erro antigo'] })
    fetch.mockResolvedValue(jsonResponse(422, {
      message: 'Dados inválidos',
      errors: { email: ['Email inválido'], 'items.0.name': ['Informe o nome'] }
    }))

    await expect(apiFetch('/users', { method: 'PATCH' })).rejects.toMatchObject({
      message: 'Email inválido',
      status: 422,
      payload: expect.objectContaining({ message: 'Dados inválidos' })
    })
    expect(serverValidationErrors()).toEqual({
      email: ['Email inválido'],
      'items.0.name': ['Informe o nome']
    })
  })

  it('limpa sessão e emite evento global ao receber 401', async () => {
    window.sessionStorage.setItem('siges_token', 'token-ficticio')
    window.localStorage.setItem('siges_user', '{"id":1}')
    const listener = vi.fn()
    window.addEventListener('siges:unauthenticated', listener, { once: true })
    fetch.mockResolvedValue(jsonResponse(401, { message: 'Não autenticado' }))

    await expect(apiFetch('/auth/me')).rejects.toMatchObject({ status: 401 })
    expect(window.sessionStorage.getItem('siges_token')).toBeNull()
    expect(window.localStorage.getItem('siges_user')).toBeNull()
    expect(listener).toHaveBeenCalledOnce()
  })

  it('envia FormData sem forçar Content-Type e captura validação do upload', async () => {
    window.sessionStorage.setItem('siges_token', 'token-ficticio')
    const formData = new FormData()
    formData.append('file', new Blob(['teste']), 'teste.txt')
    fetch.mockResolvedValue(jsonResponse(422, { errors: { file: ['Arquivo inválido'] } }))

    await expect(apiUpload('/attachments', formData)).rejects.toMatchObject({ status: 422 })
    const options = fetch.mock.calls[0][1]
    expect(options.body).toBe(formData)
    expect(options.headers).toEqual({
      Accept: 'application/json',
      Authorization: 'Bearer token-ficticio'
    })
    expect(serverValidationErrors()).toEqual({ file: ['Arquivo inválido'] })
  })

  it('baixa o blob com nome controlado e revoga a URL temporária', async () => {
    const blob = new Blob(['conteúdo'])
    fetch.mockResolvedValue({ ok: true, status: 200, blob: vi.fn().mockResolvedValue(blob) })
    const createObjectURL = vi.fn().mockReturnValue('blob:siges-teste')
    const revokeObjectURL = vi.fn()
    Object.defineProperty(URL, 'createObjectURL', { configurable: true, value: createObjectURL })
    Object.defineProperty(URL, 'revokeObjectURL', { configurable: true, value: revokeObjectURL })
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})

    await apiDownload('/reports/1/download', 'relatorio.pdf')

    expect(createObjectURL).toHaveBeenCalledWith(blob)
    expect(click).toHaveBeenCalledOnce()
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:siges-teste')
  })
})
