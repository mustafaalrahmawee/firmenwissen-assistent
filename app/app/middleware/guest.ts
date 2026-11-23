// Nur für Gäste (z. B. Login). Nutzung: definePageMeta({ middleware: 'guest' })
// Wer angemeldet ist (geladene Person im Store), kommt zur Startseite.
export default defineNuxtRouteMiddleware(() => {
  const auth = useAuthStore()

  if (auth.user !== null) {
    return navigateTo('/')
  }
})
