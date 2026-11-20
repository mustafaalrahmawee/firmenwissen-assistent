<template>
  <AuthCard
    title="Willkommen zurück"
    description="Melde dich an, um Dokumente hochzuladen und Fragen an dein Firmenwissen zu stellen."
    :error="error"
  >
    <form class="d-flex flex-column ga-4" @submit.prevent="submit">
      <FormField v-model="email" label="E-Mail" type="email" autocomplete="username" />
      <FormField v-model="password" label="Passwort" type="password" autocomplete="current-password" />
      <v-btn type="submit" color="primary" height="36" block class="mt-2" :loading="busy">
        Anmelden
      </v-btn>
    </form>
  </AuthCard>
</template>

<script setup lang="ts">
definePageMeta({
  layout: 'guest',
  middleware: 'guest',
})

const auth = useAuthStore()
const route = useRoute()

const email = ref('')
const password = ref('')
const error = ref<string | null>(null)
const busy = ref(false)

// Nur interne Pfade zulassen (kein Open Redirect über ?redirect=//fremde-seite)
function redirectTarget(): string {
  const target = route.query.redirect
  return typeof target === 'string' && target.startsWith('/') && !target.startsWith('//')
    ? target
    : '/'
}

async function submit() {
  busy.value = true
  error.value = null
  try {
    // TODO: Anmeldung über Sanctum statt Platzhalter-Nutzer
    auth.login({ id: 1, name: email.value.split('@')[0] || 'Demo', email: email.value })
    await navigateTo(redirectTarget())
  } catch {
    error.value = 'Anmeldung gerade nicht möglich.'
  } finally {
    busy.value = false
  }
}
</script>
