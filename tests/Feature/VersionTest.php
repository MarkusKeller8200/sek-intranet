<?php

namespace Tests\Feature;

use App\Support\AppVersion;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VersionTest extends TestCase
{
    public function test_version_comes_from_version_file_and_is_semver(): void
    {
        $version = trim((string) file_get_contents(base_path('VERSION')));

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
        $this->assertSame($version, AppVersion::info()['version']);
    }

    public function test_every_page_shares_the_version(): void
    {
        $this->signInAs('mitarbeitende');

        $this->get('/start')->assertInertia(fn (Assert $page) => $page
            ->where('appVersion.version', AppVersion::info()['version'])
            ->has('appVersion.build')
            ->has('appVersion.prototype'));
    }

    public function test_versions_page_lists_the_changelog_with_the_current_version(): void
    {
        $this->signInAs('mitarbeitende');
        $current = AppVersion::info()['version'];

        $this->get('/versionen')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('versions')
            ->where('info.version', $current)
            ->where('changelog', fn ($releases) => collect($releases)->contains('version', $current)));
    }

    public function test_changelog_has_an_entry_for_the_current_version(): void
    {
        $versions = array_column(AppVersion::changelog(), 'version');

        $this->assertContains(AppVersion::info()['version'], $versions, 'CHANGELOG.md: Eintrag für die aktuelle Version fehlt');
    }
}
