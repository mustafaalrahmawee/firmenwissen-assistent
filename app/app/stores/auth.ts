import { defineStore } from 'pinia'

export interface Tenant {
  name: string
}

export interface User {
  id: number
  name: string
  email: string
  tenant: Tenant
}

const AUTH_REQUEST_TIMEOUT_MS = 5000

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)

  async function login(email: string, password: string) {
    const { data } = await $api<{ data: { token: string } }>('/login', {
      method: 'POST',
      body: { email, password },
    })
    localStorage.setItem(TOKEN_KEY, data.token)

    try {
      await fetchUser()
    }
    catch (error) {
      clear()
      throw error
    }
  }

  async function fetchUser() {
    const { data } = await $api<{ data: User }>('/user', { timeout: AUTH_REQUEST_TIMEOUT_MS })
    user.value = data
  }

  // Zugang und Person verschwinden nur, wenn der Server den Zugang beendet hat
  async function logout() {
    await $api('/logout', { method: 'POST', timeout: AUTH_REQUEST_TIMEOUT_MS })
    clear()
  }

  // Zugang im Browser und Person im Store entfernen
  function clear() {
    localStorage.removeItem(TOKEN_KEY)
    user.value = null
  }

  return { user, login, fetchUser, logout, clear }
})
