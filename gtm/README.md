# Configurazione GTM manuale (equivalente al JSON)

Tutti gli elementi iniziano con **LU3G -**. Sostituisci gli ID indicati. Non pubblicare prima di aver verificato in
Anteprima.

## Variabili
| Nome | Tipo | Valore |
|---|---|---|
| LU3G - event_id, form_name, form_key, form_instance_id, page_id, page_path, value, currency, conversion_key, test_mode, contact_type, contact_id, placement, source, schema_version | Variabile di livello dati (versione 2) | `lu3g.<nome>` |
| LU3G - lu3g_user_data | Variabile di livello dati | `lu3g_user_data` |
| LU3G - user_data (hash) | JavaScript personalizzato | vedi sotto |
| LU3G - Dati forniti dall'utente | Dati forniti dall'utente → **Codice** | origine: `{{LU3G - user_data (hash)}}` |
| LU3G - page_location pulita | JavaScript personalizzato | `function(){ return location.origin + location.pathname; }` |
| LU3G - GA4 Measurement ID | Costante | **G-XXXXXXXXXX** |
| LU3G - Ads Conversion ID | Costante | **123456789** (senza AW-) |
| LU3G - Ads Conversion Label | Tabella di ricerca su `{{LU3G - conversion_key}}` | `lead` → **etichetta**, `contact_whatsapp` → **etichetta**, … |

```js
function () {
  var u = {{LU3G - lu3g_user_data}};
  if (!u || typeof u !== 'object') { return undefined; }
  var o = {};
  if (/^[0-9a-f]{64}$/.test(u.sha256_email_address)) { o.sha256_email_address = u.sha256_email_address; }
  if (/^[0-9a-f]{64}$/.test(u.sha256_phone_number)) { o.sha256_phone_number = u.sha256_phone_number; }
  return (o.sha256_email_address || o.sha256_phone_number) ? o : undefined;
}
```
Gli hash sono già calcolati dal plugin: la variabile non restituisce Promise e non va messa nei campi per i valori in
chiaro (email, phone_number).

## Attivatori (Evento personalizzato, con regex)
| Nome | Evento | Condizioni |
|---|---|---|
| LU3G - eventi form (produzione) | `^(lead_submit)$` + eventuali eventi alternativi (es. `newsletter_signup`) | `{{LU3G - test_mode}}` uguale a `false` |
| LU3G - contact_click (produzione) | `^(contact_click)$` | `{{LU3G - test_mode}}` uguale a `false` |
| LU3G - conversioni Google Ads (produzione) | tutti gli eventi LU3G | test_mode = `false` e `{{LU3G - conversion_key}}` corrisponde a `^(lead\|contact_whatsapp)$` (solo le chiavi con etichetta) |
| LU3G - tutti gli eventi (bridge diagnostico) | tutti gli eventi LU3G | nessuna |

Un evento alternativo va ad Ads solo se ha una sua chiave con etichetta: non attiva la conversione del lead principale.

## Tag
- **LU3G - GA4 - eventi form**: Evento GA4, nome `{{Event}}`, ID `{{LU3G - GA4 Measurement ID}}`. Parametri: event_id,
  form_name, form_key, value, currency, conversion_key, page_location = `{{LU3G - page_location pulita}}`. Nessun dato
  utente. Metadati aggiuntivi del tag: `lu3g_role` = `ga4`, nome del tag nella chiave `name`.
- **LU3G - GA4 - contact_click**: come sopra con event_id, contact_type, contact_id, placement, page_location.
- **LU3G - Google Ads - conversioni**: Monitoraggio delle conversioni di Google Ads.
  - ID `{{LU3G - Ads Conversion ID}}` ed etichetta `{{LU3G - Ads Conversion Label}}`.
  - Valore `{{LU3G - value}}`, valuta `{{LU3G - currency}}`, ID transazione `{{LU3G - event_id}}`.
  - "Includi i dati forniti dall'utente" con `{{LU3G - Dati forniti dall'utente}}`.
  - Metadati: `lu3g_role` = `ads`.
  - Gli identificatori vanno solo a questo tag. Non impostarli come `user_data` globale nel Google tag condiviso.
- **LU3G - Linker conversioni**: Tutte le pagine. Se il container ne ha già uno, non aggiungerlo.
- **LU3G - Bridge diagnostico**: modello personalizzato `lu3g-ct-bridge.tpl` (Modelli → Nuovo → menu ⋮ → Importa),
  attivatore "tutti gli eventi (bridge diagnostico)".

## Controlli prima di pubblicare
- Nessun altro tag deve contare gli stessi invii: Invio modulo automatico di GA4 (misurazione avanzata → interazioni con
  i moduli), vecchi script che ascoltano `submit_success`, tag `form_submit`. Uno di questi va tolto o filtrato.
- In Google Ads non usare come principali sia questa conversione sia la stessa conversione importata da GA4.
- Conversioni avanzate: in Google Ads, Obiettivi → Impostazioni → "Uso dei dati dei clienti" → attiva (dal 2026
  interruttore unico per web e lead). Nel Google tag: "Consenti le funzionalità dei dati forniti dagli utenti".
- Valori raccolti automaticamente dai tag (page_location, page_referrer, link_url, link_text dei clic in uscita della
  misurazione avanzata) possono contenere query string o indirizzi: tieni il parametro page_location pulito nei tag LU3G
  e valuta di disattivare in GA4 "Clic in uscita" se i link mailto/tel finiscono nei report con l'indirizzo.
- Gli eventi con `test_mode: true` (sessione di verifica, amministratori collegati, modalità di test) non attivano i tag
  di produzione. Per provare i tag reali usa un form di prova con la modalità di test spenta e filtra il traffico in GA4.
