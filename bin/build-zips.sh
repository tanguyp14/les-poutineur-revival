#!/bin/bash
# Génère dist/poutine-builder.zip et dist/poutineurs.zip (installables via l'admin WordPress).
set -euo pipefail
cd "$(dirname "$0")/.."
rm -rf dist && mkdir dist
(cd wp-content/plugins && zip -qr ../../dist/poutine-builder.zip poutine-builder -x '*.DS_Store')
(cd wp-content/themes && zip -qr ../../dist/poutineurs.zip poutineurs -x '*.DS_Store')
ls -l dist
