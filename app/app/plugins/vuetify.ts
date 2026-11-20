import 'vuetify/styles'
import { createVuetify, type ThemeDefinition } from 'vuetify'
import { aliases, mdi } from 'vuetify/iconsets/mdi-svg'
import { de } from 'vuetify/locale'

// Farben aus job-match (oklch nach Hex umgerechnet): neutrale Grautöne,
// fast schwarze Primärfarbe, Indigo als Markenfarbe.
const light: ThemeDefinition = {
  dark: false,
  colors: {
    background: '#FFFFFF',
    'on-background': '#111114',
    surface: '#FFFFFF',
    'on-surface': '#111114',
    primary: '#17181B',
    'on-primary': '#FAFAFA',
    secondary: '#F3F3F5',
    'on-secondary': '#17181B',
    accent: '#ECEDEF',
    'on-accent': '#17181B',
    sidebar: '#F8F9FA',
    'on-sidebar': '#111114',
    brand: '#5165D3',
    'on-brand': '#FAFAFA',
    'muted-foreground': '#696C72',
    info: '#5165D3',
    success: '#1C985A',
    warning: '#DF911A',
    error: '#E7000B',
  },
  variables: {
    'border-color': '#E4E4E7',
    'border-opacity': 1,
    'medium-emphasis-opacity': 0.65,
    'activated-opacity': 0.06,
  },
}

const dark: ThemeDefinition = {
  dark: true,
  colors: {
    background: '#0D0D0F',
    'on-background': '#F1F2F3',
    surface: '#151618',
    'on-surface': '#F1F2F3',
    primary: '#EAEBEC',
    'on-primary': '#111114',
    secondary: '#212224',
    'on-secondary': '#F1F2F3',
    accent: '#252629',
    'on-accent': '#F1F2F3',
    sidebar: '#131416',
    'on-sidebar': '#F1F2F3',
    brand: '#7990F4',
    'on-brand': '#0D0D0F',
    'muted-foreground': '#9C9EA5',
    info: '#7990F4',
    success: '#47B777',
    warning: '#F2AF48',
    error: '#FF6467',
  },
  variables: {
    'border-color': '#FFFFFF',
    'border-opacity': 0.1,
    'medium-emphasis-opacity': 0.7,
    'activated-opacity': 0.08,
  },
}

export default defineNuxtPlugin((nuxtApp) => {
  // Farbschema aus dem Cookie, damit schon das Server-HTML richtig aussieht
  const savedTheme = useCookie<'light' | 'dark'>('theme')

  const vuetify = createVuetify({
    // Server rendert für Desktop-Breite; die Seitenleiste ist dann sofort da
    ssr: { clientWidth: 1280, clientHeight: 800 },
    display: { mobileBreakpoint: 'md' },
    locale: { locale: 'de', messages: { de } },
    icons: { defaultSet: 'mdi', aliases, sets: { mdi } },
    theme: {
      defaultTheme: savedTheme.value === 'dark' ? 'dark' : 'light',
      themes: { light, dark },
    },
    // Flacher, ruhiger Stil wie in job-match
    defaults: {
      VBtn: { variant: 'flat', rounded: 'lg' },
      VCard: { flat: true, border: true, rounded: 'xl' },
      VTextField: { variant: 'outlined', density: 'compact', rounded: 'lg', color: 'primary' },
      VTextarea: { variant: 'outlined', density: 'compact', rounded: 'lg', color: 'primary' },
      VSelect: { variant: 'outlined', density: 'compact', rounded: 'lg', color: 'primary' },
      VAlert: { variant: 'tonal', density: 'compact', rounded: 'lg' },
      VList: { density: 'compact' },
    },
  })

  nuxtApp.vueApp.use(vuetify)
})
