<template>
  <v-btn
    variant="text"
    size="small"
    icon
    class="text-muted"
    :aria-label="label"
    :title="label"
    @click="ui.toggleDark()"
  >
    <component :is="ui.dark ? Sun : Moon" :size="16" />
  </v-btn>
</template>

<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue'

const ui = useUiStore()

const label = computed(() => (ui.dark ? 'Helles Farbschema' : 'Dunkles Farbschema'))
</script>
