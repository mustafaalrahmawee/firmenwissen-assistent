<!-- Layout für Gäste (Login usw.): definePageMeta({ layout: 'guest', middleware: 'guest' }) -->
<template>
  <v-app>
    <v-main class="bg-sidebar">
      <v-btn
        variant="text"
        size="small"
        icon
        class="text-muted position-absolute top-0 right-0 ma-3"
        :aria-label="themeLabel"
        :title="themeLabel"
        @click="theme.toggle()"
      >
        <component :is="isDark ? Sun : Moon" :size="16" />
      </v-btn>

      <v-sheet min-height="100svh" color="transparent" class="d-flex align-center justify-center pa-4">
        <v-sheet max-width="384" color="transparent" class="d-flex flex-column align-center ga-8 w-100">
          <div class="d-flex align-center ga-2">
            <v-avatar size="28" rounded="lg" color="brand">
              <BookOpenText :size="16" />
            </v-avatar>
            <span class="text-title-medium font-weight-semibold tracking-tight">Firmenwissen</span>
          </div>
          <div class="w-100">
            <slot />
          </div>
        </v-sheet>
      </v-sheet>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { BookOpenText, Moon, Sun } from '@lucide/vue'
import { useTheme } from 'vuetify'

const theme = useTheme()

const isDark = computed(() => theme.global.current.value.dark)
const themeLabel = computed(() => (isDark.value ? 'Helles Farbschema' : 'Dunkles Farbschema'))
</script>
