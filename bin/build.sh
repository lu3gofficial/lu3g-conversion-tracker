#!/bin/sh
# Crea dist/lu3g-conversion-tracker.zip (cartella lu3g-conversion-tracker/ all'interno), il pacchetto che WordPress installa e aggiorna.
# Esclude i file che servono solo allo sviluppo (git, script, automazioni).
set -e
cd "$(dirname "$0")/.."
rm -rf dist && mkdir -p dist/lu3g-conversion-tracker
cp -R lu3g-conversion-tracker.php readme.txt uninstall.php includes assets gtm dist/lu3g-conversion-tracker/
(cd dist && zip -rq lu3g-conversion-tracker.zip lu3g-conversion-tracker -x '*.DS_Store')
rm -rf dist/lu3g-conversion-tracker
echo "dist/lu3g-conversion-tracker.zip"
