<?php

namespace Tests\Unit;

use App\Data\Store\JsonStore;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class JsonStoreTest extends TestCase
{
    private string $dir;

    private JsonStore $store;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/intranet-store-'.bin2hex(random_bytes(4));
        $this->store = new JsonStore($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function test_insert_find_update_delete(): void
    {
        $this->assertSame([], $this->store->all('items'));

        $created = $this->store->insert('items', ['title' => 'Ä erster', 'flag' => true]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $created['id']);

        $updated = $this->store->update('items', $created['id'], ['title' => 'neu', 'created_at' => 'manipuliert']);
        $this->assertNotNull($updated);
        $this->assertSame('neu', $updated['title']);
        $this->assertTrue($updated['flag']);
        $this->assertSame($created['created_at'], $updated['created_at']);

        $this->assertNull($this->store->update('items', 'gibt-es-nicht', ['x' => 1]));
        $this->assertTrue($this->store->delete('items', $created['id']));
        $this->assertFalse($this->store->delete('items', $created['id']));
        $this->assertSame(0, $this->store->count('items'));
    }

    public function test_file_is_readable_json_with_unicode(): void
    {
        $this->store->insert('items', ['title' => 'Grüezi Bülach']);

        $contents = (string) file_get_contents($this->store->filePath('items'));
        $data = json_decode($contents, true);

        $this->assertStringContainsString('Grüezi Bülach', $contents);
        $this->assertSame(JsonStore::FORMAT_VERSION, $data['format_version']);
        $this->assertCount(1, $data['records']);
    }

    public function test_hand_written_plain_list_is_accepted(): void
    {
        mkdir($this->dir, 0775, true);
        file_put_contents($this->dir.'/items.json', '[{"id": "a", "title": "von Hand"}]');

        $this->assertSame('von Hand', $this->store->find('items', 'a')['title'] ?? null);
    }

    public function test_collection_names_are_guarded(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->store->all('../geheim');
    }

    public function test_uuid7_is_monotonic(): void
    {
        $previous = '';

        for ($i = 0; $i < 5000; $i++) {
            $id = JsonStore::uuid7();
            $this->assertGreaterThan($previous, $id);
            $previous = $id;
        }
    }
}
