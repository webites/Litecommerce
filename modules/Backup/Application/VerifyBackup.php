<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Application;

use Litecommerce\Modules\Backup\Contracts\BackupVerification;
use Litecommerce\Modules\Backup\Contracts\BackupVerifier;

final class VerifyBackup implements BackupVerifier
{
    public function verify(string $archivePath): BackupVerification
    {
        $zip = new \ZipArchive;
        if (! is_file($archivePath) || $zip->open($archivePath, \ZipArchive::RDONLY) !== true) {
            return new BackupVerification(false, ['The file is missing or is not a readable ZIP archive.'], []);
        }

        $problems = [];
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (! is_array($manifest) || ($manifest['format_version'] ?? null) !== CreateBackup::FORMAT_VERSION) {
            $zip->close();

            return new BackupVerification(false, ['manifest.json is missing, unreadable or has an unsupported format.'], []);
        }

        $fileEntries = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if (str_starts_with($stat['name'], 'files/')) {
                $fileEntries++;
            }
            if ($stat['name'] !== 'manifest.json' && ! $this->crcMatches($zip, $index, $stat)) {
                $problems[] = "Entry '{$stat['name']}' is corrupted.";
            }
        }

        $database = $manifest['database'] ?? null;
        if (is_array($database) && $zip->locateName((string) $database['entry']) === false) {
            $problems[] = "The database dump '{$database['entry']}' is missing.";
        }
        if (in_array('database', $manifest['targets'] ?? [], true) && ! is_array($database)) {
            $problems[] = 'The manifest lists the database target but has no dump entry.';
        }
        if ((int) ($manifest['files']['count'] ?? -1) !== $fileEntries) {
            $problems[] = 'The number of archived files differs from the manifest.';
        }
        $zip->close();

        $sidecar = $archivePath.'.sha256';
        if (is_file($sidecar)) {
            $expected = strtok((string) file_get_contents($sidecar), ' ');
            if ($expected !== hash_file('sha256', $archivePath)) {
                $problems[] = 'The archive does not match its .sha256 file.';
            }
        }

        return new BackupVerification($problems === [], $problems, $manifest);
    }

    /** @param array<string, mixed> $stat */
    private function crcMatches(\ZipArchive $zip, int $index, array $stat): bool
    {
        $stream = $zip->getStreamIndex($index);
        if ($stream === false) {
            return false;
        }

        $context = hash_init('crc32b');
        while (! feof($stream)) {
            $chunk = fread($stream, 1048576);
            if ($chunk === false) {
                fclose($stream);

                return false;
            }
            hash_update($context, $chunk);
        }
        fclose($stream);

        return hash_final($context) === sprintf('%08x', $stat['crc']);
    }
}
