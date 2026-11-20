<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue'

const ui = useUiStore()
</script>

<template>
  <v-btn
    variant="text"
    size="small"
    icon
    class="text-muted"
    :aria-label="ui.dark ? 'Helles Farbschema' : 'Dunkles Farbschema'"
    :title="ui.dark ? 'Helles Farbschema' : 'Dunkles Farbschema'"
    @click="ui.toggleDark()"
  >
    <component :is="ui.dark ? Sun : Moon" :size="16" />
  </v-btn>
</template>
