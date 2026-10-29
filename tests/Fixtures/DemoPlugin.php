<?php

namespace Tests\Fixtures;

use App\Plugins\ActionResult;
use App\Plugins\BasePlugin;
use App\Plugins\PluginContext;
use App\Plugins\Support\PeopleImport;

class DemoPlugin extends BasePlugin
{
    /** @var list<array{event: string, data: array<string, mixed>}> */
    public static array $events = [];

    public function action(string $action, PluginContext $ctx, array $options): ActionResult
    {
        if ($action === 'tag') {
            $ctx->call('tag', ['id' => (string) $options['id']], ['tag' => 'intranet']);

            return ActionResult::ok('Markiert.');
        }

        $people = (array) $ctx->call('people')->json('value');
        $import = new PeopleImport($ctx->users());
        $rows = $import->plan(array_map(fn (array $p): array => [
            'name' => $p['displayName'], 'email' => $p['mail'], 'function' => $p['jobTitle'] ?? null,
        ], $people), 'Demo');

        if ($options['_apply'] === true) {
            $done = $import->apply($rows, $options['_selected']);

            return ActionResult::ok("{$done['created']} angelegt, {$done['updated']} geändert.");
        }

        return ActionResult::preview(count($rows).' Personen verglichen.', PeopleImport::columns(), $rows);
    }

    public function widget(string $widget, PluginContext $ctx): array
    {
        return ['items' => [['label' => 'Personen', 'value' => count((array) $ctx->call('people')->json('value'))]]];
    }

    public function onEvent(string $event, array $data, PluginContext $ctx): void
    {
        self::$events[] = ['event' => $event, 'data' => $data];
        $ctx->log('Ereignis erhalten: '.$event);
    }
}
