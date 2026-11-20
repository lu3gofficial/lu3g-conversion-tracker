# Registro delle modifiche

## 1.1.0
- **Ponte del consenso** per i CMP che gestiscono Consent Mode dentro GTM (es. Iubenda con `googleConsentMode: "template"`,
  trovato su un sito reale): in questo caso nel dataLayer non c'è nessun comando `gtag('consent', …)` e il plugin
  considerava il consenso sconosciuto, quindi non inviava mai gli identificatori.
  - Nuovo modello GTM "LU3G CT Consent" (`gtm/lu3g-ct-consent.tpl`), incluso nel JSON con il tag "LU3G - Ponte consenso".
    Sugli eventi del CMP (Iubenda: `^iubenda_`, impostabile nella scheda Consenso) legge con `isConsentGranted` i quattro
    segnali e li scrive in `window.lu3gCTConsentGTM`.
  - Si attiva solo dopo un evento del CMP: uno stato "non impostato", che `isConsentGranted` considera concesso, non
    viene mai preso per un consenso.
  - Gli adapter "Consent Mode" e "Iubenda" usano questo stato quando c'è, altrimenti i comandi consent nel dataLayer.
    La diagnostica mostra da dove arriva il consenso.
- Pannello → GTM: download anche del modello del ponte.

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
