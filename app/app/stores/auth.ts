import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', () => {
  async function login(email: string, password: string) {
    const { token } = await $api<{ token: string }>('/login', {
      method: 'POST',
      body: { email, password },
    })
    localStorage.setItem(TOKEN_KEY, token)
  }

  return { login }
})
