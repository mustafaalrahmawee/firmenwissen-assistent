<!-- Layout für Gäste (Login usw.): definePageMeta({ layout: 'guest', middleware: 'guest' }) -->
<template>
  <v-app>
    <v-main class="bg-sidebar">
      <ThemeToggle class="theme-toggle" />

      <div class="guest d-flex align-center justify-center pa-4">
        <div class="d-flex flex-column align-center ga-8 w-100" style="max-width: 384px">
          <AppLogo />
          <div class="w-100">
            <slot />
          </div>
        </div>
      </div>
    </v-main>
  </v-app>
</template>

<style scoped>
.guest {
  min-height: 100svh;
}

.theme-toggle {
  position: absolute;
  top: 12px;
  right: 12px;
}
</style>
