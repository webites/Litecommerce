<?php

declare(strict_types=1);

namespace Tests\Unit\Email;

use InvalidArgumentException;
use Litecommerce\Modules\Email\Contracts\EmailDefinition;
use Litecommerce\Modules\Email\Contracts\EmailTransport;
use Litecommerce\Modules\Email\Contracts\EmailTransportResult;
use Litecommerce\Modules\Email\Contracts\PreparedEmail;
use Litecommerce\Modules\Email\Infrastructure\InMemoryEmailDefinitionRegistry;
use Litecommerce\Modules\Email\Infrastructure\InMemoryEmailTransportRegistry;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    public function test_plugin_can_register_owned_definition_and_transport(): void
    {
        $definitions = new InMemoryEmailDefinitionRegistry;
        $definition = new EmailDefinition(
            'plugin.invoice.ready', 'Invoice ready', [], ['en' => ['subject' => 'Invoice', 'html' => '<p>Ready</p>']], [],
            owner: 'invoice-plugin',
        );
        $definitions->register($definition);

        $transport = new class implements EmailTransport
        {
            public function send(PreparedEmail $email): EmailTransportResult
            {
                return new EmailTransportResult;
            }
        };
        $transports = new InMemoryEmailTransportRegistry;
        $transports->register('plugin.invoice', $transport, 'invoice-plugin');

        $this->assertSame($definition, $definitions->find('plugin.invoice.ready'));
        $this->assertSame($transport, $transports->find('plugin.invoice'));
        $this->assertSame('invoice-plugin', $transports->owner('plugin.invoice'));
    }

    public function test_duplicate_definition_is_rejected(): void
    {
        $registry = new InMemoryEmailDefinitionRegistry;
        $definition = new EmailDefinition('plugin.example', 'Example', [], ['en' => ['subject' => 'Subject', 'html' => '<p>Body</p>']], []);
        $registry->register($definition);

        $this->expectException(InvalidArgumentException::class);
        $registry->register($definition);
    }
}
