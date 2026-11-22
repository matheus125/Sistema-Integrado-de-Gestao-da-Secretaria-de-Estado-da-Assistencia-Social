import { defineStore } from 'pinia'
import { apiFetch } from '@/services/api'
import { clearAuthStorage, getAccessToken, getAuthJson, setAuthItem } from '@/services/auth-storage'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: getAccessToken(),
    user: getAuthJson('siges_user', null),
    roles: getAuthJson('siges_roles', []),
    permissions: getAuthJson('siges_permissions', []),
    loading: false
  }),

  getters: {
    isAuthenticated: (state) => Boolean(state.token),
    userName: (state) => state.user?.name || 'Usuario',
    can: (state) => (permission) => state.permissions.includes(permission),
    canAny: (state) => (permissions) => permissions.some(permission => state.permissions.includes(permission)),
    hasRole: (state) => (role) => state.roles.includes(role)
  },

  actions: {
    async login(credentials) {
      this.loading = true

      try {
        const data = await apiFetch('/auth/login', {
          method: 'POST',
          body: JSON.stringify({
            ...credentials,
            device_name: browserDeviceName()
          })
        })

        this.token = data.token
        this.user = data.user
        this.roles = data.roles || []
        this.permissions = data.permissions || []

        setAuthItem('siges_token', this.token)
        setAuthItem('siges_user', JSON.stringify(this.user))
        setAuthItem('siges_roles', JSON.stringify(this.roles))
        setAuthItem('siges_permissions', JSON.stringify(this.permissions))
      } finally {
        this.loading = false
      }
    },

    async fetchMe() {
      if (!this.token) return

      const data = await apiFetch('/auth/me')
      this.user = data.user
      this.roles = data.roles || []
      this.permissions = data.permissions || []
      setAuthItem('siges_user', JSON.stringify(this.user))
      setAuthItem('siges_roles', JSON.stringify(this.roles))
      setAuthItem('siges_permissions', JSON.stringify(this.permissions))
    },

    async logout() {
      if (this.token) {
        await apiFetch('/auth/logout', { method: 'POST' }).catch(() => {})
      }

      this.clearSession()
    },

    clearSession() {
      this.token = null
      this.user = null
      this.roles = []
      this.permissions = []
      clearAuthStorage()
    }
  }
})

function browserDeviceName() {
  if (typeof navigator === 'undefined') return 'SIGES Web'

  const platform = navigator.userAgentData?.platform || navigator.platform
  return platform ? `SIGES Web - ${platform}`.slice(0, 120) : 'SIGES Web'
}
