<!-- Layout für Gäste (Login usw.): definePageMeta({ layout: 'guest', middleware: 'guest' }) -->
<template>
  <v-app>
    <v-main class="bg-sidebar">
      <ThemeToggle class="position-absolute top-0 right-0 ma-3" />

      <v-sheet min-height="100svh" color="transparent" class="d-flex align-center justify-center pa-4">
        <v-sheet max-width="384" color="transparent" class="d-flex flex-column align-center ga-8 w-100">
          <AppLogo />
          <div class="w-100">
            <slot />
          </div>
        </v-sheet>
      </v-sheet>
    </v-main>
  </v-app>
</template>
