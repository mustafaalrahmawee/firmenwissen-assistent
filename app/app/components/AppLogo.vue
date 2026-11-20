<template>
  <div class="d-flex align-center ga-2">
    <v-avatar size="28" rounded="lg" color="brand">
      <BookOpenText :size="16" />
    </v-avatar>
    <span class="text-title-medium font-weight-semibold tracking-tight">Firmenwissen</span>
  </div>
</template>

<script setup lang="ts">
import { BookOpenText } from '@lucide/vue'
</script>
