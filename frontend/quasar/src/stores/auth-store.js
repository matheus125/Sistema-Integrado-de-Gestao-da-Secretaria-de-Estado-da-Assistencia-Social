import { defineStore } from 'pinia'
import { apiFetch } from '@/services/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('siges_token'),
    user: JSON.parse(localStorage.getItem('siges_user') || 'null'),
    roles: JSON.parse(localStorage.getItem('siges_roles') || '[]'),
    permissions: JSON.parse(localStorage.getItem('siges_permissions') || '[]'),
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
            device_name: 'siges-web'
          })
        })

        this.token = data.token
        this.user = data.user
        this.roles = data.roles || []
        this.permissions = data.permissions || []

        localStorage.setItem('siges_token', this.token)
        localStorage.setItem('siges_user', JSON.stringify(this.user))
        localStorage.setItem('siges_roles', JSON.stringify(this.roles))
        localStorage.setItem('siges_permissions', JSON.stringify(this.permissions))
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
      localStorage.setItem('siges_user', JSON.stringify(this.user))
      localStorage.setItem('siges_roles', JSON.stringify(this.roles))
      localStorage.setItem('siges_permissions', JSON.stringify(this.permissions))
    },

    async logout() {
      if (this.token) {
        await apiFetch('/auth/logout', { method: 'POST' }).catch(() => {})
      }

      this.token = null
      this.user = null
      this.roles = []
      this.permissions = []
      localStorage.removeItem('siges_token')
      localStorage.removeItem('siges_user')
      localStorage.removeItem('siges_roles')
      localStorage.removeItem('siges_permissions')
    }
  }
})
