<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\BulkNotificationData;
use App\Enums\Channel;
use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreBulkNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', new Enum(Channel::class)],
            'message' => ['required', 'string', 'max:1000'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['required', 'string', 'max:255'],
            'priority' => ['sometimes', new Enum(Priority::class)],
            'idempotency_key' => ['sometimes', 'string', 'uuid'],
            'metadata' => ['sometimes', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'channel.required' => 'Channel is required',
            'channel.enum' => 'Channel must be one of: sms, email',
            'message.required' => 'Message is required',
            'message.max' => 'Message must not exceed 1000 characters',
            'recipients.required' => 'At least one recipient is required',
            'recipients.array' => 'Recipients must be an array',
            'recipients.min' => 'At least one recipient is required',
            'priority.enum' => 'Priority must be one of: critical, normal, marketing',
            'idempotency_key.uuid' => 'Idempotency key must be a valid UUID',
        ];
    }

    public function attributes(): array
    {
        return [
            'channel' => 'notification channel',
            'message' => 'message content',
            'recipients' => 'recipients list',
            'priority' => 'notification priority',
            'idempotency_key' => 'idempotency key',
        ];
    }

    public function toDto(): BulkNotificationData
    {
        return new BulkNotificationData(
            channel: $this->input('channel'),
            message: $this->input('message'),
            recipients: $this->input('recipients'),
            priority: $this->input('priority', 'normal'),
            idempotency_key: $this->input('idempotency_key'),
            metadata: $this->input('metadata'),
        );
    }
}
