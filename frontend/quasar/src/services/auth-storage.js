const AUTH_KEYS = [
  'siges_token',
  'siges_user',
  'siges_roles',
  'siges_permissions'
]

function browserStorage() {
  if (typeof window === 'undefined') return null

  return {
    local: window.localStorage,
    session: window.sessionStorage
  }
}

export function migrateLegacyAuthStorage() {
  const storage = browserStorage()
  if (!storage) return

  AUTH_KEYS.forEach((key) => {
    const legacyValue = storage.local.getItem(key)

    if (storage.session.getItem(key) === null && legacyValue !== null) {
      storage.session.setItem(key, legacyValue)
    }

    storage.local.removeItem(key)
  })
}

export function getAuthItem(key) {
  migrateLegacyAuthStorage()
  return browserStorage()?.session.getItem(key) ?? null
}

export function getAuthJson(key, fallback) {
  const value = getAuthItem(key)
  if (value === null) return fallback

  try {
    return JSON.parse(value)
  } catch {
    browserStorage()?.session.removeItem(key)
    return fallback
  }
}

export function setAuthItem(key, value) {
  browserStorage()?.session.setItem(key, value)
}

export function getAccessToken() {
  return getAuthItem('siges_token')
}

export function clearAuthStorage() {
  const storage = browserStorage()
  if (!storage) return

  AUTH_KEYS.forEach((key) => {
    storage.session.removeItem(key)
    storage.local.removeItem(key)
  })
}
