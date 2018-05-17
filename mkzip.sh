#!/bin/bash
rm -f plg_system_eprivacy.zip
zip -q -9 -x "/.gitignore" -x "/.git/*" -x "/.idea/*" -x "/mkzip.sh" -x "/.mkzip.sh.swp" -x "/updates/*" -r plg_system_eprivacy.zip .

