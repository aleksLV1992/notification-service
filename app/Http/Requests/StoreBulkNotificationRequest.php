<?php

namespace App\Http\Requests;

use App\DTO\BulkNotificationData;
use App\Enums\Channel;
use App\Enums\Priority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreBulkNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
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

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
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

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
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

    /**
     * Convert the validated data to a DTO.
     */
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
