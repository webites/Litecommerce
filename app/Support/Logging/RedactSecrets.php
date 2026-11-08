<?php

declare(strict_types=1);

namespace App\Support\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

final class RedactSecrets
{
    private const SENSITIVE_KEY = '/pass(word|wd)?|secret|token|api[_-]?key|authorization|cookie|credential|signature|cvv|card[_-]?number|private[_-]?key/i';

    private const MASK = '[redacted]';

    public function __invoke(Logger $logger): void
    {
        /** @var \Monolog\Logger $monolog */
        $monolog = $logger->getLogger();
        $monolog->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(
            message: $this->maskText($record->message),
            context: $this->scrub($record->context),
            extra: $this->scrub($record->extra),
        ));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY, $key) === 1) {
                $data[$key] = self::MASK;
            } elseif (is_array($value)) {
                $data[$key] = $this->scrub($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->maskText($value);
            }
        }

        return $data;
    }

    private function maskText(string $text): string
    {
        $text = preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]{8,}/i', '$1 '.self::MASK, $text) ?? $text;

        return preg_replace('/([?&](?:token|api[_-]?key|access_token|password|secret|signature)=)[^&\s]+/i', '$1'.self::MASK, $text) ?? $text;
    }
}
