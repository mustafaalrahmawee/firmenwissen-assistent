import { defineStore } from 'pinia'

// Oberflächenzustand: Farbschema (im Cookie gespeichert) und Seitenleiste
export const useUiStore = defineStore('ui', () => {
  const theme = useCookie<'light' | 'dark'>('theme', {
    default: () => 'light',
    maxAge: 60 * 60 * 24 * 365,
    sameSite: 'lax',
  })
  const dark = computed(() => theme.value === 'dark')

  // null: Vuetify entscheidet nach Bildschirmbreite (Desktop offen, Mobil zu)
  const drawer = ref<boolean | null>(null)

  function toggleDark() {
    theme.value = dark.value ? 'light' : 'dark'
  }

  function toggleDrawer() {
    drawer.value = !drawer.value
  }

  return { theme, dark, drawer, toggleDark, toggleDrawer }
})
