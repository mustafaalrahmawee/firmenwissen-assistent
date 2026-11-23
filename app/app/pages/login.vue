<template>
  <v-card class="pa-6">
    <h1 class="text-title-large font-weight-semibold">Willkommen zurück</h1>
    <p class="text-body-medium text-muted mt-1 mb-6">
      Melde dich an, um Dokumente hochzuladen und Fragen an dein Firmenwissen zu stellen.
    </p>

    <v-alert v-if="error" type="error" class="mb-4" role="alert">
      <template #prepend>
        <CircleAlert :size="16" />
      </template>
      {{ error }}
    </v-alert>

    <v-form ref="form" v-model="formValid" class="d-flex flex-column ga-4" @submit.prevent="submit">
      <div class="d-flex flex-column ga-2">
        <label :for="emailId" class="text-body-medium font-weight-medium">E-Mail</label>
        <v-text-field
          :id="emailId"
          v-model="email"
          type="email"
          autocomplete="username"
          hide-details="auto"
          :rules="EMAIL_RULES"
          :error-messages="fieldErrors.email"
          @update:model-value="clearFieldErrors"
        />
      </div>
      <div class="d-flex flex-column ga-2">
        <label :for="passwordId" class="text-body-medium font-weight-medium">Passwort</label>
        <v-text-field
          :id="passwordId"
          v-model="password"
          type="password"
          autocomplete="current-password"
          hide-details="auto"
          :rules="PASSWORD_RULES"
          :error-messages="fieldErrors.password"
          @update:model-value="clearFieldErrors"
        />
      </div>
      <v-btn type="submit" color="primary" height="36" block class="mt-2" :loading="busy" :disabled="busy || formValid === false">
        Anmelden
      </v-btn>
    </v-form>

    <p class="text-body-medium text-muted text-center mt-6">
      Noch kein Konto?
      <NuxtLink to="/register" class="text-primary font-weight-medium">Registrieren</NuxtLink>
    </p>
  </v-card>
</template>

<script setup lang="ts">
import { CircleAlert } from '@lucide/vue'
import { FetchError } from 'ofetch'
import type { VForm } from 'vuetify/components'

definePageMeta({
  layout: 'guest',
  middleware: 'guest',
})

const EMAIL_RULES = [
  (value: string) => !!value || 'Bitte gib deine E-Mail-Adresse ein.',
  (value: string) => /^[^\s@]+@[^\s@]+$/.test(value) || 'Bitte gib eine gültige E-Mail-Adresse ein.',
]
const PASSWORD_RULES = [(value: string) => !!value || 'Bitte gib dein Passwort ein.']
const ERROR_VISIBLE_MS = 8000

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const form = ref<VForm | null>(null)
const formValid = ref<boolean | null>(null)
const emailId = useId()
const passwordId = useId()
const email = ref('')
const password = ref('')
const fieldErrors = reactive<{ email: string[], password: string[] }>({ email: [], password: [] })
const error = ref<string | null>(null)
const busy = ref(false)
let errorTimer: ReturnType<typeof setTimeout> | undefined

function showError(message: string) {
  clearTimeout(errorTimer)
  error.value = message
  errorTimer = setTimeout(() => (error.value = null), ERROR_VISIBLE_MS)
}

// Nur Ziele innerhalb der Anwendung und nicht die Anmeldeseite selbst. Der URL-Parser
// deckt auch Schreibweisen ab, die der Browser als fremde Adresse liest ("//host", "/\host").
function redirectTarget() {
  const redirect = route.query.redirect
  if (typeof redirect !== 'string' || !redirect.startsWith('/')) return '/'

  const url = new URL(redirect, window.location.origin)
  if (url.origin !== window.location.origin) return '/'

  const target = url.pathname + url.search + url.hash
  return router.resolve(target).name === 'login' ? '/' : target
}

function clearFieldErrors() {
  fieldErrors.email = []
  fieldErrors.password = []
}

async function submit() {
  const { valid } = await form.value!.validate()
  if (valid === true && busy.value === false) {
    busy.value = true
    clearTimeout(errorTimer)
    error.value = null
    clearFieldErrors()

    try {
      await auth.login(email.value, password.value)
      await navigateTo(redirectTarget())
    }
    catch (caught) {
      const status = caught instanceof FetchError ? caught.response?.status : undefined

      if (status === 422) {
        const errors = (caught as FetchError).data?.errors ?? {}
        if (errors.credentials) {
          showError(errors.credentials[0])
        }
        fieldErrors.email = errors.email ?? []
        fieldErrors.password = errors.password ?? []
      }
      else if (status === 429) {
        showError('Zu viele Anmeldeversuche. Bitte warte eine Minute und versuche es dann erneut.')
      }
      else if (status === undefined) {
        showError('Der Server ist nicht erreichbar. Bitte versuche es später erneut.')
      }
      else {
        showError('Die Anmeldung ist fehlgeschlagen. Bitte versuche es erneut.')
      }
    }
    finally {
      busy.value = false
    }
  }
}

onBeforeUnmount(() => clearTimeout(errorTimer))
</script>
