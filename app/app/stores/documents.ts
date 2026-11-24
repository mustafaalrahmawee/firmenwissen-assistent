import { defineStore } from 'pinia'

export interface UploadedDocument {
  id: string
  name: string
  format: 'pdf' | 'markdown'
  size: number
  created_at: string
}

// Reicht für 10 MB auch über eine langsame Leitung; danach gilt der Server als nicht erreichbar
const UPLOAD_TIMEOUT_MS = 5 * 60 * 1000

export const useDocumentsStore = defineStore('documents', () => {
  // Eine Datei je Anfrage; der Arbeitsbereich ergibt sich auf dem Server aus der Anmeldung
  async function upload(file: File) {
    const body = new FormData()
    body.append('file', file)

    const { data } = await $api<{ data: UploadedDocument }>('/documents', {
      method: 'POST',
      body,
      timeout: UPLOAD_TIMEOUT_MS,
    })

    return data
  }

  return { upload }
})
