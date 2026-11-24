<template>
  <v-dialog v-model="model" max-width="480" :persistent="busy">
    <v-card class="pa-6">
      <h3 class="text-title-large font-weight-semibold">Dokumente hochladen</h3>
      <p class="text-body-medium text-muted mt-1">
        PDF und Markdown (.pdf, .md, .markdown), höchstens 10 MB je Datei. Du kannst mehrere Dateien auf einmal
        auswählen; Dateinamen mit mehr als 255 Zeichen werden gekürzt. Danach werden die Dokumente verarbeitet und
        sind erst dann durchsuchbar.
      </p>

      <div v-if="progress" class="d-flex align-center ga-3 mt-4" role="status">
        <v-progress-circular indeterminate size="16" width="2" color="brand" class="flex-shrink-0" />
        <span class="text-body-medium text-truncate">
          {{ progress.current }} von {{ progress.total }}: {{ progress.name }}
        </span>
      </div>

      <div class="d-flex justify-end ga-2 mt-6">
        <v-btn variant="text" height="36" :disabled="busy" @click="model = false">Schließen</v-btn>
        <v-btn color="primary" height="36" :loading="busy" :disabled="busy" @click="picker?.click()">
          {{ actionLabel }}
        </v-btn>
      </div>
      <input
        ref="picker"
        type="file"
        multiple
        accept=".pdf,.md,.markdown"
        class="d-none"
        aria-label="Dateien zum Hochladen auswählen"
        @change="pick"
      >
    </v-card>
  </v-dialog>

  <v-snackbar-queue v-model="messages" location="bottom end" total-visible="3" />
</template>

<script setup lang="ts">
import { FetchError } from 'ofetch'

interface Message {
  text: string
  color: 'success' | 'warning' | 'error'
  timeout: number
}

// Offen oder geschlossen; die Seite bindet es mit v-model
const model = defineModel<boolean>({ default: false })

const { actionLabel = 'Dateien auswählen' } = defineProps<{
  actionLabel?: string
}>()

const documents = useDocumentsStore()
const picker = ref<HTMLInputElement | null>(null)
const busy = ref(false)
const progress = ref<{ current: number, total: number, name: string } | null>(null)
const messages = ref<Message[]>([])

function notify(color: Message['color'], text: string) {
  messages.value.push({ text, color, timeout: 5000 })
}

// Lädt eine Datei hoch und meldet ihr Ergebnis; ein zurückgegebener Grund beendet die ganze Auswahl
async function uploadFile(file: File): Promise<string | undefined> {
  // Prüft vorab, was sich ohne Server sagen lässt; verbindlich prüft der Server
  const extension = file.name.includes('.') ? file.name.split('.').pop()!.toLowerCase() : ''
  const isAllowedExtension = ['pdf', 'md', 'markdown'].includes(extension)
  if (!isAllowedExtension) {
    notify('error', `„${file.name}“: Erlaubt sind nur PDF- und Markdown-Dateien (.pdf, .md, .markdown).`)
    return
  }
  if (file.size > 10 * 1024 * 1024) {
    notify('error', `„${file.name}“: Die Datei ist zu groß. Erlaubt sind höchstens 10 MB.`)
    return
  }

  try {
    const uploaded = await documents.upload(file)
    notify('success', uploaded.name === file.name
      ? `„${file.name}“ hochgeladen. Es wird noch verarbeitet und ist bis dahin nicht durchsuchbar.`
      : `„${file.name}“ hochgeladen als „${uploaded.name}“, weil der Name zu lang war. Es wird noch verarbeitet.`)
  }
  catch (caught) {
    const status = caught instanceof FetchError ? caught.response?.status : undefined

    // Ohne Antwort kann die Datei trotzdem gespeichert sein, etwa wenn die Verbindung danach abbrach
    if (status === undefined) {
      notify('warning', `„${file.name}“: Keine Antwort vom Server. Ob die Datei angekommen ist, ist unklar; wähle sie später erneut aus.`)
      return 'Der Server ist nicht erreichbar.'
    }
    if (status === 422) {
      notify('error', `„${file.name}“: ${(caught as FetchError).data?.errors?.file?.[0] ?? 'Die Datei wurde abgewiesen.'}`)
      return
    }
    if (status === 413) {
      notify('error', `„${file.name}“: Die Datei ist zu groß. Erlaubt sind höchstens 10 MB.`)
      return
    }
    if (status === 429) {
      notify('error', `„${file.name}“: Zu viele Uploads in kurzer Zeit. Bitte versuche es später erneut.`)
      return 'Zu viele Uploads in kurzer Zeit.'
    }
    if (status === 401) {
      notify('error', `„${file.name}“: Deine Anmeldung ist abgelaufen.`)
      return 'Deine Anmeldung ist abgelaufen.'
    }
    notify('error', `„${file.name}“: Das Hochladen ist fehlgeschlagen. Bitte versuche es erneut.`)
  }
}

// Lädt die ausgewählten Dateien nacheinander hoch, je Datei eine Anfrage, und schließt danach den Dialog
async function pick(event: Event) {
  const input = event.target as HTMLInputElement
  const files = Array.from(input.files ?? [])
  input.value = ''

  if (files.length === 0 || busy.value) {
    return
  }

  busy.value = true

  // Die Auswahl gehört zur Anmeldung, unter der sie begann; meldet sich in einem anderen Tab
  // jemand anders an, gehen die übrigen Dateien nicht mit dessen Zugang in seinen Arbeitsbereich
  const startedWithToken = localStorage.getItem(TOKEN_KEY)

  try {
    for (const [index, file] of files.entries()) {
      if (localStorage.getItem(TOKEN_KEY) !== startedWithToken) {
        notify('error', `${files.length - index} Datei(en) nicht hochgeladen: In diesem Browser hat sich die Anmeldung geändert. Bitte lade die Seite neu.`)
        break
      }

      progress.value = { current: index + 1, total: files.length, name: file.name }
      const stopReason = await uploadFile(file)

      const remaining = files.length - index - 1
      if (stopReason) {
        if (remaining > 0) {
          notify('error', `${remaining} weitere Datei(en) nicht hochgeladen: ${stopReason}`)
        }
        break
      }
    }
  }
  finally {
    busy.value = false
    progress.value = null
    model.value = false
  }
}
</script>
