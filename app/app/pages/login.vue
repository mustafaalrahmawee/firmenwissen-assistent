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

    <form class="d-flex flex-column ga-4" @submit.prevent="submit">
      <div class="d-flex flex-column ga-2">
        <label :for="emailId" class="text-body-medium font-weight-medium">E-Mail</label>
        <v-text-field
          :id="emailId"
          v-model="email"
          type="email"
          autocomplete="username"
          hide-details="auto"
          required
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
          required
        />
      </div>
      <v-btn type="submit" color="primary" height="36" block class="mt-2" :loading="busy">
        Anmelden
      </v-btn>
    </form>
  </v-card>
</template>

<script setup lang="ts">
import { CircleAlert } from '@lucide/vue'

definePageMeta({
  layout: 'guest',
  middleware: 'guest',
})

const emailId = useId()
const passwordId = useId()
const email = ref('')
const password = ref('')
const error = ref<string | null>(null)
const busy = ref(false)

async function submit() {
  // TODO: Anmeldung über $api('/login', …)
}
</script>
