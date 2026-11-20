// Nur für angemeldete Nutzer. Nutzung: definePageMeta({ middleware: 'auth' })
export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore()

  if (!auth.isAuthenticated) {
    // Zielseite merken, damit der Login danach dorthin zurückführt
    return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
  }
})
