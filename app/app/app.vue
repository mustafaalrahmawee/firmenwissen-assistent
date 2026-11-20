<script setup lang="ts">
import { useTheme } from 'vuetify'

const ui = useUiStore()
const theme = useTheme()

watch(() => ui.theme, (name) => theme.change(name))
</script>

<template>
  <NuxtRouteAnnouncer />
  <!-- Das Layout (app/layouts/) wählt jede Seite über definePageMeta({ layout }) -->
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>
