<?php

namespace App\Plugins;

/**
 * Ergebnis einer Plugin-Aktion. Die Oberfläche zeigt Meldung, optional eine
 * Tabelle und – bei Übernahmen – eine Vorschau mit Auswahl («Übernehmen»).
 */
final class ActionResult
{
    /**
     * @param  list<array{key: string, label: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    private function __construct(
        public readonly string $message,
        public readonly array $columns = [],
        public readonly array $rows = [],
        public readonly bool $preview = false,
        public readonly string $status = 'success',
    ) {}

    public static function ok(string $message): self
    {
        return new self($message);
    }

    public static function warning(string $message): self
    {
        return new self($message, status: 'warning');
    }

    /**
     * @param  list<array{key: string, label: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    public static function table(string $message, array $columns, array $rows): self
    {
        return new self($message, $columns, $rows);
    }

    /**
     * Vorschau einer Übernahme. Jede Zeile braucht «_key» (eindeutig) und «_change»
     * (neu, ändern, gleich, fehlt). Ausgewählte Schlüssel kommen beim zweiten Lauf
     * als Option «_selected» zurück, zusammen mit «_apply» = true.
     *
     * @param  list<array{key: string, label: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    public static function preview(string $message, array $columns, array $rows): self
    {
        return new self($message, $columns, $rows, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'status' => $this->status,
            'columns' => $this->columns,
            'rows' => $this->rows,
            'preview' => $this->preview,
        ];
    }
}
