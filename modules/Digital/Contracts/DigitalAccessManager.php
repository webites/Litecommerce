<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Contracts;

/**
 * Staff-triggered changes to an already-issued grant — "Uprawniony
 * pracownik może wydłużyć dostęp/zwiększyć limit, z audytem"
 * (docs/knowledge-base/06-fulfillment.md#dostęp-cyfrowy). Consumed by
 * Orders\Presentation\Controllers\OrderController; the caller is
 * responsible for the audit record (same convention as every other
 * panel action in this codebase — see AuditLogger usage in
 * Catalog\Presentation\Controllers\ProductController).
 */
interface DigitalAccessManager
{
    /**
     * Sets a new expiry, or clears it (null = never expires again). Does
     * not touch a revoked grant.
     */
    public function extend(int $grantId, ?\DateTimeImmutable $until): void;

    /** Sets a new download limit, or clears it (null = unlimited). */
    public function setDownloadLimit(int $grantId, ?int $maxDownloads): void;

    /**
     * Revokes future downloads; already-served files stay served —
     * "Pobranej kopii nie cofniemy." Idempotent when already revoked.
     */
    public function revoke(int $grantId, string $reason): void;

    /**
     * Revokes every grant for one purchased line — the hook a future
     * Returns module (P1-010) will call for "Zatwierdzenie zwrotu danej
     * pozycji odbiera kolejne pobrania."
     */
    public function revokeForReference(string $referenceType, string $referenceId, string $reason): void;
}
