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
  "displayName": "LU3G CT Bridge",
  "categories": [
    "UTILITY"
  ],
  "brand": {
    "id": "brand_dummy",
    "displayName": "LU3G"
  },
  "description": "Bridge diagnostico di LU3G Conversion Tracker: dopo un evento lead_submit/contact_click restituisce alla pagina event_id, ID del container e stato dei tag (addEventCallback). Nessun dato personale.",
  "containerContexts": [
    "WEB"
  ]
}


___TEMPLATE_PARAMETERS___

[]


___SANDBOXED_JS_FOR_WEB_TEMPLATE___

// LU3G CT Bridge: quando tutti i tag di questo evento hanno finito (o è scaduto il timeout dell'evento),
// passa a window.lu3gCTBridge l'event_id del plugin, l'ID del container e, per ogni tag, id, stato, durata e i
// metadati "lu3g_role" e "name" (impostati nei tag GA4/Ads del container LU3G). La funzione esiste solo durante una
// sessione di verifica del plugin: altrimenti callInWindow non fa nulla.
const addEventCallback = require('addEventCallback');
const callInWindow = require('callInWindow');
const copyFromDataLayer = require('copyFromDataLayer');

const eventId = copyFromDataLayer('lu3g.event_id');

addEventCallback(function (containerId, eventData) {
  const out = [];
  const list = (eventData && eventData.tags) ? eventData.tags : [];
  for (let i = 0; i < list.length; i++) {
    const t = list[i];
    out.push({
      id: t.id,
      status: t.status,
      executionTime: t.executionTime,
      role: t.lu3g_role || '',
      name: t.name || ''
    });
  }
  callInWindow('lu3gCTBridge', { event_id: eventId, container_id: containerId, tags: out });
});

data.gtmOnSuccess();


___WEB_PERMISSIONS___

[
  {
    "instance": {
      "key": {
        "publicId": "read_event_metadata",
        "versionId": "1"
      },
      "param": []
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
                  {
                    "type": 1,
                    "string": "key"
                  },
                  {
                    "type": 1,
                    "string": "read"
                  },
                  {
                    "type": 1,
                    "string": "write"
                  },
                  {
                    "type": 1,
                    "string": "execute"
                  }
                ],
                "mapValue": [
                  {
                    "type": 1,
                    "string": "lu3gCTBridge"
                  },
                  {
                    "type": 8,
                    "boolean": true
                  },
                  {
                    "type": 8,
                    "boolean": false
                  },
                  {
                    "type": 8,
                    "boolean": true
                  }
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
          "value": {
            "type": 1,
            "string": "specific"
          }
        },
        {
          "key": "keyPatterns",
          "value": {
            "type": 2,
            "listItem": [
              {
                "type": 1,
                "string": "lu3g.event_id"
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
  }
]


___TESTS___

scenarios: []


___NOTES___

Creato da LU3G Conversion Tracker. Si attiva sugli eventi del plugin (lead_submit, contact_click ed eventi alternativi).
