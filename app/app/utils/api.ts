import { ofetch } from 'ofetch'

/** Schlüssel in localStorage, unter dem der Sanctum-Token liegt. */
export const TOKEN_KEY = 'auth_token'

/**
 * $fetch-Instanz für die Laravel-API, wird von Nuxt automatisch importiert.
 * Basis-URL, Accept-Header und Bearer-Token kommen von selbst dazu:
 *   await $api('/login', { method: 'POST', body: form })
 *
 * Warum nicht globalThis.$fetch überschreiben? In Nuxt 4 ist $fetch ein
 * Auto-Import, der den Wert beim Laden festhält. Ein späteres Überschreiben
 * kommt im Code nicht an. Nur mit ssr: false sicher (ein Nutzer pro Browser).
 */
export const $api = ofetch.create({
  onRequest({ options }) {
    options.baseURL = useRuntimeConfig().public.apiBase
    options.headers.set('Accept', 'application/json')

    const token = localStorage.getItem(TOKEN_KEY)
    if (token) {
      options.headers.set('Authorization', `Bearer ${token}`)
    }
  },

  async onResponseError({ response }) {
    // Token abgelaufen oder widerrufen: Token und Person löschen und zum Login.
    // Das Ziel kommt aus der Adresszeile, weil der Router beim Start (Plugin auth)
    // die aufgerufene Seite noch nicht kennt. Auf der Anmeldeseite bleibt die Adresse
    // samt ?redirect= stehen.
    if (response.status === 401) {
      localStorage.removeItem(TOKEN_KEY)
      useAuthStore().clear()

      const { pathname, search, hash } = window.location
      if (pathname !== '/login') {
        await navigateTo({ path: '/login', query: { redirect: pathname + search + hash } })
      }
    }
  },
})
