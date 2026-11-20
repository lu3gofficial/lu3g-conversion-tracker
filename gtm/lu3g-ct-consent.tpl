___TERMS_OF_SERVICE___

By creating or modifying this file you agree to Google Tag Manager's Community
Template Gallery Developer Terms of Service available at
https://developers.google.com/tag-manager/gallery-tos (or such other URL as
Google may provide), as modified from time to time.


___INFO___

{
  "type": "TAG",
  "id": "cvt_temp_public_id",
  "version": 1,
  "securityGroups": [],
  "displayName": "LU3G CT Consent",
  "categories": [
    "UTILITY"
  ],
  "brand": {
    "id": "brand_dummy",
    "displayName": "LU3G"
  },
  "description": "Ponte del consenso di LU3G Conversion Tracker: quando il CMP che lavora dentro GTM (es. il modello Iubenda) ha impostato o aggiornato il consenso, scrive nella pagina lo stato di analytics_storage, ad_storage, ad_user_data e ad_personalization. Nessun dato personale.",
  "containerContexts": [
    "WEB"
  ]
}


___TEMPLATE_PARAMETERS___

[]


___SANDBOXED_JS_FOR_WEB_TEMPLATE___

// LU3G CT Consent: si attiva solo sugli eventi del CMP (es. iubenda_gtm_consent_event), cioè dopo che il CMP ha
// impostato il consenso dentro GTM. Così uno stato "non impostato" (che isConsentGranted considera concesso) non viene
// mai scambiato per un consenso: il plugin usa questo stato solo perché arriva da un evento del CMP.
const isConsentGranted = require('isConsentGranted');
const setInWindow = require('setInWindow');
const getTimestampMillis = require('getTimestampMillis');
const copyFromDataLayer = require('copyFromDataLayer');

const types = ['analytics_storage', 'ad_storage', 'ad_user_data', 'ad_personalization'];
const state = { source: 'gtm', event: copyFromDataLayer('event') || '', time: getTimestampMillis() };
for (let i = 0; i < types.length; i++) {
  state[types[i]] = isConsentGranted(types[i]) ? 'granted' : 'denied';
}
setInWindow('lu3gCTConsentGTM', state, true);

data.gtmOnSuccess();


___WEB_PERMISSIONS___

[
  {
    "instance": {
      "key": {
        "publicId": "access_consent",
        "versionId": "1"
      },
      "param": [
        {
          "key": "consentTypes",
          "value": {
            "type": 2,
            "listItem": [
              {
                "type": 3,
                "mapKey": [
                  { "type": 1, "string": "consentType" },
                  { "type": 1, "string": "read" },
                  { "type": 1, "string": "write" }
                ],
                "mapValue": [
                  { "type": 1, "string": "analytics_storage" },
                  { "type": 8, "boolean": true },
                  { "type": 8, "boolean": false }
                ]
              },
              {
                "type": 3,
                "mapKey": [
                  { "type": 1, "string": "consentType" },
                  { "type": 1, "string": "read" },
                  { "type": 1, "string": "write" }
                ],
                "mapValue": [
                  { "type": 1, "string": "ad_storage" },
                  { "type": 8, "boolean": true },
                  { "type": 8, "boolean": false }
                ]
              },
              {
                "type": 3,
                "mapKey": [
                  { "type": 1, "string": "consentType" },
                  { "type": 1, "string": "read" },
                  { "type": 1, "string": "write" }
                ],
                "mapValue": [
                  { "type": 1, "string": "ad_user_data" },
                  { "type": 8, "boolean": true },
                  { "type": 8, "boolean": false }
                ]
              },
              {
                "type": 3,
                "mapKey": [
                  { "type": 1, "string": "consentType" },
                  { "type": 1, "string": "read" },
                  { "type": 1, "string": "write" }
                ],
                "mapValue": [
                  { "type": 1, "string": "ad_personalization" },
                  { "type": 8, "boolean": true },
                  { "type": 8, "boolean": false }
                ]
              }
            ]
          }
        }
      ]
    },
    "clientAnnotations": {
      "isEditedByUser": true
    },
    "isRequired": true
  },
  {
    "instance": {
      "key": {
        "publicId": "access_globals",
        "versionId": "1"
      },
      "param": [
        {
          "key": "keys",
          "value": {
            "type": 2,
            "listItem": [
              {
                "type": 3,
                "mapKey": [
                  { "type": 1, "string": "key" },
                  { "type": 1, "string": "read" },
                  { "type": 1, "string": "write" },
                  { "type": 1, "string": "execute" }
                ],
                "mapValue": [
                  { "type": 1, "string": "lu3gCTConsentGTM" },
                  { "type": 8, "boolean": true },
                  { "type": 8, "boolean": true },
                  { "type": 8, "boolean": false }
                ]
              }
            ]
          }
        }
      ]
    },
    "clientAnnotations": {
      "isEditedByUser": true
    },
    "isRequired": true
  },
  {
    "instance": {
      "key": {
        "publicId": "read_data_layer",
        "versionId": "1"
      },
      "param": [
        {
          "key": "allowedKeys",
          "value": { "type": 1, "string": "specific" }
        },
        {
          "key": "keyPatterns",
          "value": {
            "type": 2,
            "listItem": [
              { "type": 1, "string": "event" }
            ]
          }
        }
      ]
    },
    "clientAnnotations": {
      "isEditedByUser": true
    },
    "isRequired": true
  }
]


___TESTS___

scenarios: []


___NOTES___

Creato da LU3G Conversion Tracker 1.1.0. Attivatore consigliato: eventi del CMP (Iubenda: regex ^iubenda_).
