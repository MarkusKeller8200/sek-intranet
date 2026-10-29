<?php

// Deutsche Meldungen für die im Prototyp verwendeten Regeln (Schweizer Schreibweise).
// Bei Bedarf vollständige Übersetzung ergänzen (z. B. Paket laravel-lang/lang).

return [
    'after_or_equal' => ':attribute muss ein Datum nach oder gleich :date sein.',
    'boolean' => ':attribute muss Ja oder Nein sein.',
    'date' => ':attribute ist kein gültiges Datum.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'file' => ':attribute muss eine Datei sein.',
    'in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'max' => [
        'file' => ':attribute darf höchstens :max Kilobyte gross sein.',
        'string' => ':attribute darf höchstens :max Zeichen haben.',
        'numeric' => ':attribute darf höchstens :max sein.',
        'array' => ':attribute darf höchstens :max Elemente haben.',
    ],
    'required' => ':attribute muss ausgefüllt werden.',
    'required_if' => ':attribute muss ausgefüllt werden, wenn :other :value ist.',
    'string' => ':attribute muss ein Text sein.',
    'uploaded' => ':attribute konnte nicht hochgeladen werden.',
    'url' => ':attribute muss eine gültige URL sein (http oder https).',

    'attributes' => [
        'title' => 'Titel',
        'body' => 'Text',
        'category' => 'Kategorie',
        'audience' => 'Zielgruppe',
        'pinned' => 'Angeheftet',
        'published_at' => 'Veröffentlicht ab',
        'description' => 'Beschreibung',
        'school' => 'Schulhaus',
        'location' => 'Ort',
        'all_day' => 'Ganztägig',
        'starts_at' => 'Beginn',
        'ends_at' => 'Ende',
        'kind' => 'Art',
        'url' => 'Link',
        'file' => 'Datei',
        'name' => 'Name',
        'email' => 'E-Mail',
        'role' => 'Rolle',
        'function' => 'Funktion',
        'phone' => 'Telefon',
        'active' => 'Aktiv',
        'user_id' => 'Person',
    ],

    'values' => [
        'kind' => ['link' => 'Link', 'file' => 'Datei'],
    ],
];
