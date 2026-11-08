<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Presentation\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Dev-time scaffold for a brand-new theme SOURCE folder — never executed on
 * a merchant's runtime, purely a local `artisan` tool. Copies from
 * resources/themes/default-storefront/ only what a theme still MUST supply
 * itself: manifest.json, layout.blade.php and the closed set of TOP-LEVEL
 * views Storefront controllers render by name (Contracts\ThemeRenderer) —
 * those have no fallback. theme::partials.* are left out by default because
 * they now fall back to the default theme's own copy when missing — see
 * "Moduł Themes" (docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013,
 * ustalenie 2026-09-17). --with-partials copies them too, as an editable
 * starting point for a theme that wants to customize everything up front.
 *
 * Output is a plain directory of source files, the same shape a third-party
 * theme author would hand-write — this command does not zip or install it;
 * that stays the existing upload flow through /panel/themes (or
 * Themes\Contracts\ThemeInstaller for automated seeding).
 */
final class MakeThemeCommand extends Command
{
    /** Same single-slug convention as Domain\ThemeManifestParser::ID_PATTERN. */
    private const ID_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /** Views Storefront controllers render by name — see modules/Storefront/Presentation/Controllers/*.php. */
    private const REQUIRED_VIEWS = [
        'catalog/index.blade.php',
        'catalog/show.blade.php',
        'cart/show.blade.php',
        'checkout/show.blade.php',
        'checkout/confirmation.blade.php',
        'account/login.blade.php',
        'account/register.blade.php',
        'account/orders/index.blade.php',
        'account/orders/show.blade.php',
    ];

    protected $signature = 'theme:make {id : Slug nowego motywu, np. "my-shop-theme"}
        {--name= : Nazwa w manifest.json (domyślnie wygenerowana z id)}
        {--path= : Katalog docelowy (domyślnie resources/themes/{id})}
        {--with-partials : Skopiuj też theme::partials.* jako punkt startowy (opcjonalne — mają fallback do domyślnego motywu)}';

    protected $description = 'Tworzy szkielet nowego motywu na bazie domyślnego motywu referencyjnego';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $id = (string) $this->argument('id');

        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            $this->components->error("Identyfikator \"{$id}\" musi być pojedynczym slugiem z małych liter, cyfr i myślników (np. \"my-shop-theme\").");

            return self::FAILURE;
        }

        $source = base_path('resources/themes/default-storefront');

        if (! $this->files->isDirectory($source)) {
            $this->components->error('Brak domyślnego motywu referencyjnego pod resources/themes/default-storefront — nie ma z czego skopiować szkieletu.');

            return self::FAILURE;
        }

        $destination = (string) ($this->option('path') ?: base_path("resources/themes/{$id}"));

        if ($this->files->exists($destination)) {
            $this->components->error("Katalog \"{$destination}\" już istnieje — wybierz inny --path albo usuń go ręcznie.");

            return self::FAILURE;
        }

        $this->files->ensureDirectoryExists("{$destination}/views");
        $this->writeManifest($source, $destination, $id);
        $this->files->copy("{$source}/views/layout.blade.php", "{$destination}/views/layout.blade.php");

        foreach (self::REQUIRED_VIEWS as $view) {
            $this->files->ensureDirectoryExists(dirname("{$destination}/views/{$view}"));
            $this->files->copy("{$source}/views/{$view}", "{$destination}/views/{$view}");
        }

        if ($this->files->isDirectory("{$source}/assets")) {
            $this->files->copyDirectory("{$source}/assets", "{$destination}/assets");
        }

        $copiedPartials = $this->option('with-partials') && $this->files->isDirectory("{$source}/views/partials");

        if ($copiedPartials) {
            $this->files->copyDirectory("{$source}/views/partials", "{$destination}/views/partials");
        }

        $this->components->info("Szkielet motywu \"{$id}\" utworzony w {$destination}");

        if ($copiedPartials) {
            $this->components->warn('Skopiowano też theme::partials.* — to edytowalny punkt startowy, nie wymóg: usunięty plik spadnie na wersję z domyślnego motywu.');
        } else {
            $this->components->warn('Partiale (theme::partials.*) NIE zostały skopiowane — brakujące spadną na wersję z domyślnego motywu. Użyj --with-partials, żeby dostać edytowalną kopię wszystkich.');
        }

        $this->line('Dalej: dostosuj manifest.json oraz widoki, spakuj katalog do ZIP-a i wgraj przez panel (/panel/themes) tak jak dowolną paczkę motywu.');

        return self::SUCCESS;
    }

    private function writeManifest(string $source, string $destination, string $id): void
    {
        $manifest = json_decode($this->files->get("{$source}/manifest.json"), true);

        $manifest['id'] = $id;
        $manifest['name'] = (string) ($this->option('name') ?: ucwords(str_replace('-', ' ', $id)));
        $manifest['version'] = '0.1.0';
        unset($manifest['dependencies'], $manifest['settings']);

        $this->files->put(
            "{$destination}/manifest.json",
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );
    }
}
