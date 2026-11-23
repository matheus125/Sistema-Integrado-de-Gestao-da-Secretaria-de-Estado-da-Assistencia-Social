import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/services/api', () => ({ apiFetch: vi.fn() }))

import { apiFetch } from '@/services/api'
import { useAuthStore } from '@/stores/auth-store'

describe('store de autenticação', () => {
  beforeEach(() => {
    window.localStorage.clear()
    window.sessionStorage.clear()
    setActivePinia(createPinia())
  })

  it('autentica, expõe permissões e persiste a sessão somente em sessionStorage', async () => {
    apiFetch.mockResolvedValue({
      token: 'token-ficticio',
      user: { id: 7, name: 'Usuária Teste' },
      roles: ['Gestor'],
      permissions: ['relatorios.visualizar']
    })
    const auth = useAuthStore()

    await auth.login({ email: 'teste@example.test', password: 'segredo-ficticio' })

    expect(apiFetch).toHaveBeenCalledWith('/auth/login', expect.objectContaining({ method: 'POST' }))
    expect(JSON.parse(apiFetch.mock.calls[0][1].body)).toEqual(expect.objectContaining({
      email: 'teste@example.test',
      password: 'segredo-ficticio',
      device_name: expect.stringContaining('SIGES Web')
    }))
    expect(auth.isAuthenticated).toBe(true)
    expect(auth.userName).toBe('Usuária Teste')
    expect(auth.can('relatorios.visualizar')).toBe(true)
    expect(auth.canAny(['usuarios.editar', 'relatorios.visualizar'])).toBe(true)
    expect(auth.hasRole('Gestor')).toBe(true)
    expect(window.sessionStorage.getItem('siges_token')).toBe('token-ficticio')
    expect(window.localStorage.getItem('siges_token')).toBeNull()
    expect(auth.loading).toBe(false)
  })

  it('sempre encerra o loading quando o login falha', async () => {
    apiFetch.mockRejectedValue(new Error('Credenciais inválidas'))
    const auth = useAuthStore()

    await expect(auth.login({ email: 'x@example.test', password: 'inválida' })).rejects.toThrow('Credenciais inválidas')
    expect(auth.loading).toBe(false)
    expect(auth.isAuthenticated).toBe(false)
  })

  it('atualiza o usuário e limpa a sessão no logout mesmo se a API falhar', async () => {
    window.sessionStorage.setItem('siges_token', 'token-ficticio')
    const auth = useAuthStore()
    apiFetch.mockResolvedValueOnce({ user: { id: 8, name: 'Atualizada' }, roles: [], permissions: ['x'] })

    await auth.fetchMe()
    expect(auth.userName).toBe('Atualizada')
    expect(auth.permissions).toEqual(['x'])

    apiFetch.mockRejectedValueOnce(new Error('sem rede'))
    await auth.logout()
    expect(auth.token).toBeNull()
    expect(auth.user).toBeNull()
    expect(window.sessionStorage.getItem('siges_token')).toBeNull()
  })
})
