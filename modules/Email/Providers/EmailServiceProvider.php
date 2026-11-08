<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Providers;

use App\Support\Secrets\SecretReencryptor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Email\Application\EloquentEmailMessageCatalog;
use Litecommerce\Modules\Email\Application\EloquentEmailTransportObligationChecker;
use Litecommerce\Modules\Email\Application\EnsureDefaultEmailLayouts;
use Litecommerce\Modules\Email\Application\ProcessEmailQueue;
use Litecommerce\Modules\Email\Application\QueueTransactionalEmail;
use Litecommerce\Modules\Email\Application\SendTestEmail;
use Litecommerce\Modules\Email\Console\ProcessEmailQueueCommand;
use Litecommerce\Modules\Email\Console\PruneEmailAttachmentsCommand;
use Litecommerce\Modules\Email\Console\PruneEmailHistoryCommand;
use Litecommerce\Modules\Email\Contracts\EmailDefinition;
use Litecommerce\Modules\Email\Contracts\EmailDefinitionRegistry;
use Litecommerce\Modules\Email\Contracts\EmailMessageCatalog;
use Litecommerce\Modules\Email\Contracts\EmailSettings;
use Litecommerce\Modules\Email\Contracts\EmailTransportObligationChecker;
use Litecommerce\Modules\Email\Contracts\EmailTransportRegistry;
use Litecommerce\Modules\Email\Contracts\EmailVariableDefinition;
use Litecommerce\Modules\Email\Contracts\EmailVariableType;
use Litecommerce\Modules\Email\Contracts\TestEmailSender;
use Litecommerce\Modules\Email\Contracts\TransactionalEmailQueue;
use Litecommerce\Modules\Email\Infrastructure\EloquentEmailSettings;
use Litecommerce\Modules\Email\Infrastructure\EmailSecretReencryptor;
use Litecommerce\Modules\Email\Infrastructure\InMemoryEmailDefinitionRegistry;
use Litecommerce\Modules\Email\Infrastructure\InMemoryEmailTransportRegistry;
use Litecommerce\Modules\Email\Infrastructure\SmtpEmailTransport;
use Litecommerce\Modules\Email\Presentation\Controllers\EmailSettingsController;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\StaffPermissionChecker;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;
use Litecommerce\Modules\Store\Contracts\Events\LanguageProvisioned;
use Litecommerce\Modules\Store\Contracts\SettingsTabRegistry;

final class EmailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/email.php', 'email');
        $this->app->singleton(EmailDefinitionRegistry::class, InMemoryEmailDefinitionRegistry::class);
        $this->app->singleton(EmailTransportRegistry::class, InMemoryEmailTransportRegistry::class);
        $this->app->bind(EmailSettings::class, EloquentEmailSettings::class);
        $this->app->bind(EmailMessageCatalog::class, EloquentEmailMessageCatalog::class);
        $this->app->bind(EmailTransportObligationChecker::class, EloquentEmailTransportObligationChecker::class);
        $this->app->bind(TransactionalEmailQueue::class, QueueTransactionalEmail::class);
        $this->app->bind(TestEmailSender::class, SendTestEmail::class);
        $this->app->tag(EmailSecretReencryptor::class, SecretReencryptor::class);
        $this->commands([ProcessEmailQueueCommand::class, PruneEmailAttachmentsCommand::class, PruneEmailHistoryCommand::class]);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'email');
        $this->registerDefinitions($this->app->make(EmailDefinitionRegistry::class));
        $this->app->make(EmailTransportRegistry::class)->register('smtp', $this->app->make(SmtpEmailTransport::class));
        $this->registerScheduledTasks($this->app->make(ScheduledTaskRegistry::class));
        Event::listen(LanguageProvisioned::class, fn (LanguageProvisioned $event) => $this->app->make(EnsureDefaultEmailLayouts::class)->handle($event->code));

        $this->app->make(SettingsTabRegistry::class)->register(
            'email',
            'email::panel.tab',
            700,
            [$this->app->make(EmailSettingsController::class), 'settingsTab'],
            fn (): bool => auth()->id() !== null && $this->app->make(StaffPermissionChecker::class)->allows((int) auth()->id(), CorePermission::ManageEmail->value),
        );
    }

    private function registerScheduledTasks(ScheduledTaskRegistry $tasks): void
    {
        $tasks->register('core.email.process', 60, function (): void {
            $traffic = (string) config('scheduler.mode', 'traffic') === 'traffic';
            $this->app->make(ProcessEmailQueue::class)->process(
                (int) config($traffic ? 'email.traffic_batch' : 'email.cron_batch', $traffic ? 1 : 25),
                (int) config($traffic ? 'email.traffic_time_limit' : 'email.cron_time_limit', $traffic ? 2 : 50),
            );
        });
        $tasks->register('core.email.prune_attachments', 86400, static function (): void {
            Artisan::call('email:prune-attachments');
        });
        $tasks->register('core.email.prune_history', 86400, static function (): void {
            Artisan::call('email:prune-history');
        });
    }

    private function registerDefinitions(EmailDefinitionRegistry $registry): void
    {
        $text = static fn (string $key, bool $required = true): EmailVariableDefinition => new EmailVariableDefinition($key, EmailVariableType::Text, $key, $required);
        $url = static fn (string $key): EmailVariableDefinition => new EmailVariableDefinition($key, EmailVariableType::Url, $key);
        $list = static fn (string $key): EmailVariableDefinition => new EmailVariableDefinition($key, EmailVariableType::List, $key);
        $money = static fn (string $key): EmailVariableDefinition => new EmailVariableDefinition($key, EmailVariableType::Money, $key);

        $registry->register(new EmailDefinition('core.order_placed', 'email::panel.definitions.order_placed', [
            $text('store_name'), $text('order_id'), $money('total'), $text('currency'), $url('order_url'), $list('lines'),
        ], $this->defaults(
            'Zamówienie {{order_id}} zostało przyjęte',
            '<h1>Dziękujemy za zamówienie</h1><p>Zamówienie <strong>{{order_id}}</strong> na kwotę {{total}} {{currency}} zostało przyjęte.</p><ul>{{#lines}}<li>{{name}} × {{quantity}}</li>{{/lines}}</ul><p><a href="{{order_url}}">Zobacz zamówienie</a></p>',
            'Order {{order_id}} has been received',
            '<h1>Thank you for your order</h1><p>Order <strong>{{order_id}}</strong> for {{total}} {{currency}} has been received.</p><ul>{{#lines}}<li>{{name}} × {{quantity}}</li>{{/lines}}</ul><p><a href="{{order_url}}">View order</a></p>',
        ), ['store_name' => 'Litecommerce', 'order_id' => 'ORDER-1', 'total' => '99.00', 'currency' => 'PLN', 'order_url' => 'https://example.com/pl/orders/example', 'lines' => [['name' => 'Produkt', 'quantity' => '1']]]));

        $registry->register(new EmailDefinition('core.payment_confirmed', 'email::panel.definitions.payment_confirmed', [
            $text('store_name'), $text('order_id'), $money('total'), $text('currency'), $url('order_url'),
        ], $this->defaults(
            'Płatność za zamówienie {{order_id}} została potwierdzona',
            '<h1>Płatność potwierdzona</h1><p>Otrzymaliśmy {{total}} {{currency}} za zamówienie {{order_id}}.</p><p><a href="{{order_url}}">Zobacz zamówienie</a></p>',
            'Payment for order {{order_id}} confirmed',
            '<h1>Payment confirmed</h1><p>We received {{total}} {{currency}} for order {{order_id}}.</p><p><a href="{{order_url}}">View order</a></p>',
        ), ['store_name' => 'Litecommerce', 'order_id' => 'ORDER-1', 'total' => '99.00', 'currency' => 'PLN', 'order_url' => 'https://example.com/pl/orders/example']));

        $registry->register(new EmailDefinition('core.order_ready_for_pickup', 'email::panel.definitions.order_ready_for_pickup', [
            $text('store_name'), $text('order_id'), $text('pickup_name'), $text('pickup_address'), $text('pickup_instructions', false), $text('pickup_deadline', false), $url('order_url'),
        ], $this->defaults(
            'Zamówienie {{order_id}} jest gotowe do odbioru',
            '<h1>Zamówienie gotowe do odbioru</h1><p><strong>{{pickup_name}}</strong><br>{{pickup_address}}</p>{{#pickup_instructions}}<p>{{pickup_instructions}}</p>{{/pickup_instructions}}{{#pickup_deadline}}<p>Prosimy o odbiór do: <strong>{{pickup_deadline}}</strong></p>{{/pickup_deadline}}<p><a href="{{order_url}}">Zobacz zamówienie</a></p>',
            'Order {{order_id}} is ready for pickup',
            '<h1>Your order is ready for pickup</h1><p><strong>{{pickup_name}}</strong><br>{{pickup_address}}</p>{{#pickup_instructions}}<p>{{pickup_instructions}}</p>{{/pickup_instructions}}{{#pickup_deadline}}<p>Please collect it by: <strong>{{pickup_deadline}}</strong></p>{{/pickup_deadline}}<p><a href="{{order_url}}">View order</a></p>',
        ), ['store_name' => 'Litecommerce', 'order_id' => 'ORDER-1', 'pickup_name' => 'Sklep główny', 'pickup_address' => 'Testowa 1, 00-001 Warszawa', 'pickup_instructions' => 'Wejście od podwórza', 'pickup_deadline' => '12 listopada 2026 12:00', 'order_url' => 'https://example.com/pl/orders/example']));

        $registry->register(new EmailDefinition('core.order_pickup_reminder', 'email::panel.definitions.order_pickup_reminder', [
            $text('store_name'), $text('order_id'), $text('pickup_name'), $text('pickup_address'), $text('pickup_instructions', false), $text('pickup_deadline'), $text('lapses', false), $url('order_url'),
        ], $this->defaults(
            'Przypomnienie: zamówienie {{order_id}} czeka na odbiór',
            '<h1>Zamówienie czeka na odbiór</h1><p><strong>{{pickup_name}}</strong><br>{{pickup_address}}</p>{{#pickup_instructions}}<p>{{pickup_instructions}}</p>{{/pickup_instructions}}<p>Prosimy o odbiór do: <strong>{{pickup_deadline}}</strong></p>{{#lapses}}<p>Nieodebrane w terminie zamówienie zostanie anulowane.</p>{{/lapses}}<p><a href="{{order_url}}">Zobacz zamówienie</a></p>',
            'Reminder: order {{order_id}} is waiting for pickup',
            '<h1>Your order is waiting for pickup</h1><p><strong>{{pickup_name}}</strong><br>{{pickup_address}}</p>{{#pickup_instructions}}<p>{{pickup_instructions}}</p>{{/pickup_instructions}}<p>Please collect it by: <strong>{{pickup_deadline}}</strong></p>{{#lapses}}<p>If it is not collected in time, the order will be cancelled.</p>{{/lapses}}<p><a href="{{order_url}}">View order</a></p>',
        ), ['store_name' => 'Litecommerce', 'order_id' => 'ORDER-1', 'pickup_name' => 'Sklep główny', 'pickup_address' => 'Testowa 1, 00-001 Warszawa', 'pickup_instructions' => 'Wejście od podwórza', 'pickup_deadline' => '12 listopada 2026 12:00', 'lapses' => '1', 'order_url' => 'https://example.com/pl/orders/example']));

        $registry->register(new EmailDefinition('core.order_pickup_expired', 'email::panel.definitions.order_pickup_expired', [
            $text('store_name'), $text('order_id'), $text('pickup_deadline', false), $url('order_url'),
        ], $this->defaults(
            'Zamówienie {{order_id}} zostało anulowane',
            '<h1>Zamówienie anulowane</h1><p>Zamówienie nie zostało odebrane{{#pickup_deadline}} do {{pickup_deadline}}{{/pickup_deadline}}, dlatego je anulowaliśmy, a towar wrócił do sprzedaży.</p><p><a href="{{order_url}}">Zobacz zamówienie</a></p>',
            'Order {{order_id}} was cancelled',
            '<h1>Order cancelled</h1><p>The order was not collected{{#pickup_deadline}} by {{pickup_deadline}}{{/pickup_deadline}}, so we cancelled it and put the items back on sale.</p><p><a href="{{order_url}}">View order</a></p>',
        ), ['store_name' => 'Litecommerce', 'order_id' => 'ORDER-1', 'pickup_deadline' => '12 listopada 2026 12:00', 'order_url' => 'https://example.com/pl/orders/example']));

        $registry->register(new EmailDefinition('core.order_status_changed', 'email::panel.definitions.order_status_changed', [
            $text('store_name'), $text('order_id'), $text('old_status'), $text('new_status'), $text('reason', false), $url('order_url'),
        ], $this->defaults(
            'Nowy status zamówienia {{order_id}}',
            '<h1>Status zamówienia został zmieniony</h1><p>{{old_status}} → <strong>{{new_status}}</strong></p>{{#reason}}<p>{{reason}}</p>{{/reason}}<p><a href="{{order_url}}">Zobacz zamówienie</a></p>',
            'New status for order {{order_id}}',
            '<h1>Your order status changed</h1><p>{{old_status}} → <strong>{{new_status}}</strong></p>{{#reason}}<p>{{reason}}</p>{{/reason}}<p><a href="{{order_url}}">View order</a></p>',
        ), ['store_name' => 'Litecommerce', 'order_id' => 'ORDER-1', 'old_status' => 'Oczekuje', 'new_status' => 'W realizacji', 'reason' => 'Przykładowa informacja', 'order_url' => 'https://example.com/pl/orders/example']));

        $registry->register(new EmailDefinition('core.digital_access_granted', 'email::panel.definitions.digital_access_granted', [
            $text('store_name'), $text('order_id'), $list('downloads'),
        ], $this->defaults(
            'Pliki do zamówienia {{order_id}} są dostępne',
            '<h1>Twoje pliki są gotowe</h1><ul>{{#downloads}}<li><a href="{{url}}">{{filename}}</a></li>{{/downloads}}</ul>',
            'Files for order {{order_id}} are ready',
            '<h1>Your files are ready</h1><ul>{{#downloads}}<li><a href="{{url}}">{{filename}}</a></li>{{/downloads}}</ul>',
        ), ['store_name' => 'Litecommerce', 'order_id' => 'ORDER-1', 'downloads' => [['filename' => 'ebook.pdf', 'url' => 'https://example.com/downloads/example']]], true, true, 90));

        $registry->register(new EmailDefinition('core.return_decided', 'email::panel.definitions.return_decided', [
            $text('store_name'), $text('order_id'), $text('decision'), $text('details'), $url('order_url'),
        ], $this->defaults(
            'Decyzja dotycząca zwrotu dla zamówienia {{order_id}}',
            '<h1>Decyzja dotycząca zwrotu</h1><p>{{decision}}</p><p>{{details}}</p><p><a href="{{order_url}}">Zobacz zamówienie</a></p>',
            'Return decision for order {{order_id}}',
            '<h1>Return decision</h1><p>{{decision}}</p><p>{{details}}</p><p><a href="{{order_url}}">View order</a></p>',
        ), ['store_name' => 'Litecommerce', 'order_id' => 'ORDER-1', 'decision' => 'Zaakceptowano', 'details' => 'Przykładowa decyzja', 'order_url' => 'https://example.com/pl/orders/example']));

        $registry->register(new EmailDefinition('core.manual_customer_message', 'email::panel.definitions.manual_customer_message', [
            $text('store_name'), $text('order_id'), $text('message'), $url('order_url'),
        ], $this->defaults(
            'Wiadomość dotycząca zamówienia {{order_id}}',
            '<h1>Wiadomość od sklepu</h1><p>{{message}}</p><p><a href="{{order_url}}">Zobacz zamówienie</a></p>',
            'Message about order {{order_id}}',
            '<h1>Message from the store</h1><p>{{message}}</p><p><a href="{{order_url}}">View order</a></p>',
        ), ['store_name' => 'Litecommerce', 'order_id' => 'ORDER-1', 'message' => 'Przykładowa wiadomość', 'order_url' => 'https://example.com/pl/orders/example']));

        $registry->register(new EmailDefinition('core.customer_account_deletion_confirm', 'email::panel.definitions.customer_account_deletion_confirm', [$text('store_name'), $url('confirm_url')], $this->defaults(
            'Potwierdź usunięcie konta', '<h1>Potwierdź usunięcie konta</h1><p>Aby usunąć konto w sklepie {{store_name}}, kliknij poniższy link. Jeśli to nie Ty, zignoruj tę wiadomość.</p><p><a href="{{confirm_url}}">Potwierdź usunięcie konta</a></p>',
            'Confirm account deletion', '<h1>Confirm account deletion</h1><p>To delete your account at {{store_name}}, use the link below. If this was not you, ignore this message.</p><p><a href="{{confirm_url}}">Confirm account deletion</a></p>',
        ), ['store_name' => 'Litecommerce', 'confirm_url' => 'https://example.com/pl/account/delete/example'], true, true, 100));
        $registry->register(new EmailDefinition('core.customer_account_deleted', 'email::panel.definitions.customer_account_deleted', [$text('store_name')], $this->defaults(
            'Konto zostało usunięte', '<h1>Konto zostało usunięte</h1><p>Twoje konto w sklepie {{store_name}} zostało usunięte. Zamówienia pozostają w sklepie przez okres wymagany przepisami, a potem ich dane osobowe są anonimizowane.</p>',
            'Your account was deleted', '<h1>Your account was deleted</h1><p>Your account at {{store_name}} has been deleted. Orders stay in the store for the period required by law, after which their personal data is anonymized.</p>',
        ), ['store_name' => 'Litecommerce'], true, true, 100));

        foreach ([
            ['core.staff_password_reset', 'email::panel.definitions.staff_password_reset', 'Reset hasła do panelu', 'Panel password reset'],
            ['core.customer_password_reset', 'email::panel.definitions.customer_password_reset', 'Reset hasła klienta', 'Customer password reset'],
        ] as [$key, $label, $pl, $en]) {
            $registry->register(new EmailDefinition($key, $label, [$text('store_name'), $url('reset_url')], $this->defaults(
                $pl, '<h1>'.$pl.'</h1><p><a href="{{reset_url}}">Ustaw nowe hasło</a></p>',
                $en, '<h1>'.$en.'</h1><p><a href="{{reset_url}}">Set a new password</a></p>',
            ), ['store_name' => 'Litecommerce', 'reset_url' => 'https://example.com/reset/example'], true, true, 100));
        }
    }

    /** @return array<string, array{subject:string,html:string}> */
    private function defaults(string $plSubject, string $plHtml, string $enSubject, string $enHtml): array
    {
        return ['pl' => ['subject' => $plSubject, 'html' => $plHtml], 'en' => ['subject' => $enSubject, 'html' => $enHtml]];
    }
}
