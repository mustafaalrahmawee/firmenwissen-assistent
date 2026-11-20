// https://nuxt.com/docs/api/configuration/nuxt-config
import vuetify, { transformAssetUrls } from 'vite-plugin-vuetify'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  // Reine SPA: alles läuft im Browser, der Token liegt in localStorage
  ssr: false,

  runtimeConfig: {
    public: {
      // Überschreibbar mit NUXT_PUBLIC_API_BASE
      apiBase: 'http://localhost:8000/api/v1',
    },
  },

  css: ['@fontsource-variable/inter', '~/assets/css/main.css'],

  build: {
    transpile: ['vuetify'],
  },

  modules: [
    '@pinia/nuxt',
    // Vuetify: Komponenten werden beim Build automatisch importiert (Tree-Shaking)
    (_options, nuxt) => {
      nuxt.hooks.hook('vite:extendConfig', (config) => {
        // @ts-expect-error plugins ist hier immer ein Array
        config.plugins.push(vuetify({ autoImport: true }))
      })
    },
  ],

  vite: {
    vue: {
      template: { transformAssetUrls },
    },
  },
})
