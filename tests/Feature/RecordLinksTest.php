<?php

namespace Tests\Feature;

use App\Data\Contracts\UserRepository;
use App\Support\PersonLinks;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Direkt ins fremde System springen (#149): CMI-Dossier aus der Datenherkunft, ohne Daten aus CMI zu lesen.
 */
class RecordLinksTest extends TestCase
{
    private const ID = '0123456789abcdef0123456789abcdef';

    public function test_cmi_dossier_url_per_person_type(): void
    {
        $this->assertSame('https://sekbuelach.cmicloud.ch/#/a6918005296e46f8a31242fc7861f653/a6918005296e46f8a31242fc7861f653/'.self::ID, PersonLinks::recordUrl('cmi', self::ID, 'lehrperson'));
        $this->assertSame('https://sekbuelach.cmicloud.ch/#/360f3d3459a745e5b2f35918edb69434/360f3d3459a745e5b2f35918edb69434/'.self::ID, PersonLinks::recordUrl('cmi', self::ID, 'schueler'));
        $this->assertSame('In CMI öffnen', PersonLinks::recordLabel('cmi'));
    }

    public function test_only_valid_ids_and_https(): void
    {
        $this->assertNull(PersonLinks::recordUrl('cmi', '', 'lehrperson'));
        $this->assertNull(PersonLinks::recordUrl('cmi', '4711', 'lehrperson'));
        $this->assertNull(PersonLinks::recordUrl('cmi', self::ID.'/../x', 'lehrperson'));
        $this->assertNull(PersonLinks::recordUrl('escola', self::ID, 'lehrperson'), 'nur Systeme mit Vorlage');

        config(['intranet.record_links.cmi.url' => 'http://unsicher.example/#/{bereich}/{id}']);
        $this->assertNull(PersonLinks::recordUrl('cmi', self::ID, 'lehrperson'));
    }

    public function test_person_page_shows_link(): void
    {
        $person = collect(app(UserRepository::class)->all())->firstWhere('name', 'Daniel Probe');
        app(PersonLinks::class)->save(['person_id' => $person['id'], 'direction' => 'source', 'system' => 'cmi', 'external_id' => self::ID]);

        $this->signInAs('admin');
        $this->get('/personen/'.$person['id'])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('links', fn ($links): bool => collect($links)->contains(fn (array $l): bool => $l['system'] === 'cmi'
                && str_ends_with((string) $l['open_url'], '/'.self::ID) && $l['open_label'] === 'In CMI öffnen')));
    }
}
