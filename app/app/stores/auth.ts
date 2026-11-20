import { defineStore } from 'pinia'

export interface User {
  id: number
  name: string
  email: string
}

// Anmeldestatus. Der Pinia-State wird beim SSR in den Payload geschrieben,
// Server und Client sehen also denselben Stand.
export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)

  const isAuthenticated = computed(() => user.value !== null)

  // TODO: durch Sanctum ersetzen (POST /login, danach GET /api/user)
  function login(newUser: User) {
    user.value = newUser
  }

  function logout() {
    user.value = null
  }

  return { user, isAuthenticated, login, logout }
})
