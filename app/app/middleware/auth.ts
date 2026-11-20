// Nur für angemeldete Nutzer. Nutzung: definePageMeta({ middleware: 'auth' })
export default defineNuxtRouteMiddleware(() => {
  // TODO: Anmeldestatus prüfen, sonst
  // return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
})
