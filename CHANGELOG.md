# Registro delle modifiche

## 1.2.1
- **File per GTM, pixel Meta**: il tag "LU3G - Meta - Pixel base" era impostato "una volta per pagina". Al caricamento
  il consenso non è ancora confermato (il modello Iubenda lo aggiorna poco dopo), GTM bloccava il tag e lo considerava
  già usato, quindi non lo riprovava all'evento del banner: il pixel non partiva mai e nemmeno Lead / Contact arrivavano
  a Meta. Ora il tag è "una volta per evento", con un controllo nella pagina che lo esegue una sola volta.
  Trovato verificando la Consent Mode su un sito reale. **Dopo l'aggiornamento riscarica il file e reimportalo in GTM**
  (opzione "Sovrascrivi" per i tag LU3G).

## 1.2.0
- **Canali personalizzati** (scheda Canali, prima "Contatti"): oltre a WhatsApp, telefono ed email, regole tue con nome,
  "riconosci dal" (indirizzo del link: domini, domini con percorso o schemi; tipo di file; classe CSS di un pulsante,
  anche senza link), chiave Ads e valore. Pronti da accendere: Indicazioni (Google Maps), Messenger, Telegram, Download
  PDF. Il canale arriva in `contact_type` (es. `maps`, `download`, `prenotazioni`). Mai una ricerca libera nel testo
  dell'indirizzo: dominio e inizio del percorso.
- **Pannello semplificato**: Impostazioni con solo le cose di base (form sì/no, nome evento, conversioni avanzate,
  banner dei cookie); valore, valuta, paese, chiavi, container, test e adapter personalizzato nella sezione "Avanzate".
  La scheda Consenso è unita a Impostazioni. Nella scheda Form e nel widget di Elementor in vista solo "Traccia questo
  form" e il nome evento; il resto dietro "Avanzate" / "Opzioni avanzate" (i valori già salvati restano validi).
- **Valore delle conversioni**: spiegato che per un sito di contatti di solito resta vuoto (si conta il lead; il "una
  per persona" si imposta in Google Ads con Conteggio: Una). Senza valore non partono né valore né valuta.
- **Meta nel file per GTM**: con l'ID del pixel il file contiene "LU3G - Meta - Pixel base" (facoltativo), "Lead" sui form
  e "Contact" sui click, con `eventID` = `event_id`, solo con il consenso `ad_storage`. Il pixel base parte anche
  sull'evento del banner, così se il visitatore accetta dopo il caricamento della pagina il pixel si attiva allora.
- **Linker conversioni** facoltativo nel file ("Il container ha già il Linker conversioni"). Le chiavi dei canali
  personalizzati entrano nella tabella delle etichette Ads.
- Correzione: una riga di canale incompleta non viene più salvata (prima il pannello mostrava l'errore ma la salvava).

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
