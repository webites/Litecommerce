<?php

declare(strict_types=1);

namespace Tests\Feature;

use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Audit\Infrastructure\Models\AuditEntry;
use Tests\TestCase;

final class AuditLoggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);
    }

    public function test_logger_records_action_and_redacts_nested_secrets(): void
    {
        app(AuditLogger::class)->record(new AuditEntryData(
            actorType: 'staff',
            actorId: '12',
            action: 'identity.staff.updated',
            subjectType: 'staff_account',
            subjectId: '18',
            changes: [
                'name' => ['old' => 'Jan', 'new' => 'Janina'],
                'password' => ['old' => 'secret-a', 'new' => 'secret-b'],
            ],
            metadata: ['request' => ['authorization' => 'Bearer secret']],
        ));

        $entry = AuditEntry::query()->sole();

        $this->assertSame('identity.staff.updated', $entry->action);
        $this->assertSame('[REDACTED]', $entry->changes['password']);
        $this->assertSame('[REDACTED]', $entry->metadata['request']['authorization']);
        $this->assertSame('Janina', $entry->changes['name']['new']);
    }
}
