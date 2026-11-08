<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Infrastructure;

/**
 * Writes values into the .env file. Path is constructor-injected (not a
 * hardcoded base_path('.env')) so happy-path tests can point it at a
 * temp file instead of touching the repo's real .env.
 */
final class EnvFileWriter
{
    public function __construct(private readonly string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @param  array{host:string,port:string,database:string,username:string,password:string}  $credentials
     */
    public function writeDatabaseCredentials(array $credentials): void
    {
        $this->set('DB_CONNECTION', 'mysql');
        $this->set('DB_HOST', $credentials['host']);
        $this->set('DB_PORT', $credentials['port']);
        $this->set('DB_DATABASE', $credentials['database']);
        $this->set('DB_USERNAME', $credentials['username']);
        $this->set('DB_PASSWORD', $credentials['password']);
    }

    /** Records where the shop lives (asset and e-mail links are built from it), names the application and switches off debug output. */
    public function writeApplicationSettings(string $url): void
    {
        if (! preg_match('/^APP_NAME=(?!Laravel$)\S/m', $this->contents())) {
            $this->set('APP_NAME', 'Litecommerce');
        }

        $this->set('APP_URL', rtrim($url, '/'));
        $this->set('APP_ENV', 'production');
        $this->set('APP_DEBUG', 'false');
    }

    private function contents(): string
    {
        return file_exists($this->path) ? (string) file_get_contents($this->path) : '';
    }

    private function set(string $key, string $value): void
    {
        if (! file_exists($this->path)) {
            return;
        }

        $contents = (string) file_get_contents($this->path);
        $escaped = $this->escape($value);
        $pattern = '/^'.preg_quote($key, '/').'=.*/m';

        $contents = preg_match($pattern, $contents) === 1
            ? (string) preg_replace($pattern, "{$key}={$escaped}", $contents)
            : rtrim($contents)."\n{$key}={$escaped}\n";

        file_put_contents($this->path, $contents);
    }

    private function escape(string $value): string
    {
        if ($value === '' || preg_match('/[\s#"]/', $value) === 1) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }
}
