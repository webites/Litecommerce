<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Application;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Litecommerce\Modules\Email\Contracts\EmailAttachmentSource;
use Litecommerce\Modules\Email\Contracts\EmailDefinition;
use Litecommerce\Modules\Email\Contracts\EmailDefinitionRegistry;
use Litecommerce\Modules\Email\Contracts\EmailEnvelope;
use Litecommerce\Modules\Email\Contracts\EmailSettings;
use Litecommerce\Modules\Email\Contracts\EmailTransportRegistry;
use Litecommerce\Modules\Email\Contracts\EmailVariableType;
use Litecommerce\Modules\Email\Contracts\TransactionalEmailQueue;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailLayout;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessage;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessageAttachment;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailTemplate;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Throwable;

final class QueueTransactionalEmail implements TransactionalEmailQueue
{
    private const DefaultLayout = '<div style="background-color: #f5f5f5; padding: 24px"><div style="background-color: #ffffff; max-width: 680px; margin: 0 auto; padding: 24px">{{content}}</div></div>';

    public function __construct(
        private readonly EmailDefinitionRegistry $definitions,
        private readonly EmailSettings $settings,
        private readonly LanguageRepository $languages,
        private readonly HtmlSanitizer $sanitizer,
        private readonly EmailTransportRegistry $transports,
    ) {}

    public function enqueue(EmailEnvelope $message): ?string
    {
        $existing = EmailMessage::query()->where('idempotency_key', $message->idempotencyKey)->value('id');
        if (is_string($existing)) {
            return $existing;
        }

        $definition = $this->definitions->find($message->definitionKey);
        if ($definition === null) {
            throw new InvalidArgumentException("Unknown email definition [{$message->definitionKey}].");
        }
        $this->validateEnvelope($message, $definition);

        [$locale, $template, $layout] = $this->template($definition, $message->locale);
        if (! $definition->mandatory && ! $template['enabled']) {
            return null;
        }

        $id = (string) Str::uuid();
        $paths = [];

        try {
            EmailMessage::query()->create([
                'id' => $id,
                'definition_key' => $definition->key,
                'source_type' => $message->sourceType,
                'source_id' => $message->sourceId,
                'actor_type' => $message->actorType,
                'actor_id' => $message->actorId,
                'idempotency_key' => $message->idempotencyKey,
                'mode' => $message->mode,
                'priority' => $definition->priority,
                'locale' => $locale,
                'to_email' => strtolower(trim($message->toEmail)),
                'to_name' => $message->toName,
                'recipient_hash' => hash('sha256', strtolower(trim($message->toEmail))),
                'variables' => $this->normalizedVariables($message, $definition),
                'template_subject' => $template['subject'],
                'template_html' => $this->sanitizer->sanitize($template['html']),
                'layout_html' => $this->sanitizer->sanitize($layout),
                'transport_key' => $transportKey = $message->transportKey ?? $this->settings->current()->transportKey,
                'transport_owner' => $this->transports->owner($transportKey),
                'status' => 'queued',
                'available_at' => now(),
            ]);

            foreach ($message->attachments as $source) {
                $paths[] = $this->copyAttachment($id, $source);
            }
        } catch (QueryException $exception) {
            $this->removePaths($paths);
            $existing = EmailMessage::query()->where('idempotency_key', $message->idempotencyKey)->value('id');
            if (is_string($existing)) {
                return $existing;
            }
            throw $exception;
        } catch (Throwable $exception) {
            $this->removePaths($paths);
            EmailMessage::query()->whereKey($id)->delete();
            throw $exception;
        }

        return $id;
    }

    /** @return array{0:string,1:array{subject:string,html:string,enabled:bool},2:string} */
    private function template(EmailDefinition $definition, string $requestedLocale): array
    {
        $defaultLanguage = $this->languages->default();
        $defaultLocale = $defaultLanguage === null ? (string) config('app.fallback_locale', 'en') : $defaultLanguage->code;
        $locale = array_key_exists($requestedLocale, $definition->defaults) ? $requestedLocale : $defaultLocale;
        if (! array_key_exists($locale, $definition->defaults)) {
            $locale = (string) array_key_first($definition->defaults);
        }
        $default = $definition->defaults[$locale];
        $override = EmailTemplate::query()->where('definition_key', $definition->key)->where('locale', $locale)->first();
        $layout = $override === null || $override->layout_id === null
            ? EmailLayout::query()->where('locale', $locale)->where('is_default', true)->value('html')
            : EmailLayout::query()->whereKey($override->layout_id)->value('html');
        $subject = $override === null ? $default['subject'] : ($override->subject ?: $default['subject']);
        $html = $override === null ? $default['html'] : ($override->html ?: $default['html']);
        $enabled = $override === null || $override->enabled === null ? $definition->defaultEnabled : $override->enabled;

        return [$locale, [
            'subject' => $subject,
            'html' => $html,
            'enabled' => $definition->mandatory ? true : $enabled,
        ], is_string($layout) ? $layout : self::DefaultLayout];
    }

    private function validateEnvelope(EmailEnvelope $message, EmailDefinition $definition): void
    {
        if (filter_var($message->toEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Email recipient is invalid.');
        }
        if (! in_array($message->mode, ['live', 'test'], true)) {
            throw new InvalidArgumentException('Email mode must be live or test.');
        }
        if (preg_match('/^[a-z]{2,10}$/', $message->locale) !== 1) {
            throw new InvalidArgumentException('Email locale is invalid.');
        }
        if ($message->idempotencyKey === '' || strlen($message->idempotencyKey) > 191) {
            throw new InvalidArgumentException('Email idempotency key is invalid.');
        }

        $known = [];
        foreach ($definition->variables as $variable) {
            $known[$variable->key] = true;
            if ($variable->required && ! array_key_exists($variable->key, $message->variables)) {
                throw new InvalidArgumentException("Required email variable [{$variable->key}] is missing.");
            }
            if (array_key_exists($variable->key, $message->variables)) {
                $this->validateValue($variable->key, $variable->type, $message->variables[$variable->key]);
            }
        }
        foreach (array_keys($message->variables) as $key) {
            if (! isset($known[$key])) {
                throw new InvalidArgumentException("Unknown email variable [{$key}].");
            }
        }
    }

    private function validateValue(string $key, EmailVariableType $type, mixed $value): void
    {
        $valid = match ($type) {
            EmailVariableType::Text => is_string($value),
            EmailVariableType::Url => is_string($value) && $this->safeUrl($value),
            EmailVariableType::Number => is_int($value) || is_string($value) && is_numeric($value),
            EmailVariableType::Money => is_string($value) && preg_match('/^-?\d+(?:\.\d+)?$/', $value) === 1,
            EmailVariableType::Date => is_string($value) && $this->validDate($value),
            EmailVariableType::Boolean => is_bool($value),
            EmailVariableType::List => is_array($value) && array_is_list($value),
        };
        if (! $valid) {
            throw new InvalidArgumentException("Email variable [{$key}] does not match type [{$type->value}].");
        }
    }

    private function safeUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    private function validDate(string $value): bool
    {
        try {
            new \DateTimeImmutable($value);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    private function copyAttachment(string $messageId, EmailAttachmentSource $source): string
    {
        $stream = $source->openStream();
        if (! is_resource($stream)) {
            throw new InvalidArgumentException('Email attachment source did not return a stream.');
        }
        $temporary = fopen('php://temp/maxmemory:5242880', 'w+b');
        if ($temporary === false) {
            fclose($stream);
            throw new \RuntimeException('Cannot allocate an attachment spool.');
        }

        $hash = hash_init('sha256');
        $size = 0;
        while (! feof($stream)) {
            $chunk = fread($stream, 8192);
            if ($chunk === false) {
                fclose($stream);
                fclose($temporary);
                throw new \RuntimeException('Cannot read an email attachment.');
            }
            $size += strlen($chunk);
            hash_update($hash, $chunk);
            fwrite($temporary, $chunk);
        }
        fclose($stream);
        if ($size !== $source->sizeBytes()) {
            fclose($temporary);
            throw new InvalidArgumentException('Email attachment size changed while it was being queued.');
        }

        rewind($temporary);
        $disk = (string) config('email.private_disk', 'local');
        $path = trim((string) config('email.attachment_directory', 'email-attachments'), '/')."/{$messageId}/".(string) Str::uuid();
        if (! Storage::disk($disk)->writeStream($path, $temporary)) {
            fclose($temporary);
            throw new \RuntimeException('Cannot persist an email attachment.');
        }
        fclose($temporary);

        EmailMessageAttachment::query()->create([
            'email_message_id' => $messageId,
            'disk' => $disk,
            'path' => $path,
            'filename' => $source->filename(),
            'mime_type' => $source->mimeType(),
            'size_bytes' => $size,
            'checksum_sha256' => hash_final($hash),
        ]);

        return $disk.':'.$path;
    }

    /** @return array<string, mixed> */
    private function normalizedVariables(EmailEnvelope $message, EmailDefinition $definition): array
    {
        $variables = $message->variables;
        foreach ($definition->variables as $variable) {
            if (! $variable->required && ! array_key_exists($variable->key, $variables)) {
                $variables[$variable->key] = match ($variable->type) {
                    EmailVariableType::Text, EmailVariableType::Url, EmailVariableType::Number, EmailVariableType::Money, EmailVariableType::Date => '',
                    EmailVariableType::Boolean => false,
                    EmailVariableType::List => [],
                };
            }
        }

        return $variables;
    }

    /** @param list<string> $paths */
    private function removePaths(array $paths): void
    {
        foreach ($paths as $location) {
            [$disk, $path] = explode(':', $location, 2);
            Storage::disk($disk)->delete($path);
        }
    }
}
