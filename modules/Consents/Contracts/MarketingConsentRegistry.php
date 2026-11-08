<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Consents\Contracts;

/** Marketing consents keyed by e-mail address; the core stores them and never sends marketing messages itself. */
interface MarketingConsentRegistry
{
    /**
     * Returns false when the address already had an active consent (nothing is
     * recorded again). An unknown or disabled locale falls back to the store's
     * default language; the statement is recorded in the resolved language.
     */
    public function grant(string $email, ConsentSource $source, ?string $locale = null): bool;

    /** Returns false when there was no active consent to withdraw. */
    public function withdraw(string $email, ConsentSource $source): bool;

    /** Withdraws by the secret token from an unsubscribe link; returns false for an unknown token or an already withdrawn consent. */
    public function withdrawByToken(string $token): bool;

    public function isGranted(string $email): bool;

    /**
     * Cursor read for senders: active consents with id greater than $afterId,
     * ascending, so a sender pages through the list without loading it whole.
     *
     * @return list<MarketingConsentData>
     */
    public function grantedAfter(int $afterId, int $limit): array;

    /** Link for a message footer and the List-Unsubscribe header; null when the address has no active consent. */
    public function unsubscribeUrl(string $email): ?string;
}
