<template>
  <div class="d-flex flex-column ga-4">
    <h2 class="text-headline-small font-weight-semibold tracking-tight">Firmenwissen-Assistent</h2>
    <p class="text-body-medium text-muted">Angemeldet als {{ auth.user?.email }}</p>

    <v-card v-for="feature in FEATURES" :key="feature.title" class="d-flex align-center ga-3 pa-4">
      <v-avatar size="36" rounded="lg" color="secondary">
        <component :is="feature.icon" :size="16" />
      </v-avatar>
      <div class="flex-grow-1 overflow-hidden">
        <p class="text-body-medium font-weight-medium">{{ feature.title }}</p>
        <p class="text-body-medium text-muted">{{ feature.text }}</p>
      </div>
      <v-chip size="small" variant="tonal" color="muted-foreground" class="flex-shrink-0">bald</v-chip>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { FileUp, MessageSquare, Plug } from '@lucide/vue'

definePageMeta({
  layout: 'auth',
  middleware: 'auth',
  title: 'Start',
})

const auth = useAuthStore()

// Geplante Bereiche laut PROJEKT.md
const FEATURES = [
  { icon: FileUp, title: 'Dokumente', text: 'PDF und Markdown hochladen, Verarbeitung mit Status.' },
  { icon: MessageSquare, title: 'Chat', text: 'Fragen stellen, Antworten mit Quellen und Textstelle.' },
  { icon: Plug, title: 'MCP', text: 'Dasselbe Wissen in Claude, Cursor oder VS Code nutzen.' },
]
</script>
