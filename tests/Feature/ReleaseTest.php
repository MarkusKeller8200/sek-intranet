<?php

namespace Tests\Feature;

use App\Support\AppVersion;
use App\Support\Release;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Versionen und Releases (docs/KONZEPT-VERSIONIERUNG.md): Release-Skript, Prüfung, Developer Release,
 * vorausgefüllte GitHub-Issues.
 */
class ReleaseTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/release-'.uniqid());
        File::ensureDirectoryExists($this->dir.'/plugins/Demo');
        file_put_contents($this->dir.'/VERSION', "0.31.0\n");
        file_put_contents($this->dir.'/CHANGELOG.md', "# Changelog\n\n## [Unveröffentlicht]\n\n### Neu\n\n- Etwas Neues\n\n## [0.31.0] – 2026-11-01\n\n### Neu\n\n- Alt\n");
        file_put_contents($this->dir.'/plugins/Demo/plugin.json', '{"version": "1.0"}');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_semver_comparison_and_planned_version(): void
    {
        $this->assertTrue(Release::compare('0.32.0-beta.2', '0.32.0-beta.10') < 0);
        $this->assertTrue(Release::compare('0.32.0-rc.1', '0.32.0') < 0);
        $this->assertTrue(Release::compare('0.10.0', '0.9.1') > 0);
        $this->assertFalse(Release::isValid('0.32'));

        $features = "## [Unveröffentlicht]\n\n### Neu\n\n- x\n\n### Behoben\n\n- y\n\n## [0.31.0]";
        $fixes = "## [Unveröffentlicht]\n\n### Behoben\n\n- y\n\n## [0.31.0]";
        $this->assertSame('0.32.0', Release::planned('0.31.0', $features));
        $this->assertSame('0.31.1', Release::planned('0.31.0', $fixes));
        $this->assertSame('0.32.0', Release::planned('0.32.0-beta.1', $fixes));
    }

    public function test_prepare_moves_unreleased_into_a_new_version(): void
    {
        $release = new Release($this->dir);
        $release->prepare('0.32.0', '2026-11-02');

        $this->assertSame('0.32.0', $release->current());
        $changelog = (string) file_get_contents($this->dir.'/CHANGELOG.md');
        $this->assertStringContainsString("## [Unveröffentlicht]\n\n## [0.32.0] – 2026-11-02\n\n### Neu\n\n- Etwas Neues", $changelog);
        $this->assertSame("### Neu\n\n- Etwas Neues", Release::notes($changelog, '0.32.0'));
        $this->assertSame('', Release::unreleasedText($changelog));
        $this->assertSame([], $release->check());
    }

    public function test_prepare_rejects_invalid_or_empty_releases(): void
    {
        $release = new Release($this->dir);
        foreach (['0.31.0', '0.30.0', 'abc'] as $bad) {
            try {
                $release->prepare($bad, '2026-11-02');
                $this->fail("{$bad} hätte abgelehnt werden müssen");
            } catch (InvalidArgumentException) {
                $this->assertSame('0.31.0', $release->current());
            }
        }
        $release->prepare('0.32.0', '2026-11-02');
        $this->expectException(InvalidArgumentException::class);
        $release->prepare('0.33.0', '2026-11-03');
    }

    public function test_prerelease_keeps_the_changelog(): void
    {
        $release = new Release($this->dir);
        $before = file_get_contents($this->dir.'/CHANGELOG.md');
        $release->prepare('0.32.0-beta.1', '2026-11-02');

        $this->assertSame('0.32.0-beta.1', $release->current());
        $this->assertSame($before, file_get_contents($this->dir.'/CHANGELOG.md'));
    }

    public function test_check_reports_changed_plugin_without_version_bump(): void
    {
        $release = new Release($this->dir);

        $errors = $release->check(['Demo'], ['Demo' => '1.0']);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Plugin Demo', $errors[0]);
        $this->assertSame([], $release->check(['Demo'], ['Demo' => '0.9']));
        $this->assertSame([], $release->check([], ['Demo' => '1.0']));
    }

    public function test_repository_passes_the_release_check(): void
    {
        $this->assertSame([], (new Release(base_path()))->check());
    }

    public function test_developer_release_from_git_describe(): void
    {
        $this->assertNull(AppVersion::developerRelease('0.31.0', null));
        $this->assertNull(AppVersion::developerRelease('0.31.0', 'v0.31.0-0-gabc1234'));
        $this->assertNull(AppVersion::developerRelease('0.31.0', 'kaputt'));

        $dev = AppVersion::developerRelease('0.31.0', 'v0.31.0-5-gabc1234');
        $this->assertNotNull($dev);
        $this->assertSame(5, $dev['commits']);
        $this->assertSame('0.31.0', $dev['base']);
        $this->assertTrue(Release::compare($dev['planned'], '0.31.0') > 0);

        // VERSION schon erhöht, Tag fehlt noch (Release läuft): auch Developer Release
        $this->assertSame(0, AppVersion::developerRelease('0.32.0', 'v0.31.0-0-gabc1234')['commits'] ?? null);
    }

    public function test_versions_page_shows_developer_release_and_github_links_only_for_admins(): void
    {
        config(['intranet.build_describe' => 'v0.0.1-3-gabc1234']);

        $this->signInAs('lehrperson');
        $this->get('/versionen')->assertInertia(fn (Assert $page) => $page
            ->where('info.dev.commits', 3)
            ->where('github', null));

        $this->signInAs('admin');
        $this->get('/versionen')->assertInertia(fn (Assert $page) => $page
            ->where('github', 'MarkusKeller8200/sek-intranet'));

        config(['intranet.github_repo' => 'kein repo']);
        $this->get('/versionen')->assertInertia(fn (Assert $page) => $page->where('github', null));
    }

    public function test_gui_prototype_offers_github_only_to_developers(): void
    {
        $this->signInAs('schulleitung');
        $this->get('/gui-prototypen/button')->assertInertia(fn (Assert $page) => $page->where('github', null));

        $this->signInAs('admin');
        $this->get('/gui-prototypen/button')->assertInertia(fn (Assert $page) => $page->where('github', 'MarkusKeller8200/sek-intranet'));
    }
}
