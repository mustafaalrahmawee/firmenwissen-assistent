// Nur für Gäste (z. B. Login). Nutzung: definePageMeta({ middleware: 'guest' })
export default defineNuxtRouteMiddleware(() => {
  const auth = useAuthStore()

  if (auth.isAuthenticated) {
    return navigateTo('/')
  }
})
