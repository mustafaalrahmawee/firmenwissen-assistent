<script setup lang="ts">
import { BookOpenText } from '@lucide/vue'
</script>

<template>
  <div class="d-flex align-center ga-2">
    <div class="logo-mark d-flex align-center justify-center rounded-lg bg-brand">
      <BookOpenText :size="16" />
    </div>
    <span class="logo-text font-weight-semibold tracking-tight">Firmenwissen</span>
  </div>
</template>

<style scoped>
.logo-mark {
  width: 28px;
  height: 28px;
}

.logo-text {
  font-size: 15px;
}
</style>
