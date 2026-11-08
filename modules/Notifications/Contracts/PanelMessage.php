<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Notifications\Contracts;

use InvalidArgumentException;

/** One message for panel staff. The key identifies the problem: a new key is shown again to staff who read the old one. */
final readonly class PanelMessage
{
    public function __construct(
        public string $key,
        public PanelMessageSeverity $severity,
        public string $title,
        public ?string $body = null,
        public ?string $actionLabel = null,
        public ?string $actionUrl = null,
        public ?string $permission = null,
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9_.:-]{0,149}$/', $key) !== 1) {
            throw new InvalidArgumentException("Invalid panel message key '{$key}'.");
        }

        if (trim($title) === '') {
            throw new InvalidArgumentException('A panel message needs a title.');
        }

        if ($actionUrl !== null && preg_match('#^(/(?!/)|https?://)#i', $actionUrl) !== 1) {
            throw new InvalidArgumentException('The action URL must be a site path or an http(s) address.');
        }

        if (($actionUrl === null) !== ($actionLabel === null)) {
            throw new InvalidArgumentException('The action label and the action URL go together.');
        }
    }

    /** @return array{key: string, severity: string, title: string, body: ?string, action_label: ?string, action_url: ?string} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'severity' => $this->severity->value,
            'title' => $this->title,
            'body' => $this->body,
            'action_label' => $this->actionLabel,
            'action_url' => $this->actionUrl,
        ];
    }
}
