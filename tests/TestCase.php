<?php

namespace Tests;

use App\Auth\IntranetUser;
use App\Data\Contracts\UserRepository;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    /**
     * Jeder Test startet mit frischen Demo-Daten in einem eigenen Testordner
     * (INTRANET_DATA_PATH in phpunit.xml), nie in den Entwicklungsdaten.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Backend-Tests unabhängig vom Frontend-Build (kein Vite-Manifest nötig).
        $this->withoutVite();

        $path = base_path((string) config('intranet.data_path'));
        File::ensureDirectoryExists($path);
        File::cleanDirectory($path);

        Artisan::call('intranet:seed', ['--force' => true]);
    }

    /**
     * Meldet die erste aktive Demo-Person mit der gewünschten Rolle an.
     */
    protected function signInAs(string $role): IntranetUser
    {
        $record = collect(app(UserRepository::class)->active())
            ->firstWhere('role', $role);

        $this->assertNotNull($record, "Keine Demo-Person mit Rolle {$role}");

        $user = new IntranetUser($record);
        $this->actingAs($user);

        return $user;
    }
}
