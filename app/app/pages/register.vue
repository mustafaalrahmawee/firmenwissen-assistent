<template>
  <v-card class="pa-6">
    <h1 class="text-title-large font-weight-semibold">Konto anlegen</h1>
    <p class="text-body-medium text-muted mt-1 mb-6">
      Lege ein Konto an und bekomme einen eigenen Arbeitsbereich für die Dokumente deiner Firma.
    </p>

    <v-alert v-if="error" type="error" class="mb-4" role="alert">
      <template #prepend>
        <CircleAlert :size="16" />
      </template>
      {{ error }}
    </v-alert>

    <v-form ref="form" v-model="formValid" class="d-flex flex-column ga-4" @submit.prevent="submit">
      <div class="d-flex flex-column ga-2">
        <label :for="nameId" class="text-body-medium font-weight-medium">Name</label>
        <v-text-field
          :id="nameId"
          v-model="name"
          autocomplete="name"
          hide-details="auto"
          :rules="NAME_RULES"
          :error-messages="fieldErrors.name"
          @update:model-value="clearFieldErrors"
        />
      </div>
      <div class="d-flex flex-column ga-2">
        <label :for="companyId" class="text-body-medium font-weight-medium">Firma</label>
        <v-text-field
          :id="companyId"
          v-model="company"
          autocomplete="organization"
          hide-details="auto"
          :rules="COMPANY_RULES"
          :error-messages="fieldErrors.company"
          @update:model-value="clearFieldErrors"
        />
      </div>
      <div class="d-flex flex-column ga-2">
        <label :for="emailId" class="text-body-medium font-weight-medium">E-Mail</label>
        <v-text-field
          :id="emailId"
          v-model="email"
          type="email"
          autocomplete="email"
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
          autocomplete="new-password"
          hide-details="auto"
          :rules="PASSWORD_RULES"
          :error-messages="fieldErrors.password"
          @update:model-value="passwordChanged"
        />
      </div>
      <div class="d-flex flex-column ga-2">
        <label :for="confirmationId" class="text-body-medium font-weight-medium">Passwort wiederholen</label>
        <v-text-field
          :id="confirmationId"
          ref="confirmationField"
          v-model="passwordConfirmation"
          type="password"
          autocomplete="new-password"
          hide-details="auto"
          :rules="confirmationRules"
          @update:model-value="clearFieldErrors"
        />
      </div>
      <v-btn type="submit" color="primary" height="36" block class="mt-2" :loading="busy" :disabled="busy || formValid === false">
        Registrieren
      </v-btn>
    </v-form>

    <p class="text-body-medium text-muted text-center mt-6">
      Schon ein Konto?
      <NuxtLink to="/login" class="text-primary font-weight-medium">Anmelden</NuxtLink>
    </p>
  </v-card>
</template>

<script setup lang="ts">
import { CircleAlert } from '@lucide/vue'
import { FetchError } from 'ofetch'
import type { VForm, VTextField } from 'vuetify/components'
import { AccountCreatedError } from '~/stores/auth'

definePageMeta({
  layout: 'guest',
  middleware: 'guest',
})

const PASSWORD_RULE = 'Das Passwort muss mindestens 8 Zeichen lang sein, Buchstaben und Ziffern enthalten und mit einem Großbuchstaben beginnen.'
const PASSWORD_MAX_BYTES = 72

const NAME_RULES = [
  (value: string) => !!value.trim() || 'Bitte gib deinen Namen ein.',
  (value: string) => value.length <= 255 || 'Der Name darf höchstens 255 Zeichen lang sein.',
]
const COMPANY_RULES = [
  (value: string) => !!value.trim() || 'Bitte gib den Namen deiner Firma ein.',
  (value: string) => value.length <= 255 || 'Der Name der Firma darf höchstens 255 Zeichen lang sein.',
]
const EMAIL_RULES = [
  (value: string) => !!value || 'Bitte gib deine E-Mail-Adresse ein.',
  (value: string) => /^[^\s@]+@[^\s@]+$/.test(value) || 'Bitte gib eine gültige E-Mail-Adresse ein.',
]
const PASSWORD_RULES = [
  (value: string) => !!value || 'Bitte gib ein Passwort ein.',
  (value: string) => (value.length >= 8 && /^\p{Lu}/u.test(value) && /\p{L}/u.test(value) && /[0-9]/.test(value)) || PASSWORD_RULE,
  (value: string) => new TextEncoder().encode(value).length <= PASSWORD_MAX_BYTES
    || 'Das Passwort ist zu lang. Erlaubt sind 72 Byte: 72 Zeichen ohne Umlaute und Sonderzeichen, mit ihnen weniger.',
]
const ERROR_VISIBLE_MS = 8000

const auth = useAuthStore()
const form = ref<VForm | null>(null)
const confirmationField = ref<VTextField | null>(null)
const formValid = ref<boolean | null>(null)
const nameId = useId()
const companyId = useId()
const emailId = useId()
const passwordId = useId()
const confirmationId = useId()
const name = ref('')
const company = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const fieldErrors = reactive<{ name: string[], company: string[], email: string[], password: string[] }>({
  name: [],
  company: [],
  email: [],
  password: [],
})
const error = ref<string | null>(null)
const busy = ref(false)
let errorTimer: ReturnType<typeof setTimeout> | undefined

const confirmationRules = [
  (value: string) => !!value || 'Bitte wiederhole das Passwort.',
  (value: string) => value === password.value || 'Die beiden Passwörter stimmen nicht überein.',
]

function showError(message: string) {
  clearTimeout(errorTimer)
  error.value = message
  errorTimer = setTimeout(() => (error.value = null), ERROR_VISIBLE_MS)
}

function clearFieldErrors() {
  fieldErrors.name = []
  fieldErrors.company = []
  fieldErrors.email = []
  fieldErrors.password = []
}

// Die Wiederholung neu prüfen, sobald sich das Passwort ändert, nicht erst bei ihrer eigenen Eingabe
function passwordChanged() {
  clearFieldErrors()
  if (passwordConfirmation.value) {
    confirmationField.value?.validate()
  }
}

async function submit() {
  const { valid } = await form.value!.validate()
  if (valid === true && busy.value === false) {
    busy.value = true
    clearTimeout(errorTimer)
    error.value = null
    clearFieldErrors()

    try {
      await auth.register({
        name: name.value,
        company: company.value,
        email: email.value,
        password: password.value,
        password_confirmation: passwordConfirmation.value,
      })
      await navigateTo('/')
    }
    catch (caught) {
      const status = caught instanceof FetchError ? caught.response?.status : undefined

      if (caught instanceof AccountCreatedError) {
        showError('Dein Konto ist angelegt, aber die Anmeldung danach hat nicht geklappt. Bitte melde dich über „Anmelden“ an.')
      }
      else if (status === 422) {
        const errors = (caught as FetchError).data?.errors ?? {}
        fieldErrors.name = errors.name ?? []
        fieldErrors.company = errors.company ?? []
        fieldErrors.email = errors.email ?? []
        fieldErrors.password = errors.password ?? []
      }
      else if (status === 429) {
        showError('Zu viele Registrierungen von diesem Anschluss. Bitte versuche es später erneut.')
      }
      else if (status === undefined) {
        showError('Der Server ist nicht erreichbar. Bitte versuche es später erneut.')
      }
      else {
        showError('Die Registrierung ist fehlgeschlagen. Bitte versuche es erneut.')
      }
    }
    finally {
      busy.value = false
    }
  }
}

onBeforeUnmount(() => clearTimeout(errorTimer))
</script>
