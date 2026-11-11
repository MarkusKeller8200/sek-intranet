<?php

namespace Modules\Zeiterfassung;

use App\Auth\IntranetUser;
use App\Modules\ModuleProvider;
use App\Support\PersonDocuments;
use App\Support\Signatures;
use Illuminate\Support\Facades\Gate;
use Modules\Zeiterfassung\Data\Contracts\EintragRepository;
use Modules\Zeiterfassung\Data\Contracts\MonatRepository;
use Modules\Zeiterfassung\Data\Json\JsonEintragRepository;
use Modules\Zeiterfassung\Data\Json\JsonMonatRepository;
use RuntimeException;

/**
 * Zeiterfassung: Repositories im eigenen Datenspeicher des Moduls (<data_path>/modules/zeiterfassung).
 */
class ModuleServiceProvider extends ModuleProvider
{
    protected string $module = 'zeiterfassung';

    public function register(): void
    {
        $this->app->singleton(EintragRepository::class, fn (): EintragRepository => match ($this->storeType()) {
            'json' => new JsonEintragRepository($this->store()),
            default => throw new RuntimeException('Zeiterfassung: Speicher «'.$this->storeType().'» ist noch nicht umgesetzt.'),
        });
        $this->app->singleton(Buchungsarten::class, fn (): Buchungsarten => new Buchungsarten($this->store()));
        $this->app->singleton(Sollzeiten::class, fn (): Sollzeiten => new Sollzeiten($this->store()));
        $this->app->singleton(Pruefregeln::class, fn (): Pruefregeln => new Pruefregeln($this->store()));
        $this->app->singleton(Vorgesetzte::class, fn (): Vorgesetzte => new Vorgesetzte($this->store()));
        $this->app->singleton(MonatRepository::class, fn (): MonatRepository => match ($this->storeType()) {
            'json' => new JsonMonatRepository($this->store()),
            default => throw new RuntimeException('Zeiterfassung: Speicher «'.$this->storeType().'» ist noch nicht umgesetzt.'),
        });
    }

    public function boot(): void
    {
        // Unterschriften (#43): unterschriebene Monatsrapporte ablegen, Status nachführen
        Signatures::listen('zeiterfassung', fn (string $ereignis, array $auftrag, ?string $pdf): ?bool => $this->app->make(Unterschriften::class)->ereignis($ereignis, $auftrag, $pdf));
        // Dokumente bei der Person (#208): unterschriebene Monatsrapporte für die Person selbst, das Visum und die
        // vorgesetzte Person, die mitunterschrieben hat
        PersonDocuments::register('zeiterfassung', fn (string $person, ?IntranetUser $viewer): array => self::dokumente($this->app->make(MonatRepository::class), $person, $viewer));
    }

    /**
     * @return list<array{title: string, date: string|null, url: string, kind: string}>
     */
    public static function dokumente(MonatRepository $monate, string $person, ?IntranetUser $viewer): array
    {
        if ($viewer === null) {
            return [];
        }
        $ich = (string) $viewer->getAuthIdentifier();
        $visum = Gate::forUser($viewer)->allows('visum-zeiterfassung');
        $result = [];
        foreach ($monate->all() as $m) {
            if (($m['person_id'] ?? null) !== $person || ! is_string($m['unterschrift_pdf'] ?? null)) {
                continue;
            }
            if ($ich !== $person && ! $visum && ($m['unterschrift_vorgesetzte'] ?? null) !== $ich) {
                continue;
            }
            $monat = (string) $m['monat'];
            $result[] = [
                'title' => 'Monatsrapport '.MonatsblattPdf::monatName($monat).' (unterschrieben)',
                'date' => is_string($m['unterschrift_at'] ?? null) ? $m['unterschrift_at'] : null,
                'url' => "/zeiterfassung/unterschrift/{$person}/{$monat}",
                'kind' => 'Zeiterfassung',
            ];
        }

        return $result;
    }
}
