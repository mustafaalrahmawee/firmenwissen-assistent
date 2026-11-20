<!-- Seitenleiste wie in job-match: Logo, Navigation, Nutzer mit Abmelden unten -->
<template>
  <v-navigation-drawer v-model="ui.drawer" color="sidebar" width="256">
    <v-sheet height="56" color="transparent" class="d-flex align-center justify-space-between px-4">
      <AppLogo />
      <v-btn
        variant="text"
        size="small"
        icon
        class="text-muted"
        aria-label="Seitenleiste schließen"
        title="Seitenleiste schließen"
        @click="ui.toggleDrawer()"
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
        <v-avatar size="32" color="secondary" class="text-body-small font-weight-semibold">
          {{ auth.user.email.charAt(0).toUpperCase() }}
        </v-avatar>
        <div class="d-flex flex-column flex-grow-1 overflow-hidden">
          <span class="text-body-medium font-weight-medium text-truncate">{{ auth.user.email }}</span>
          <span class="text-body-small text-muted text-truncate">Mitglied</span>
        </div>
        <v-btn
          variant="text"
          size="small"
          icon
          class="text-muted"
          aria-label="Abmelden"
          title="Abmelden"
          @click="logout"
        >
          <LogOut :size="16" />
        </v-btn>
      </div>
    </template>
  </v-navigation-drawer>
</template>

<script setup lang="ts">
import { House, LogOut, PanelLeft } from '@lucide/vue'

const NAV = [
  { to: '/', label: 'Start', icon: House },
]

const ui = useUiStore()
const auth = useAuthStore()

async function logout() {
  auth.logout()
  await navigateTo('/login')
}
</script>
