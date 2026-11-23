// Lädt beim Start, wer mit dem gespeicherten Zugang angemeldet ist, bevor die erste Seite erscheint.
// Bei 401 löscht $api den Zugang und geht zur Anmeldeseite; ist der Server nicht erreichbar,
// bleibt der Zugang stehen und die Anwendung startet trotzdem.
export default defineNuxtPlugin(async () => {
  if (!localStorage.getItem(TOKEN_KEY)) return

  try {
    await useAuthStore().fetchUser()
  }
  catch {
    // Fehler werden hier bewusst geschluckt, siehe oben
  }
})
