<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Consents\Contracts\ConsentSource;
use Litecommerce\Modules\Consents\Contracts\MarketingConsentRegistry;
use Litecommerce\Modules\Email\Contracts\EmailEnvelope;
use Litecommerce\Modules\Email\Contracts\TransactionalEmailQueue;
use Litecommerce\Modules\Identity\Contracts\AccountDeletionRefusedException;
use Litecommerce\Modules\Identity\Contracts\CustomerAccountDeletion;
use Litecommerce\Modules\Identity\Contracts\CustomerPurchaseBlockChecker;
use Litecommerce\Modules\Identity\Contracts\Events\CustomerAccountDeleted;
use Litecommerce\Modules\Identity\Infrastructure\Models\CustomerAccount;
use Litecommerce\Modules\Identity\Infrastructure\Models\CustomerAccountDeletionRequest;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;

final class EloquentCustomerAccountDeletion implements CustomerAccountDeletion
{
    public function __construct(
        private readonly CustomerPurchaseBlockChecker $blocks,
        private readonly TransactionalEmailQueue $emails,
        private readonly AuditLogger $audit,
        private readonly LanguageRepository $languages,
        private readonly StoreSettingsReader $settings,
        private readonly MarketingConsentRegistry $consents,
    ) {}

    public function request(int $customerAccountId, string $password): void
    {
        $account = CustomerAccount::query()->findOrFail($customerAccountId);

        if (! Hash::check($password, $account->password)) {
            throw new AccountDeletionRefusedException(AccountDeletionRefusedException::INVALID_PASSWORD, trans('identity::account_deletion.invalid_password'));
        }

        $this->assertNotBlocked($customerAccountId);

        $token = Str::random(64);
        $hash = hash('sha256', $token);
        $locale = $this->locale();

        DB::transaction(function () use ($account, $hash, $token, $locale): void {
            CustomerAccountDeletionRequest::query()->where('customer_account_id', $account->getKey())->delete();
            CustomerAccountDeletionRequest::query()->create([
                'customer_account_id' => $account->getKey(),
                'token_hash' => $hash,
                'expires_at' => now()->addMinutes((int) config('identity.account_deletion_link_minutes', 60)),
            ]);

            $this->emails->enqueue(new EmailEnvelope(
                'core.customer_account_deletion_confirm',
                $account->email,
                null,
                $locale,
                [
                    'store_name' => $this->settings->current()->storeName,
                    'confirm_url' => route('storefront.account.delete.confirm', ['locale' => $locale, 'token' => $token]),
                ],
                'customer-account-deletion-confirm:'.$hash,
                'customer_account',
                (string) $account->getKey(),
            ));

            $this->record($account->getKey(), 'identity.customer_account.deletion_requested');
        });
    }

    public function confirm(string $token): int
    {
        $locale = $this->locale();

        return DB::transaction(function () use ($token, $locale): int {
            $request = CustomerAccountDeletionRequest::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();

            if ($request === null || $request->expires_at->isPast()) {
                throw new AccountDeletionRefusedException(AccountDeletionRefusedException::INVALID_TOKEN, trans('identity::account_deletion.invalid_link'));
            }

            $account = CustomerAccount::query()->lockForUpdate()->findOrFail($request->customer_account_id);
            $this->assertNotBlocked((int) $account->getKey());

            $accountId = (int) $account->getKey();
            $this->emails->enqueue(new EmailEnvelope(
                'core.customer_account_deleted',
                $account->email,
                null,
                $locale,
                ['store_name' => $this->settings->current()->storeName],
                'customer-account-deleted:'.$request->token_hash,
                'customer_account',
                (string) $accountId,
            ));
            $this->record($accountId, 'identity.customer_account.deleted');
            $this->consents->withdraw($account->email, ConsentSource::AccountDeletion);
            $account->delete();

            $event = new CustomerAccountDeleted($accountId, new \DateTimeImmutable);
            DB::afterCommit(static fn () => CommittedEvent::dispatch($event));

            return $accountId;
        });
    }

    private function assertNotBlocked(int $customerAccountId): void
    {
        if ($this->blocks->forCustomerAccount($customerAccountId)->blocked) {
            throw new AccountDeletionRefusedException(AccountDeletionRefusedException::PURCHASE_BLOCKED, trans('identity::account_deletion.purchase_blocked'));
        }
    }

    private function record(int|string $accountId, string $action): void
    {
        $this->audit->record(new AuditEntryData(
            actorType: 'customer',
            actorId: (string) $accountId,
            action: $action,
            subjectType: 'customer_account',
            subjectId: (string) $accountId,
        ));
    }

    private function locale(): string
    {
        $default = $this->languages->default();

        return $default === null ? (string) config('app.fallback_locale', 'en') : $default->code;
    }
}
