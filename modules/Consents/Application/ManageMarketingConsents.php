<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Consents\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Litecommerce\Modules\Consents\Contracts\ConsentSource;
use Litecommerce\Modules\Consents\Contracts\Events\MarketingConsentGranted;
use Litecommerce\Modules\Consents\Contracts\Events\MarketingConsentWithdrawn;
use Litecommerce\Modules\Consents\Contracts\MarketingConsentData;
use Litecommerce\Modules\Consents\Contracts\MarketingConsentRegistry;
use Litecommerce\Modules\Consents\Infrastructure\Models\MarketingConsent;
use Litecommerce\Modules\Consents\Infrastructure\Models\MarketingConsentEvent;

final class ManageMarketingConsents implements MarketingConsentRegistry
{
    public function __construct(private readonly ConsentLocaleResolver $locales) {}

    public function grant(string $email, ConsentSource $source, ?string $locale = null): bool
    {
        $email = $this->normalize($email);
        $locale = $this->locales->resolve($locale);

        return DB::transaction(function () use ($email, $source, $locale): bool {
            $consent = $this->lockedByEmail($email) ?? $this->createLocked($email, $locale);

            if ($consent->status === MarketingConsent::GRANTED) {
                return false;
            }

            $now = now();
            $consent->forceFill(['status' => MarketingConsent::GRANTED, 'locale' => $locale, 'granted_at' => $now, 'withdrawn_at' => null])->save();

            $statement = (string) trans('consents::marketing.statement', [], $locale);
            $this->record($consent, 'granted', $source, $locale, $statement);

            $event = new MarketingConsentGranted($email, $source, $now->toDateTimeImmutable());
            DB::afterCommit(static fn () => CommittedEvent::dispatch($event));

            return true;
        });
    }

    public function withdraw(string $email, ConsentSource $source): bool
    {
        $email = $this->normalize($email);

        return DB::transaction(function () use ($email, $source): bool {
            $consent = $this->lockedByEmail($email);

            return $consent !== null && $this->withdrawLocked($consent, $source);
        });
    }

    public function withdrawByToken(string $token): bool
    {
        return DB::transaction(function () use ($token): bool {
            $consent = MarketingConsent::query()->where('unsubscribe_token', $token)->lockForUpdate()->first();

            return $consent !== null && $this->withdrawLocked($consent, ConsentSource::UnsubscribeLink);
        });
    }

    public function isGranted(string $email): bool
    {
        return MarketingConsent::query()->where('email', $this->normalize($email))->where('status', MarketingConsent::GRANTED)->exists();
    }

    public function grantedAfter(int $afterId, int $limit): array
    {
        return MarketingConsent::query()
            ->where('status', MarketingConsent::GRANTED)->where('id', '>', $afterId)
            ->orderBy('id')->limit(max(1, $limit))->get()
            ->map(static fn (MarketingConsent $consent): MarketingConsentData => new MarketingConsentData(
                $consent->id, $consent->email, $consent->locale, $consent->granted_at->toDateTimeImmutable(),
            ))->all();
    }

    public function unsubscribeUrl(string $email): ?string
    {
        $consent = MarketingConsent::query()->where('email', $this->normalize($email))->where('status', MarketingConsent::GRANTED)->first();

        return $consent === null ? null : route('storefront.marketing.unsubscribe', ['locale' => $consent->locale, 'token' => $consent->unsubscribe_token]);
    }

    private function withdrawLocked(MarketingConsent $consent, ConsentSource $source): bool
    {
        if ($consent->status === MarketingConsent::WITHDRAWN) {
            return false;
        }

        $now = now();
        $consent->forceFill(['status' => MarketingConsent::WITHDRAWN, 'withdrawn_at' => $now])->save();
        $this->record($consent, 'withdrawn', $source, $consent->locale, null);

        $event = new MarketingConsentWithdrawn($consent->email, $source, $now->toDateTimeImmutable());
        DB::afterCommit(static fn () => CommittedEvent::dispatch($event));

        return true;
    }

    private function record(MarketingConsent $consent, string $action, ConsentSource $source, string $locale, ?string $statement): void
    {
        MarketingConsentEvent::query()->create([
            'marketing_consent_id' => $consent->id,
            'action' => $action,
            'source' => $source->value,
            'locale' => $locale,
            'content_version' => $statement === null ? null : substr(hash('sha256', $statement), 0, 12),
            'content_text' => $statement,
            'occurred_at' => now(),
        ]);
    }

    private function lockedByEmail(string $email): ?MarketingConsent
    {
        return MarketingConsent::query()->where('email', $email)->lockForUpdate()->first();
    }

    private function createLocked(string $email, string $locale): MarketingConsent
    {
        try {
            return DB::transaction(static fn (): MarketingConsent => MarketingConsent::query()->create([
                'email' => $email,
                'status' => MarketingConsent::WITHDRAWN,
                'unsubscribe_token' => Str::random(40),
                'locale' => $locale,
            ]));
        } catch (UniqueConstraintViolationException) {
            return MarketingConsent::query()->where('email', $email)->lockForUpdate()->firstOrFail();
        }
    }

    private function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }
}
