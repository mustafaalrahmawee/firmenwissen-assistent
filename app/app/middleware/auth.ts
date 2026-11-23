// Nur für angemeldete Nutzer. Nutzung: definePageMeta({ middleware: 'auth' })
// Angemeldet ist, wer eine geladene Person im Store hat (das Plugin auth lädt sie beim Start).
export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore()

  if (auth.user === null) {
    return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
  }
})
