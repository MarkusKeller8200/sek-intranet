#!/usr/bin/env bash
# Zeigt das Ende einer Ausgabe als Fehler-Hinweis (Annotation) im GitHub-Lauf an.
# So ist die Ursache in der Übersicht sichtbar, ohne das ganze Protokoll zu öffnen.
# Aufruf: report-failure.sh <datei> <titel> [zeilen]
set -u

file="$1"
title="$2"
lines="${3:-80}"

[ -s "$file" ] || { echo "::error title=$title::Keine Ausgabe vorhanden."; exit 0; }

# Farbcodes entfernen; leere Zeilen nur ausserhalb von Diffs weglassen
if head -n 1 "$file" | grep -q '^diff --git'; then
    text=$(sed -E 's/\x1B\[[0-9;?]*[A-Za-z]//g' "$file" | head -n "$lines")
else
    text=$(sed -E 's/\x1B\[[0-9;?]*[A-Za-z]//g' "$file" | grep -v '^[[:space:]]*$' | tail -n "$lines")
fi
text=${text//'%'/'%25'}
text=${text//$'\r'/}
text=${text//$'\n'/'%0A'}

echo "::error title=$title::$text"
