<!-- Layout für angemeldete Nutzer: definePageMeta({ layout: 'auth', middleware: 'auth', title: '…' }) -->
<template>
  <!-- Ein Wurzelelement (nicht <slot />), damit Nuxt Layout-Wechsel animieren kann -->
  <v-app>
    <!-- Seitenleiste wie in job-match: Logo, Navigation, Nutzer mit Abmelden unten -->
    <v-navigation-drawer v-model="drawer" color="sidebar" width="256">
      <v-sheet height="56" color="transparent" class="d-flex align-center justify-space-between px-4">
        <div class="d-flex align-center ga-2">
          <v-avatar size="28" rounded="lg" color="brand">
            <BookOpenText :size="16" />
          </v-avatar>
          <span class="text-title-medium font-weight-semibold tracking-tight">Firmenwissen</span>
        </div>
        <v-btn
          variant="text"
          size="small"
          icon
          class="text-muted"
          aria-label="Seitenleiste schließen"
          title="Seitenleiste schließen"
          @click="drawer = false"
        >
          <PanelLeft :size="16" />
        </v-btn>
      </v-sheet>

      <v-list nav base-color="muted-foreground" color="on-surface" class="px-2 pt-1">
        <v-list-item v-for="item in NAV" :key="item.to" :to="item.to" exact rounded="lg">
          <template #prepend>
            <component :is="item.icon" :size="16" class="mr-3" />
          </template>
          <template #default="{ isActive }">
            <v-list-item-title :class="['text-body-medium', isActive && 'font-weight-medium']">
              {{ item.label }}
            </v-list-item-title>
          </template>
        </v-list-item>
      </v-list>
    </v-navigation-drawer>

    <v-app-bar flat height="56" color="background" class="border-b">
      <v-btn
        v-if="mobile || !drawer"
        variant="text"
        size="small"
        icon
        class="text-muted ml-2"
        aria-label="Seitenleiste öffnen"
        title="Seitenleiste öffnen"
        @click="drawer = true"
      >
        <PanelLeft :size="16" />
      </v-btn>
      <h1 class="text-body-medium font-weight-medium text-truncate mx-4">{{ title }}</h1>

      <template #append>
        <v-btn
          variant="text"
          size="small"
          icon
          class="text-muted mr-2"
          :aria-label="themeLabel"
          :title="themeLabel"
          @click="theme.toggle()"
        >
          <component :is="isDark ? Sun : Moon" :size="16" />
        </v-btn>
      </template>
    </v-app-bar>

    <v-main>
      <v-sheet max-width="672" color="transparent" class="mx-auto pa-6">
        <slot />
      </v-sheet>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { BookOpenText, House, Moon, PanelLeft, Sun } from '@lucide/vue'
import { useDisplay, useTheme } from 'vuetify'

const NAV = [
  { to: '/', label: 'Start', icon: House },
]

const route = useRoute()
const { mobile } = useDisplay()
const theme = useTheme()

// null: Vuetify entscheidet nach Bildschirmbreite (Desktop offen, Mobil zu)
const drawer = ref<boolean | null>(null)

const title = computed(() => (route.meta.title as string | undefined) ?? '')
const isDark = computed(() => theme.global.current.value.dark)
const themeLabel = computed(() => (isDark.value ? 'Helles Farbschema' : 'Dunkles Farbschema'))
</script>
