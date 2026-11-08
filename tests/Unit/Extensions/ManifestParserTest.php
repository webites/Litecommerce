<?php

declare(strict_types=1);

namespace Tests\Unit\Extensions;

use Litecommerce\Modules\Extensions\Contracts\ExtensionInstallationException;
use Litecommerce\Modules\Extensions\Domain\ManifestParser;
use PHPUnit\Framework\TestCase;

final class ManifestParserTest extends TestCase
{
    private function validManifest(array $overrides = []): string
    {
        return json_encode(array_merge([
            'id' => 'acme-invoices',
            'name' => 'Acme Invoices',
            'version' => '1.0.0',
            'type' => 'plugin',
            'provider' => 'LitecommercePlugins\\AcmeInvoices\\AcmeInvoices',
            'autoload' => ['psr-4' => ['LitecommercePlugins\\AcmeInvoices\\' => 'src']],
        ], $overrides), JSON_THROW_ON_ERROR);
    }

    public function test_parses_a_minimal_valid_manifest(): void
    {
        $manifest = (new ManifestParser)->parse($this->validManifest());

        $this->assertSame('acme-invoices', $manifest->id);
        $this->assertSame('1.0.0', $manifest->version);
        $this->assertSame('*', $manifest->coreConstraint);
        $this->assertSame([], $manifest->dependencies);
    }

    public function test_rejects_invalid_json(): void
    {
        $this->expectException(ExtensionInstallationException::class);
        (new ManifestParser)->parse('{not json');
    }

    public function test_rejects_missing_required_field(): void
    {
        $data = json_decode($this->validManifest(), true);
        unset($data['provider']);

        $this->expectExceptionMessage('provider');
        (new ManifestParser)->parse(json_encode($data));
    }

    public function test_rejects_a_slash_in_the_id(): void
    {
        $this->expectException(ExtensionInstallationException::class);
        (new ManifestParser)->parse($this->validManifest(['id' => 'acme/invoices']));
    }

    public function test_rejects_theme_type(): void
    {
        $this->expectException(ExtensionInstallationException::class);
        (new ManifestParser)->parse($this->validManifest(['type' => 'theme']));
    }

    public function test_rejects_a_namespace_not_matching_the_id_convention(): void
    {
        $this->expectException(ExtensionInstallationException::class);
        (new ManifestParser)->parse($this->validManifest(['autoload' => ['psr-4' => ['SomethingElse\\' => 'src']]]));
    }

    public function test_required_namespace_prefix_is_studly_cased(): void
    {
        $this->assertSame('LitecommercePlugins\\AcmeInvoices\\', ManifestParser::requiredNamespacePrefix('acme-invoices'));
    }

    public function test_parses_dependencies_permissions_and_settings(): void
    {
        $manifest = (new ManifestParser)->parse($this->validManifest([
            'dependencies' => [['id' => 'other-plugin', 'version' => '^1.0']],
            'permissions' => [['key' => 'acme.invoices.manage', 'label' => 'Zarządzanie fakturami']],
            'settings' => [['key' => 'company_name', 'type' => 'string', 'label' => 'Nazwa firmy', 'default' => 'Acme']],
        ]));

        $this->assertSame([['id' => 'other-plugin', 'version' => '^1.0']], $manifest->dependencies);
        $this->assertSame('acme.invoices.manage', $manifest->permissions[0]['key']);
        $this->assertSame('Acme', $manifest->settings[0]['default']);
    }
}
