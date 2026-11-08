<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Litecommerce\Modules\DemoData\Contracts\DemoDataBatchData;
use Litecommerce\Modules\DemoData\Contracts\DemoDataBatchStatus;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContext;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContributor;
use Litecommerce\Modules\DemoData\Contracts\DemoDataManager;
use Litecommerce\Modules\DemoData\Contracts\DemoDataResourceRegistry;
use Litecommerce\Modules\DemoData\Infrastructure\Models\DemoDataBatch;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;

final class ManageDemoData implements DemoDataManager
{
    /** @var list<DemoDataContributor> */
    private array $contributors;

    /** @param iterable<DemoDataContributor> $contributors */
    public function __construct(
        private readonly LanguageRepository $languages,
        private readonly CurrencyRepository $currencies,
        private readonly DemoDataResourceRegistry $resources,
        iterable $contributors,
    ) {
        $this->contributors = [...$contributors];
        usort($this->contributors, static fn (DemoDataContributor $a, DemoDataContributor $b): int => $a->priority() <=> $b->priority());
    }

    public function install(?string $locale = null): DemoDataBatchData
    {
        $language = $locale === null
            ? $this->languages->default()
            : collect($this->languages->all())->first(static fn ($candidate): bool => $candidate->code === $locale && $candidate->isEnabled);
        $currency = $this->currencies->base();

        if ($language === null || ! $language->isEnabled) {
            throw new \RuntimeException('Przed dodaniem danych demo skonfiguruj aktywny język sklepu.');
        }

        if ($currency === null) {
            throw new \RuntimeException('Przed dodaniem danych demo skonfiguruj walutę bazową sklepu.');
        }

        if (DemoDataBatch::query()->whereNotNull('active_key')->exists()) {
            throw new \RuntimeException('Sklep ma już aktywny batch danych demonstracyjnych. Usuń go przed ponownym seedowaniem.');
        }

        $batch = DemoDataBatch::query()->create([
            'id' => (string) Str::ulid(),
            'status' => DemoDataBatchStatus::Creating->value,
            'active_key' => 'store',
            'locale' => $language->code,
            'currency_code' => $currency->code,
        ]);

        try {
            DB::transaction(function () use ($batch): void {
                $context = new DemoDataContext((string) $batch->getKey(), $batch->locale, $batch->currency_code);

                foreach ($this->contributors as $contributor) {
                    $contributor->install($context, $this->resources);
                }

                $batch->update([
                    'status' => DemoDataBatchStatus::Ready->value,
                    'completed_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            $batch->refresh()->update([
                'status' => DemoDataBatchStatus::Failed->value,
                'active_key' => null,
                'error_message' => Str::limit($exception->getMessage(), 2000, ''),
            ]);

            throw new \RuntimeException('Nie udało się dodać danych demonstracyjnych: '.$exception->getMessage(), 0, $exception);
        }

        return $this->toData($batch->refresh());
    }

    public function remove(?string $batchId = null): DemoDataBatchData
    {
        $batch = $batchId === null
            ? DemoDataBatch::query()->whereNotNull('active_key')->latest('created_at')->first()
            : DemoDataBatch::query()->find($batchId);

        if ($batch === null) {
            throw new \RuntimeException('Nie znaleziono aktywnego batcha danych demonstracyjnych.');
        }

        if (! in_array($batch->status, [DemoDataBatchStatus::Ready->value, DemoDataBatchStatus::RemovalFailed->value], true)) {
            throw new \RuntimeException('Ten batch nie jest gotowy do usunięcia.');
        }

        $batch->update(['status' => DemoDataBatchStatus::Removing->value, 'error_message' => null]);

        try {
            DB::transaction(function () use ($batch): void {
                foreach (array_reverse($this->contributors) as $contributor) {
                    $contributor->remove((string) $batch->getKey(), $this->resources);
                }

                $batch->update([
                    'status' => DemoDataBatchStatus::Removed->value,
                    'active_key' => null,
                    'removed_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            $batch->refresh()->update([
                'status' => DemoDataBatchStatus::RemovalFailed->value,
                'active_key' => 'store',
                'error_message' => Str::limit($exception->getMessage(), 2000, ''),
            ]);

            throw new \RuntimeException('Nie udało się usunąć danych demonstracyjnych: '.$exception->getMessage(), 0, $exception);
        }

        return $this->toData($batch->refresh());
    }

    public function active(): ?DemoDataBatchData
    {
        $batch = DemoDataBatch::query()
            ->whereNotNull('active_key')
            ->withCount('resources')
            ->latest('created_at')
            ->first();

        return $batch === null ? null : $this->toData($batch);
    }

    public function all(): array
    {
        return DemoDataBatch::query()->withCount('resources')->latest('created_at')->get()
            ->map(fn (DemoDataBatch $batch): DemoDataBatchData => $this->toData($batch))
            ->all();
    }

    private function toData(DemoDataBatch $batch): DemoDataBatchData
    {
        return new DemoDataBatchData(
            id: (string) $batch->getKey(),
            status: $batch->status,
            locale: $batch->locale,
            currencyCode: $batch->currency_code,
            resourceCount: isset($batch->resources_count) ? (int) $batch->resources_count : $batch->resources()->count(),
            errorMessage: $batch->error_message,
            completedAt: $batch->completed_at?->toIso8601String(),
            removedAt: $batch->removed_at?->toIso8601String(),
            createdAt: $batch->created_at->toIso8601String(),
        );
    }
}
