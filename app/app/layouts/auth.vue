<!-- Layout für angemeldete Nutzer: definePageMeta({ layout: 'auth', middleware: 'auth', title: '…' }) -->
<template>
  <!-- Ein Wurzelelement (nicht <slot />), damit Nuxt Layout-Wechsel animieren kann -->
  <v-app>
    <v-navigation-drawer v-model="drawer" color="sidebar" width="256">
      <v-sheet height="56" color="transparent" class="d-flex align-center justify-space-between px-4">
        <div class="d-flex align-center ga-2 overflow-hidden">
          <v-avatar size="28" rounded="lg" color="brand" class="flex-shrink-0">
            <BookOpenText :size="16" />
          </v-avatar>
          <div class="d-flex flex-column overflow-hidden">
            <span class="text-title-medium font-weight-semibold tracking-tight">Firmenwissen</span>
            <v-tooltip v-if="auth.user" :text="auth.user.tenant.name" location="bottom start" open-on-click>
              <template #activator="{ props }">
                <span
                  v-bind="props"
                  tabindex="0"
                  class="text-body-small text-muted text-truncate"
                  :aria-label="`Arbeitsbereich: ${auth.user.tenant.name}`"
                >
                  {{ auth.user.tenant.name }}
                </span>
              </template>
            </v-tooltip>
          </div>
        </div>
        <v-btn
          variant="text"
          size="small"
          icon
          class="text-muted flex-shrink-0"
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

      <template v-if="auth.user" #append>
        <div class="d-flex align-center ga-3 border-t px-3 py-3">
          <v-avatar size="32" color="secondary" class="text-label-medium font-weight-semibold">
            {{ auth.user.name.charAt(0).toUpperCase() }}
          </v-avatar>
          <div class="d-flex flex-column flex-grow-1 overflow-hidden">
            <span class="text-body-medium font-weight-medium text-truncate">{{ auth.user.name }}</span>
            <span class="text-body-small text-muted text-truncate">{{ auth.user.email }}</span>
          </div>
          <v-btn
            variant="text"
            size="small"
            icon
            class="text-muted flex-shrink-0"
            aria-label="Abmelden"
            title="Abmelden"
            :loading="loggingOut"
            :disabled="loggingOut"
            @click="logout"
          >
            <LogOut :size="16" />
          </v-btn>
        </div>
      </template>
    </v-navigation-drawer>

    <v-snackbar v-model="logoutFailed" color="error" :timeout="8000">
      Abmelden hat nicht geklappt. Bitte versuche es erneut.
    </v-snackbar>

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
import { BookOpenText, FileText, House, LogOut, Moon, PanelLeft, Sun } from '@lucide/vue'
import { FetchError } from 'ofetch'
import { useDisplay, useTheme } from 'vuetify'

const NAV = [
  { to: '/', label: 'Start', icon: House },
  { to: '/documents', label: 'Dokumente', icon: FileText },
]

const auth = useAuthStore()
const route = useRoute()
const { mobile } = useDisplay()
const theme = useTheme()

// null: Vuetify entscheidet nach Bildschirmbreite (Desktop offen, Mobil zu)
const drawer = ref<boolean | null>(null)

const loggingOut = ref(false)
const logoutFailed = ref(false)

// Bei 401 war der Zugang schon ungültig: $api löscht ihn und geht zur Anmeldeseite.
// Jeder andere Fehler lässt die Person angemeldet, damit sie es erneut versuchen kann.
async function logout() {
  loggingOut.value = true
  logoutFailed.value = false

  try {
    await auth.logout()
    await navigateTo('/login')
  }
  catch (caught) {
    const status = caught instanceof FetchError ? caught.response?.status : undefined
    if (status !== 401) {
      logoutFailed.value = true
    }
  }
  finally {
    loggingOut.value = false
  }
}

const title = computed(() => (route.meta.title as string | undefined) ?? '')
const isDark = computed(() => theme.global.current.value.dark)
const themeLabel = computed(() => (isDark.value ? 'Helles Farbschema' : 'Dunkles Farbschema'))
</script>
