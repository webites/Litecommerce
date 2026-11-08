<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Infrastructure\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $definition_key
 * @property string|null $source_type
 * @property string|null $source_id
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property string $mode
 * @property string $locale
 * @property string $to_email
 * @property string|null $to_name
 * @property array<string, mixed> $variables
 * @property string $template_subject
 * @property string $template_html
 * @property string $layout_html
 * @property string|null $rendered_subject
 * @property string|null $rendered_html
 * @property string|null $rendered_text
 * @property string $transport_key
 * @property string $status
 * @property int $attempts
 * @property Carbon $available_at
 * @property Carbon|null $leased_until
 * @property Carbon|null $accepted_at
 * @property Carbon|null $terminal_at
 * @property string|null $last_error_code
 * @property string|null $last_error_message
 * @property Carbon|null $created_at
 * @property-read Collection<int, EmailMessageAttachment> $attachments
 * @property-read Collection<int, EmailSendAttempt> $attemptsLog
 */
final class EmailMessage extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return HasMany<EmailMessageAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(EmailMessageAttachment::class);
    }

    /** @return HasMany<EmailSendAttempt, $this> */
    public function attemptsLog(): HasMany
    {
        return $this->hasMany(EmailSendAttempt::class);
    }

    protected function casts(): array
    {
        return [
            'to_email' => 'encrypted',
            'to_name' => 'encrypted',
            'variables' => 'encrypted:array',
            'template_subject' => 'encrypted',
            'template_html' => 'encrypted',
            'layout_html' => 'encrypted',
            'rendered_subject' => 'encrypted',
            'rendered_html' => 'encrypted',
            'rendered_text' => 'encrypted',
            'available_at' => 'datetime',
            'leased_until' => 'datetime',
            'accepted_at' => 'datetime',
            'terminal_at' => 'datetime',
        ];
    }
}
