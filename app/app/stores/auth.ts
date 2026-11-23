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

export interface Registration {
  name: string
  company: string
  email: string
  password: string
  password_confirmation: string
}

// Das Konto ist angelegt, aber danach ließ sich die Person nicht laden; ein neuer Versuch fände die Adresse vergeben
export class AccountCreatedError extends Error {}

const AUTH_REQUEST_TIMEOUT_MS = 5000

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)

  async function login(email: string, password: string) {
    const { data } = await $api<{ data: { token: string } }>('/login', {
      method: 'POST',
      body: { email, password },
    })
    await storeTokenAndLoadUser(data.token)
  }

  // Legt Konto und Arbeitsbereich an; die Antwort ist schon ein Zugang für dieses Gerät
  async function register(registration: Registration) {
    const { data } = await $api<{ data: { token: string } }>('/register', {
      method: 'POST',
      body: registration,
    })

    try {
      await storeTokenAndLoadUser(data.token)
    }
    catch (error) {
      throw new AccountCreatedError('Konto angelegt, Anmeldung danach fehlgeschlagen', { cause: error })
    }
  }

  // Zugang speichern und die Person laden; gelingt das nicht, bleibt kein halber Zustand zurück
  async function storeTokenAndLoadUser(token: string) {
    localStorage.setItem(TOKEN_KEY, token)

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

  return { user, login, register, fetchUser, logout, clear }
})
