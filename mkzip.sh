#!/bin/bash
TARGET='de-DE.plg_system_eprivacy.zip'

rm -f "$TARGET"
zip -q -9 -x "/.gitignore" -x "/.git/*" -x "/.idea/*" -x "/mkzip.sh" -x "/.mkzip.sh.swp" -x "/updates/*" -r "$TARGET" .

