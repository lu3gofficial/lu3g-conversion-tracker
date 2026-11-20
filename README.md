# LU3G Conversion Tracker

Conversioni dei form di **Elementor Pro** (`lead_submit`) e click su **WhatsApp, telefono ed email** (`contact_click`)
nel dataLayer di **Google Tag Manager**. Se il consenso `ad_user_data` è concesso, l'evento porta email e telefono
normalizzati e cifrati (SHA-256) per le **conversioni avanzate di Google Ads**. GTM manda poi gli eventi a GA4 e Ads con
la configurazione inclusa (`gtm/`). Il plugin non manda niente direttamente a Google.

Plugin WordPress di LU3G Agenzia Web. Requisiti: PHP 7.4+, WordPress 6.0+, Elementor Pro per i form (i click sui
contatti funzionano anche senza), sito in HTTPS per gli identificatori (Web Crypto).

## Struttura
```
lu3g-conversion-tracker/
├─ lu3g-conversion-tracker.php  intestazione (Update URI), avvio
├─ uninstall.php                opzioni, esiti, sessioni
├─ includes/
│  ├─ class-updater.php         GENERATO da lu3gofficial/lu3g-updater (.lu3g-updater): non modificarlo a mano
│  ├─ class-validate.php        nomi evento (regole GA4), valori, valute ISO 4217, paesi
│  ├─ class-settings.php        impostazioni globali + configurazione effettiva di un form con l'origine di ogni valore
│  ├─ class-forms.php           censimento dei form (adapter elementor_form; Atomic Form riconosciuto, non supportato)
│  ├─ class-elementor.php       sezione "Tracciamento LU3G" nel widget Form
│  ├─ class-frontend.php        script nel sito e configurazione (niente dati personali)
│  ├─ class-diagnostics.php     sessione di verifica, esiti, report
│  ├─ class-gtm.php             container GTM da importare, generato con gli ID del pannello
│  └─ class-admin.php           pagina LU3G Tracking
├─ assets/
│  ├─ lu3g-ct-core.js           funzioni pure: email, telefono, SHA-256, validazioni, link di contatto
│  ├─ tracker.js                script nel sito
│  ├─ vendor/libphonenumber-js/ libphonenumber-js 1.13.15 (MIT), bundle "min"
│  └─ admin.css / admin.js      pannello
├─ gtm/
│  ├─ lu3g-conversion-tracker-container.json  container generico (ID da sostituire)
│  ├─ lu3g-ct-bridge.tpl        modello di tag "LU3G CT Bridge" (diagnostica)
│  ├─ lu3g-ct-consent.tpl       modello di tag "LU3G CT Consent" (ponte del consenso, 1.1.0)
│  └─ README.md                 configurazione manuale equivalente
└─ tests/core.test.js           test automatici (node tests/core.test.js)
```

## Contratto del dataLayer (schema 1.0)

Evento form (dopo un invio **riuscito**):
```json
{
  "event": "lead_submit",
  "lu3g": {
    "schema_version": "1.0",
    "event_id": "2e198419-02be-43bb-b9fd-c82ba13ef7bd",
    "source": "elementor_pro",
    "form_key": "123:ab12cd",
    "form_instance_id": "37a24b31-…",
    "form_name": "richiesta_preventivo",
    "page_id": 123,
    "page_path": "/contatti/",
    "value": 25.5,
    "currency": "EUR",
    "conversion_key": "lead",
    "test_mode": false
  },
  "lu3g_user_data": {
    "sha256_email_address": "<64 caratteri esadecimali>",
    "sha256_phone_number": "<64 caratteri esadecimali>"
  }
}
```
Evento contatto:
```json
{
  "event": "contact_click",
  "lu3g": {
    "schema_version": "1.0", "event_id": "…", "source": "link",
    "contact_type": "whatsapp", "contact_id": "commerciale", "placement": "header",
    "page_id": 123, "page_path": "/contatti/", "conversion_key": "contact_whatsapp", "test_mode": false
  },
  "lu3g_user_data": null
}
```
- I campi non disponibili si omettono (niente stringhe vuote). `value` e `currency` ci sono solo se c'è un valore
  (anche 0). `conversion_key` manca se la destinazione Ads è esclusa.
- `form_key` = documento:widget (definizione del form). `form_instance_id` = istanza mostrata nella pagina.
  `form_name` = etichetta statica (impostata o ricavata dal nome del modulo), mai dati compilati.
- `page_path` senza query string né frammento. Non si inviano URL completi, testi dei link, numeri o indirizzi aziendali,
  testo del messaggio WhatsApp, subject/body di mailto.
- **Isolamento**: prima di ogni evento il plugin fa `push({lu3g: null, lu3g_user_data: null})` (GTM unisce gli oggetti in
  modo ricorsivo: senza questo un form con sola email erediterebbe il telefono del precedente). Dopo l'evento,
  nell'`eventCallback` (quando i tag lo hanno letto) azzera di nuovo `lu3g_user_data`. Il resto del dataLayer non si tocca.
  Gli oggetti restano comunque nell'array del dataLayer fino al cambio pagina: il reset non li cancella e gli hash non
  sono dati anonimi. Niente cookie, localStorage, sessionStorage o log con contatti o hash.

## Canali personalizzati (1.2.0)
Scheda **Canali**: WhatsApp, telefono ed email sono sempre pronti. In più, regole con nome, chiave Ads e valore.
Una regola riconosce il link in uno di tre modi:
- **Indirizzo**: domini (`calendly.com`, vale anche per i sottodomini), domini con percorso (`google.com/maps`) o schemi
  (`tg:`).
- **Tipo di file**: `pdf, zip`.
- **Classe CSS**: `.btn-prenota`, vale anche per un pulsante senza link.

Ordine di controllo: prima le classi CSS (le più specifiche), poi WhatsApp/telefono/email, poi le altre regole.
Pronti da accendere: Google Maps, Messenger, Telegram, Download PDF. Il canale arriva in `lu3g.contact_type`.

## Decisioni

**Invio riuscito.** Vale il successo che Elementor restituisce al browser: l'evento jQuery `submit_success` (Elementor lo
lancia con `jQuery.trigger`, quindi un listener nativo con lo stesso nome non lo sentirebbe). Non vuol dire email
consegnata né contatto ricevuto dal CRM. Click sul pulsante, submit nativo, cambio step e avvio AJAX non sono
conversioni. Errori di validazione, CAPTCHA, server o rete non lanciano `submit_success`.
Sorgente verificato: Elementor Pro 4.4 (mirror proelements/proelements). Il form ha i campi nascosti `post_id` (documento
che contiene il form, il template per header/popup) e `form_id` (widget), che danno la chiave.

**Tentativi.** All'evento `submit` (fase di cattura, prima che Elementor legga e poi svuoti il form) si crea un tentativo
con un `event_id` casuale (UUID). Se servono, si fotografano solo email e telefono del form inviato, si normalizzano e si
cifrano. Il successo prende il tentativo più recente di quel form e lo chiude. Un nuovo invio scarta i tentativi
precedenti senza successo, insieme ai loro dati (scadono comunque dopo 2 minuti).
- Doppio callback dello stesso tentativo: un solo evento. Due invii riusciti: due eventi.
- Un hash finito in ritardo scrive solo nel suo tentativo.
- Se gli hash non sono pronti al successo, l'evento parte senza identificatori e non ne parte un secondo più tardi.
- Nessuna deduplica per email, telefono, nome del form o finestra di tempo. `event_id` da solo non deduplica in GA4:
  in Google Ads si usa come ID transazione.

**Redirect.** L'evento viene messo nel dataLayer in modo sincrono dentro `submit_success`, prima che Elementor cambi
pagina, e GTM lo elabora subito. Il plugin non aggiunge attese e non tocca il redirect. I tag Google (GA4, Ads) inviano
con beacon/keepalive. Un tag di terze parti lento può non fare in tempo.

**Email.** Regole Google per le conversioni avanzate: spazi iniziali e finali tolti, minuscolo. Solo per `gmail.com` e
`googlemail.com` si tolgono i punti e il suffisso `+…` prima della @. Il valore inviato da Elementor resta com'è.

**Telefono.** libphonenumber-js 1.13.15 (metadati "min"), formato E.164. Ordine: prefisso esplicito del numero (`+`, oppure
il prefisso internazionale del paese, es. `0039` in Italia), poi il campo paese del form (ISO o prefisso), il paese del
form, il paese del sito (IT).
- Vengono scartati, con il motivo nella diagnostica: numeri incompleti, più numeri nello stesso campo, numeri con
  lettere, numeri ambigui (cifre iniziali uguali al prefisso, senza `+`, quando anche la lettura nazionale è valida).
- L'interno (int., ext., x) si toglie per il matching.
- Un numero scartato non blocca il form: l'evento parte con la sola email.

**Hash.** SHA-256 nel browser (Web Crypto), UTF-8, esadecimale minuscolo. Chiavi `sha256_email_address` e
`sha256_phone_number`. Senza Web Crypto (sito non HTTPS) gli identificatori si omettono: non si ripiega mai sull'invio in
chiaro.

**Consenso.** Adapter: `consent_mode` (stato scritto dal ponte GTM dopo un evento del CMP, oppure comandi
`gtag('consent', …)` nel dataLayer con un `update` esplicito del CMP),
`iubenda` (come il precedente, più la presenza di `_iub.cs.api`), `custom` (`window.lu3gCTConsent()` restituisce
`{ready, analytics_storage, ad_storage, ad_user_data, ad_personalization}`), `none`.
- Gli identificatori servono solo ad Ads e richiedono `ad_user_data` esplicitamente `granted`. Il consenso si controlla
  all'invio e di nuovo subito prima del push.
- Stato solo "default", assente o sconosciuto = niente identificatori, e l'assenza del CMP non vale come consenso.
- Il plugin non concede consensi, non mostra banner, non sovrascrive callback.
- Per Iubenda: l'API JavaScript della Cookie Solution non espone lo stato per finalità quando il consenso è per finalità,
  quindi si leggono i segnali Consent Mode che Iubenda invia (in Iubenda va attivato Google Consent Mode).
- **Ponte del consenso (1.1.0).** Se il CMP gestisce Consent Mode dentro GTM, il tag "LU3G - Ponte consenso" si
  attiva sugli eventi del CMP (Iubenda: regex `^iubenda_`). Esempio: Iubenda con `googleConsentMode: "template"`, che non
  scrive comandi consent nel dataLayer.
  - Il tag legge i quattro segnali con `isConsentGranted` e li scrive in `window.lu3gCTConsentGTM`.
  - Siccome parte solo dopo un evento del CMP, uno stato non impostato non viene preso per un consenso. È il "bridge GTM
    esplicito" chiesto dalle specifiche.
- Gli eventi senza identificatori partono sempre: cosa fanno i tag lo decide GTM con Consent Mode.

**Meta (file per GTM, 1.2.0).** Con l'ID del pixel il file contiene il pixel base (facoltativo), `Lead` sui form e
`Contact` sui click, con `eventID` = `event_id` per la futura deduplica con la Conversions API. I tag partono solo con il
consenso `ad_storage`. Il pixel base parte anche sugli eventi del banner: GTM non riprova da solo un tag bloccato quando
il consenso arriva dopo. Nessun dato personale a Meta: niente advanced matching in chiaro.

**GA4.** Riceve solo metadati ordinari (niente email, telefono o hash; `page_location` senza query string). La funzione
GA4 "User-provided data" richiede un'integrazione separata e non è attiva. `lead_submit` resta il nome: mapparlo su
`generate_lead` va fatto apposta e in sostituzione.

## Configurazione GTM
Pannello → **GTM**: inserisci ID GA4, ID conversione Ads ed etichette per chiave. Poi i file sono due:

- **File completo (consigliato).** Esporta da GTM il container live (Amministrazione → Esporta container), caricalo nel
  pannello e scarica il container completo: tutti gli elementi del sito restano come sono (stessi ID, cartelle, pausa),
  gli elementi LU3G vengono aggiunti o aggiornati per nome, quelli non più generati vengono tolti, i vecchi tag che
  partono sugli stessi eventi possono essere messi in pausa. Il pannello mostra il riepilogo. Si importa con
  Sovrascrivi oppure con Unisci + "Sovrascrivi in conflitto": in entrambi i casi non si perde niente.
- **Solo LU3G.** Contiene solo gli elementi LU3G: va importato **sempre con Unisci** (+ "Sovrascrivi in conflitto").
  Con "Sovrascrivi" GTM sostituisce l'intero container e cancella banner dei cookie, Google tag e tutto il resto.

In alternativa usa `gtm/lu3g-conversion-tracker-container.json` e sostituisci i valori `SOSTITUISCI`. La guida manuale
equivalente è in [gtm/README.md](gtm/README.md).

Salvare le impostazioni in WordPress non aggiorna GTM. Dopo aver cambiato nomi evento, chiavi o etichette, scarica di
nuovo il JSON e importalo. Il plugin non pubblica niente nei container.

## Diagnostica
- **Test locale** (pannello): dati inventati, stessi calcoli dello script. Nessun push nel dataLayer, nessun form inviato.
- **Sessione di verifica**: 30 minuti, solo per l'amministratore che la avvia (nessun parametro pubblico). Nel sito
  compare un riquadro con form rilevati, adapter, consenso, container ed esito di ogni evento. Gli eventi hanno
  `test_mode: true` e gli attivatori LU3G di produzione li escludono. Gli esiti tornano nel pannello senza dati né hash
  (ultimi 20, 7 giorni). Il report JSON si esporta.
- **Esiti**: "OK", "Errore", "Bloccato dal consenso", "Non verificato", "Non applicabile". Livelli: form rilevato, invio
  riuscito, payload, identificatori, dataLayer, GTM, tag, trasmissione, ricezione, attribuzione.
- **Bridge GTM** (`gtm/lu3g-ct-bridge.tpl`, incluso nel JSON): con `addEventCallback` restituisce event_id, container e
  stato dei tag con metadato `lu3g_role` = ga4/ads. Senza bridge il riquadro dice "dataLayer verificato; elaborazione GTM
  non verificata". L'`eventCallback` da solo non prova niente (arriva anche per timeout).
- Trasmissione, ricezione e attribuzione restano "Non verificato": si controllano con Tag Assistant, GA4 DebugView e la
  diagnostica di Google Ads.

## Versioni supportate e limiti accertati
| Componente | Stato |
|---|---|
| Widget Form classico di Elementor Pro | Supportato (flusso verificato sul sorgente di Elementor Pro 4.4) |
| Atomic Form (`e-form`, Elementor Pro 4.x) | **Non supportato**: riconosciuto e segnalato, non genera eventi |
| WordPress | 6.0+ (provato sul 7.x locale) |
| PHP | 7.4+ |
| libphonenumber-js | 1.13.15 (metadati min) |

- Provato su un WordPress locale con un **Elementor Pro simulato** (markup, campi nascosti e `submit_success` jQuery
  come la 4.4), un CMP simulato (comandi consent) e un GTM simulato (merge del modello dati, eventCallback, bridge).
  Restano da provare: Elementor Pro reale (anche multistep e popup veri), Iubenda reale, l'importazione del JSON in un
  container GTM vero, Tag Assistant, DebugView e la diagnostica di Ads. Il formato del JSON segue gli export di GTM
  (exportFormatVersion 2) ma non è stato importato in GTM.
- WP Rocket: gli script del plugin vengono esclusi da "Ritarda JavaScript" con i filtri `rocket_delay_js_exclusions`
  e `rocket_exclude_js`. Con altri plugin di ottimizzazione escludi `lu3g-conversion-tracker/assets/` e `lu3gCTConfig`.
- Se GTM manca o è bloccato, l'evento resta nel dataLayer senza nessuno che lo legga (`eventCallback` non arriva, quindi
  `lu3g_user_data` non viene azzerato).
- L'interno del telefono si scarta. Non c'è un collegamento CRM per le conversioni offline.

## Come provarlo
1. `node tests/core.test.js` (54 test: email, telefoni della tabella delle specifiche, hash, nomi evento, valori, link).
2. Pannello → Panoramica: stato (Elementor Pro, Atomic Form, HTTPS, consenso, configurazione GTM).
3. Pannello → Diagnostica → Test locale con un'email Gmail e `0039 347 123 4567`.
4. Importa il JSON in GTM, apri l'Anteprima, poi Diagnostica → **Avvia sessione di verifica** → invia un form di prova e
   clicca un link WhatsApp: nel riquadro e in Tag Assistant c'è l'evento con lo stesso `event_id`.
5. Con il consenso negato nel banner: evento senza `lu3g_user_data`.

## Pubblicare una nuova versione
1. Aumenta la versione in `lu3g-conversion-tracker.php` (`Version:` e `LU3G_CT_VERSION`) e `Stable tag` in `readme.txt`.
2. Aggiungi la sezione in `CHANGELOG.md` (diventa la nota della release).
3. Commit, poi `git tag vX.Y.Z && git push && git push --tags`, oppure da GitHub: Actions → Release → Run workflow.
