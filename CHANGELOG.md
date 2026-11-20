# Registro delle modifiche

## 1.0.0
Prima versione.
- **Form di Elementor Pro** (widget Form classico): evento `lead_submit` (o uno alternativo per form, es.
  `newsletter_signup`) solo dopo l'invio riuscito (`submit_success` di Elementor). Ogni invio è un tentativo con il suo
  `event_id`: un doppio callback dà un solo evento, due invii due eventi, gli invii falliti nessuno.
- **Conversioni avanzate**: email (regole Google, Gmail compreso) e telefono (libphonenumber-js 1.13.15, E.164)
  normalizzati e cifrati SHA-256 nel browser, in `lu3g_user_data`, solo con il consenso `ad_user_data` esplicitamente
  concesso (adapter Consent Mode, Iubenda, personalizzato, nessuno).
- **Click sui contatti**: WhatsApp click-to-chat, `tel:`, `mailto:` in tutto il sito, `contact_click` con tipo, posizione
  e chiave di conversione per canale.
- **Configurazione**: pagina LU3G Tracking (impostazioni globali, form, contatti, consenso) e sezione "Tracciamento LU3G"
  nel widget Form, con precedenza form → globale; validazione dei nomi evento (regole GA4), valori (0, vuoto, virgola),
  valute ISO 4217.
- **GTM**: container da importare generato con gli ID del pannello (GA4, Ads con conversioni avanzate e ID transazione,
  linker, bridge diagnostico) e guida manuale equivalente.
- **Diagnostica**: test locale, sessione di verifica di 30 minuti con riquadro nel sito ed esiti per livello, report JSON
  senza dati personali.
- Atomic Form riconosciuto ma non ancora supportato.
