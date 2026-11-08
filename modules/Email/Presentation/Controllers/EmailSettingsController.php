<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Litecommerce\Modules\Email\Application\HtmlSanitizer;
use Litecommerce\Modules\Email\Application\SafeTemplateRenderer;
use Litecommerce\Modules\Email\Contracts\EmailDefinition;
use Litecommerce\Modules\Email\Contracts\EmailDefinitionRegistry;
use Litecommerce\Modules\Email\Contracts\EmailEnvelope;
use Litecommerce\Modules\Email\Contracts\EmailSettings;
use Litecommerce\Modules\Email\Contracts\EmailTransportRegistry;
use Litecommerce\Modules\Email\Contracts\TransactionalEmailQueue;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailLayout;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessage;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailSendAttempt;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailTemplate;
use Litecommerce\Modules\Orders\Contracts\OrderStatusRegistry;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Store\Contracts\SettingsTabContent;
use Litecommerce\Modules\Store\Contracts\SettingsTabContext;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;

final class EmailSettingsController extends Controller
{
    public function __construct(
        private readonly EmailSettings $settings,
        private readonly EmailDefinitionRegistry $definitions,
        private readonly EmailTransportRegistry $transports,
        private readonly LanguageRepository $languages,
        private readonly OrderStatusRegistry $statuses,
        private readonly StoreSettingsStore $storeSettings,
    ) {}

    public function settingsTab(SettingsTabContext $context): SettingsTabContent
    {
        $settings = $this->settings->current();
        $languageCodes = array_map(static fn ($language): string => $language->code, array_values(array_filter(
            $this->languages->all(),
            static fn ($language): bool => $language->isEnabled,
        )));
        if ($languageCodes === []) {
            $languageCodes = [(string) config('app.fallback_locale', 'en')];
        }

        $overrides = EmailTemplate::query()->whereIn('locale', $languageCodes)->get()->groupBy('definition_key');
        $layouts = EmailLayout::query()->orderBy('locale')->orderBy('name')->get();
        $messages = EmailMessage::query()->with('attemptsLog')->latest()->limit(50)->get();
        $queuedCount = EmailMessage::query()->where('status', 'queued')->count();
        $oldestQueuedAt = EmailMessage::query()->where('status', 'queued')->min('created_at');

        return SettingsTabContent::component('email', [
            'settings' => [
                'from_name' => $settings->fromName,
                'from_email' => $settings->fromEmail,
                'reply_to' => $settings->replyTo,
                'transport_key' => $settings->transportKey,
                'smtp_host' => $settings->smtpHost,
                'smtp_port' => $settings->smtpPort,
                'smtp_encryption' => $settings->smtpEncryption,
                'smtp_username' => $settings->smtpUsername,
                'smtp_password_configured' => $settings->smtpPasswordConfigured,
                'status_notifications' => array_values(array_filter((array) $this->storeSettings->get('email.order_statuses', []), 'is_string')),
            ],
            'transports' => $this->transports->keys(),
            'languages' => $languageCodes,
            'definitions' => array_map(function (EmailDefinition $definition) use ($overrides, $languageCodes): array {
                $rows = $overrides->get($definition->key, collect())->keyBy('locale');
                $templates = [];
                foreach ($languageCodes as $locale) {
                    $defaults = $definition->defaults;
                    $fallback = $defaults[$locale] ?? $defaults[array_key_first($defaults)];
                    $row = $rows->get($locale);
                    if (! $row instanceof EmailTemplate) {
                        $row = null;
                    }
                    $enabled = $row === null || $row->enabled === null ? $definition->defaultEnabled : $row->enabled;
                    $templates[$locale] = [
                        'subject' => $row?->subject ?: $fallback['subject'],
                        'html' => $row?->html ?: $fallback['html'],
                        'enabled' => $definition->mandatory ? true : $enabled,
                        'layout_id' => $row?->layout_id,
                        'overridden' => $row !== null,
                    ];
                }

                return [
                    'key' => $definition->key,
                    'label' => trans($definition->label),
                    'mandatory' => $definition->mandatory,
                    'variables' => array_map(static fn ($variable): array => [
                        'key' => $variable->key,
                        'type' => $variable->type->value,
                        'label' => $variable->label,
                        'required' => $variable->required,
                    ], $definition->variables),
                    'templates' => $templates,
                ];
            }, $this->definitions->all()),
            'layouts' => $layouts->map(static fn (EmailLayout $layout): array => [
                'id' => $layout->id,
                'key' => $layout->key,
                'locale' => $layout->locale,
                'name' => $layout->name,
                'html' => $layout->html,
                'is_default' => $layout->is_default,
            ])->all(),
            'statuses' => array_map(static fn ($status): array => [
                'key' => $status->key,
                'label' => $status->label(),
                'customer_visible' => $status->customerVisible,
            ], $this->statuses->all()),
            'messages' => $messages->map(static fn (EmailMessage $message): array => [
                'id' => $message->id,
                'definition_key' => $message->definition_key,
                'source_type' => $message->source_type,
                'source_id' => $message->source_id,
                'recipient' => $message->to_email,
                'locale' => $message->locale,
                'mode' => $message->mode,
                'status' => $message->status,
                'attempts' => $message->attempts,
                'last_error_code' => $message->last_error_code,
                'last_error_message' => $message->last_error_message,
                'created_at' => $message->created_at?->toAtomString(),
                'accepted_at' => $message->accepted_at?->toAtomString(),
                'attempt_log' => $message->attemptsLog->sortByDesc('attempt_number')->values()->map(static fn (EmailSendAttempt $attempt): array => [
                    'number' => $attempt->attempt_number,
                    'outcome' => $attempt->outcome,
                    'error_code' => $attempt->error_code,
                    'duration_ms' => $attempt->duration_ms,
                    'started_at' => $attempt->started_at->toAtomString(),
                ])->all(),
            ])->all(),
            'queueHealth' => [
                'queued_count' => $queuedCount,
                'oldest_queued_at' => is_string($oldestQueuedAt) ? $oldestQueuedAt : null,
            ],
        ]);
    }

    public function update(Request $request, StoreSettingsStore $storeSettings, OrderStatusRegistry $statuses): RedirectResponse
    {
        $visibleStatuses = array_map(static fn ($status): string => $status->key, array_values(array_filter(
            $statuses->all(),
            static fn ($status): bool => $status->customerVisible,
        )));
        $data = $request->validate([
            'from_name' => ['required', 'string', 'max:255'],
            'from_email' => ['required', 'email', 'max:255'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'transport_key' => ['required', Rule::in($this->transports->keys())],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:1000'],
            'status_notifications' => ['array'],
            'status_notifications.*' => [Rule::in($visibleStatuses)],
        ]);
        $this->settings->update($data);
        $storeSettings->set('email.order_statuses', array_values(array_unique($data['status_notifications'] ?? [])));

        return back()->with('status', trans('email::panel.saved'));
    }

    public function updateTemplate(Request $request, string $definition, string $locale, HtmlSanitizer $sanitizer, SafeTemplateRenderer $renderer): RedirectResponse
    {
        $registered = $this->definitions->find($definition);
        abort_if($registered === null, 404);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'html' => ['required', 'string', 'max:200000'],
            'enabled' => ['required', 'boolean'],
            'layout_id' => ['nullable', 'integer', 'exists:email_layouts,id'],
        ]);
        try {
            $html = $sanitizer->sanitize((string) $data['html']);
            $layout = $data['layout_id'] === null ? '<div>{{content}}</div>' : (string) EmailLayout::query()->whereKey($data['layout_id'])->value('html');
            $renderer->subject((string) $data['subject'], $registered->sampleVariables);
            $renderer->html($html, $layout, $registered->sampleVariables);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['html' => $exception->getMessage()]);
        }

        EmailTemplate::query()->updateOrCreate(
            ['definition_key' => $registered->key, 'locale' => $locale],
            [
                'subject' => $data['subject'],
                'html' => $html,
                'enabled' => $registered->mandatory ? true : (bool) $data['enabled'],
                'layout_id' => $data['layout_id'],
            ],
        );

        return back()->with('status', trans('email::panel.template_saved'));
    }

    public function previewTemplate(Request $request, string $definition, HtmlSanitizer $sanitizer, SafeTemplateRenderer $renderer): JsonResponse
    {
        $registered = $this->definitions->find($definition);
        abort_if($registered === null, 404);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'html' => ['required', 'string', 'max:200000'],
            'layout_id' => ['nullable', 'integer', 'exists:email_layouts,id'],
        ]);
        try {
            $html = $sanitizer->sanitize((string) $data['html']);
            $layout = $data['layout_id'] === null ? '<div>{{content}}</div>' : (string) EmailLayout::query()->whereKey($data['layout_id'])->value('html');

            return response()->json([
                'subject' => $renderer->subject((string) $data['subject'], $registered->sampleVariables),
                'html' => $renderer->html($html, $layout, $registered->sampleVariables),
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function resetTemplate(string $definition, string $locale): RedirectResponse
    {
        abort_if($this->definitions->find($definition) === null, 404);
        EmailTemplate::query()->where('definition_key', $definition)->where('locale', $locale)->delete();

        return back()->with('status', trans('email::panel.template_reset'));
    }

    public function storeLayout(Request $request, HtmlSanitizer $sanitizer): RedirectResponse
    {
        $data = $this->layoutData($request);
        try {
            $html = $sanitizer->sanitize((string) $data['html']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['html' => $exception->getMessage()]);
        }
        if (! str_contains($html, '{{content}}')) {
            return back()->withErrors(['html' => trans('email::panel.layout_content_required')]);
        }
        $this->saveLayout(null, $data, $html);

        return back()->with('status', trans('email::panel.layout_saved'));
    }

    public function updateLayout(Request $request, int $layout, HtmlSanitizer $sanitizer): RedirectResponse
    {
        $model = EmailLayout::query()->find($layout);
        if (! $model instanceof EmailLayout) {
            return back()->with('error', trans('email::panel.layout_not_found'));
        }
        $data = $this->layoutData($request, $model->id);
        try {
            $html = $sanitizer->sanitize((string) $data['html']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['html' => $exception->getMessage()]);
        }
        if (! str_contains($html, '{{content}}')) {
            return back()->withErrors(['html' => trans('email::panel.layout_content_required')]);
        }
        $this->saveLayout($model, $data, $html);

        return back()->with('status', trans('email::panel.layout_saved'));
    }

    public function destroyLayout(int $layout): RedirectResponse
    {
        $model = EmailLayout::query()->find($layout);
        if (! $model instanceof EmailLayout) {
            return back()->with('error', trans('email::panel.layout_not_found'));
        }
        if (EmailTemplate::query()->where('layout_id', $model->id)->exists()) {
            return back()->with('error', trans('email::panel.layout_in_use'));
        }
        $model->delete();

        return back()->with('status', trans('email::panel.layout_deleted'));
    }

    public function test(Request $request, TransactionalEmailQueue $queue): RedirectResponse
    {
        $data = $request->validate([
            'definition_key' => ['required', 'string'],
            'locale' => ['required', 'string', 'max:10'],
            'recipient' => ['required', 'email', 'max:255'],
        ]);
        $definition = $this->definitions->find((string) $data['definition_key']);
        abort_if($definition === null, 404);
        $queue->enqueue(new EmailEnvelope(
            $definition->key,
            (string) $data['recipient'],
            null,
            (string) $data['locale'],
            $definition->sampleVariables,
            'email.test:'.Str::uuid(),
            'email_test',
            null,
            'test',
        ));

        return back()->with('status', trans('email::panel.test_queued'));
    }

    public function retry(string $message): RedirectResponse
    {
        $model = EmailMessage::query()->find($message);
        if (! $model instanceof EmailMessage) {
            return back()->with('error', trans('email::panel.message_not_found'));
        }
        if (! in_array($model->status, ['failed', 'delivery_unknown'], true)) {
            return back()->with('error', trans('email::panel.message_not_retryable'));
        }
        $model->update([
            'status' => 'queued',
            'available_at' => now(),
            'terminal_at' => null,
            'last_error_code' => null,
            'last_error_message' => null,
        ]);

        return back()->with('status', trans('email::panel.retry_queued'));
    }

    public function cancel(string $message): RedirectResponse
    {
        $model = EmailMessage::query()->find($message);
        if (! $model instanceof EmailMessage) {
            return back()->with('error', trans('email::panel.message_not_found'));
        }
        if (! in_array($model->status, ['queued', 'failed', 'delivery_unknown'], true)) {
            return back()->with('error', trans('email::panel.message_not_cancellable'));
        }
        $model->update([
            'status' => 'cancelled',
            'terminal_at' => now(),
            'lease_token' => null,
            'leased_until' => null,
        ]);

        return back()->with('status', trans('email::panel.message_cancelled'));
    }

    /** @return array<string, mixed> */
    private function layoutData(Request $request, ?int $ignore = null): array
    {
        return $request->validate([
            'key' => ['required', 'regex:/^[a-z][a-z0-9_.-]{1,99}$/', Rule::unique('email_layouts', 'key')->where('locale', $request->input('locale'))->ignore($ignore)],
            'locale' => ['required', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:255'],
            'html' => ['required', 'string', 'max:200000'],
            'is_default' => ['required', 'boolean'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function saveLayout(?EmailLayout $layout, array $data, string $html): void
    {
        if ((bool) $data['is_default']) {
            EmailLayout::query()->where('locale', $data['locale'])->when($layout !== null, fn ($query) => $query->where('id', '!=', $layout->id))->update(['is_default' => false]);
        }
        ($layout ?? new EmailLayout)->fill([
            'key' => $data['key'],
            'locale' => $data['locale'],
            'name' => $data['name'],
            'html' => $html,
            'is_default' => (bool) $data['is_default'],
        ])->save();
    }
}
