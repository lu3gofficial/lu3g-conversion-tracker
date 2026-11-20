=== LU3G Conversion Tracker ===
Contributors: lu3g
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later

Conversioni dei form di Elementor Pro e click su WhatsApp, telefono ed email nel dataLayer di Google Tag Manager, con conversioni avanzate di Google Ads nel rispetto del consenso.

== Description ==

Pubblica nel dataLayer lead_submit (invio riuscito dei form di Elementor Pro) e contact_click (WhatsApp, tel:, mailto:). Con il consenso ad_user_data aggiunge email e telefono normalizzati e cifrati SHA-256 per Google Ads. Include la configurazione GTM da importare e una diagnostica. Libreria inclusa: libphonenumber-js 1.13.15 (MIT).

== Changelog ==

= 1.2.1 =
* File per GTM: il pixel base Meta parte anche quando il consenso arriva dopo il caricamento della pagina.

= 1.2.0 =
* Canali personalizzati (Maps, Telegram, Messenger, download, pulsanti con classe CSS), pannello semplificato, tag Meta nel file per GTM.

= 1.1.0 =
* Ponte del consenso in GTM per i CMP che lavorano dentro GTM (es. Iubenda con il modello GTM).

= 1.0.0 =
* Prima versione.
