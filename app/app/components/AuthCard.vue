<!-- Karte für Gast-Seiten (Login usw.), wie AuthCard in job-match -->
<template>
  <v-card class="pa-6">
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

<script setup lang="ts">
import { CircleAlert } from '@lucide/vue'

defineProps<{ title: string; description: string; error?: string | null }>()
</script>
