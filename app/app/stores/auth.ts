import { defineStore } from 'pinia'

export interface User {
  id: number
  name: string
  email: string
}

const FETCH_USER_TIMEOUT_MS = 5000

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
      // Ohne geladene Person gilt die Anmeldung als fehlgeschlagen
      localStorage.removeItem(TOKEN_KEY)
      throw error
    }
  }

  async function fetchUser() {
    const { data } = await $api<{ data: User }>('/user', { timeout: FETCH_USER_TIMEOUT_MS })
    user.value = data
  }

  function clear() {
    user.value = null
  }

  return { user, login, fetchUser, clear }
})
