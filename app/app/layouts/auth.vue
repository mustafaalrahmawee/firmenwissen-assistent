<!-- Layout für angemeldete Nutzer: definePageMeta({ layout: 'auth', middleware: 'auth', title: '…' }) -->
<script setup lang="ts">
import { PanelLeft } from '@lucide/vue'
import { useDisplay } from 'vuetify'

const ui = useUiStore()
const route = useRoute()
const { mobile } = useDisplay()

const title = computed(() => (route.meta.title as string | undefined) ?? '')
</script>

<template>
  <!-- Ein Wurzelelement (nicht <slot />), damit Nuxt Layout-Wechsel animieren kann -->
  <v-app>
    <AppSidebar />

    <v-app-bar flat height="56" color="background" class="border-b">
      <v-btn
        v-if="mobile || !ui.drawer"
        variant="text"
        size="small"
        icon
        class="text-muted ml-2"
        aria-label="Seitenleiste öffnen"
        title="Seitenleiste öffnen"
        @click="ui.toggleDrawer()"
      >
        <PanelLeft :size="16" />
      </v-btn>
      <h1 class="text-body-medium font-weight-medium text-truncate mx-4">{{ title }}</h1>

      <template #append>
        <ThemeToggle class="mr-2" />
      </template>
    </v-app-bar>

    <v-main>
      <div class="page mx-auto pa-6">
        <slot />
      </div>
    </v-main>
  </v-app>
</template>

<style scoped>
.page {
  max-width: 672px;
}
</style>
