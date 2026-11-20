<!-- Karte für Gast-Seiten (Login usw.), wie AuthCard in job-match -->
<script setup lang="ts">
import { CircleAlert } from '@lucide/vue'

defineProps<{ title: string; description: string; error?: string | null }>()
</script>

<template>
  <v-card class="pa-6 auth-card">
    <h1 class="text-title-large font-weight-semibold">{{ title }}</h1>
    <p class="text-body-medium text-muted mt-1 mb-6">{{ description }}</p>

    <v-alert v-if="error" type="error" class="mb-4" role="alert">
      <template #prepend>
        <CircleAlert :size="16" />
      </template>
      {{ error }}
    </v-alert>

    <slot />
  </v-card>
</template>

<style scoped>
.auth-card {
  box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}
</style>
