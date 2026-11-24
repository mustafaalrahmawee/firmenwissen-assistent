<template>
  <div class="d-flex flex-column ga-4">
    <div class="d-flex align-start ga-4">
      <div class="flex-grow-1">
        <h2 class="text-headline-small font-weight-semibold tracking-tight">Dokumente</h2>
        <p class="text-body-medium text-muted mt-1">
          Lege Dokumente in den Arbeitsbereich deiner Firma, damit der Assistent sie kennt.
        </p>
      </div>
      <v-btn color="primary" height="36" class="flex-shrink-0" @click="uploadDialog = true">
        <template #prepend>
          <Upload :size="16" />
        </template>
        Hochladen
      </v-btn>
    </div>

    <DocumentUploadDialog v-model="uploadDialog" action-label="Dateien auswählen" />
  </div>
</template>

<script setup lang="ts">
import { Upload } from '@lucide/vue'

definePageMeta({
  layout: 'auth',
  middleware: 'auth',
  title: 'Dokumente',
})

const uploadDialog = ref(false)
</script>
