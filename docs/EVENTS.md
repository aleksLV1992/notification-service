# Events Documentation

Документация по событиям доменов (Domain Events).

---

## Обзор

События используются для:
- Логирования действий в audit log
- Отправки метрик в Prometheus
- Уведомления внешних систем через webhooks
- Триггеринга бизнес-процессов

---

## NotificationSent

**Описание:** Событие отправляется, когда уведомление успешно отправлено провайдеру.

**Когда генерируется:**
- После успешного вызова `NotificationProvider::send()`
- Статус получателя меняется на `sent`

**Payload:**
```php
class NotificationSent
{
    public NotificationRecipient $recipient;
}
```

**Данные получателя:**
| Поле | Тип | Описание |
|------|-----|----------|
| `id` | UUID | ID получателя |
| `notification_id` | UUID | ID уведомления |
| `recipient_identifier` | string | Email или телефон |
| `channel` | string | Канал (sms/email) |
| `status` | string | `sent` |
| `sent_at` | datetime | Время отправки |

**Listeners:**
- `LogSentNotification` — запись в audit log
- `UpdateSentMetrics` — обновление метрик Prometheus

**Пример использования:**
```php
Event::listen(NotificationSent::class, function (NotificationSent $event) {
    Log::channel('audit')->info('Notification sent', [
        'recipient_id' => $event->recipient->id,
        'channel' => $event->recipient->notification->channel,
    ]);
});
```

---

## NotificationDelivered

**Описание:** Событие отправляется, когда уведомление подтверждено как доставленное.

**Когда генерируется:**
- После успешной доставки провайдером
- Статус получателя меняется на `delivered`

**Payload:**
```php
class NotificationDelivered
{
    public NotificationRecipient $recipient;
}
```

**Данные получателя:**
| Поле | Тип | Описание |
|------|-----|----------|
| `id` | UUID | ID получателя |
| `notification_id` | UUID | ID уведомления |
| `recipient_identifier` | string | Email или телефон |
| `channel` | string | Канал (sms/email) |
| `status` | string | `delivered` |
| `delivered_at` | datetime | Время доставки |

**Listeners:**
- `LogDeliveredNotification` — запись в audit log
- `UpdateDeliveredMetrics` — обновление метрик Prometheus
- `SendDeliveryCallback` — webhook во внешнюю систему

**Пример использования:**
```php
Event::listen(NotificationDelivered::class, function (NotificationDelivered $event) {
    // Отправка webhook
    Http::post('https://external-system.com/webhooks/delivery', [
        'notification_id' => $event->recipient->notification_id,
        'status' => 'delivered',
        'delivered_at' => $event->recipient->delivered_at->toIso8601String(),
    ]);
});
```

---

## NotificationFailed

**Описание:** Событие отправляется, когда доставка уведомления не удалась после всех попыток.

**Когда генерируется:**
- После исчерпания всех retry попыток (3 попытки)
- Статус получателя меняется на `failed`

**Payload:**
```php
class NotificationFailed
{
    public NotificationRecipient $recipient;
    public string $errorMessage;
}
```

**Данные получателя:**
| Поле | Тип | Описание |
|------|-----|----------|
| `id` | UUID | ID получателя |
| `notification_id` | UUID | ID уведомления |
| `recipient_identifier` | string | Email или телефон |
| `channel` | string | Канал (sms/email) |
| `status` | string | `failed` |
| `error_message` | string | Текст ошибки |
| `attempts` | int | Количество попыток |
| `failed_at` | datetime | Время неудачи |

**Listeners:**
- `LogFailedNotification` — запись в audit log
- `UpdateFailedMetrics` — обновление метрик Prometheus
- `AlertOnFailure` — отправка alert в Slack/PagerDuty для critical уведомлений

**Пример использования:**
```php
Event::listen(NotificationFailed::class, function (NotificationFailed $event) {
    // Alert для critical уведомлений
    if ($event->recipient->notification->priority === 'critical') {
        Alert::send('Critical notification failed', [
            'recipient' => $event->recipient->recipient_identifier,
            'error' => $event->errorMessage,
            'attempts' => $event->recipient->attempts,
        ]);
    }
});
```

---

## NotificationRecipientCreated

**Описание:** Событие отправляется при создании получателя уведомления.

**Когда генерируется:**
- При создании новой записи `NotificationRecipient`

**Payload:**
```php
class NotificationRecipientCreated
{
    public NotificationRecipient $recipient;
}
```

**Listeners:**
- `LogRecipientCreated` — запись в audit log

---

## Диаграмма последовательности

```
┌─────────┐     ┌──────────────┐     ┌─────────────┐     ┌────────────┐
│  Job    │     │  Provider    │     │   Model     │     │   Event    │
└────┬────┘     └──────┬───────┘     └──────┬──────┘     └─────┬──────┘
     │                 │                    │                   │
     │  send()         │                    │                   │
     ├────────────────>│                    │                   │
     │                 │                    │                   │
     │  success        │                    │                   │
     │<────────────────┤                    │                   │
     │                 │                    │                   │
     │                 │  markAsSent()      │                   │
     │                 ├───────────────────>│                   │
     │                 │                    │                   │
     │                 │                    │  event(new ...)   │
     │                 │                    ├──────────────────>│
     │                 │                    │                   │
     │                 │                    │                   │ dispatch
     │                 │                    │                   ├─────────> Listeners
```

---

## Best Practices

### 1. Обработка событий

```php
// ✅ Правильно: используйте async listeners
class LogSentNotification implements ShouldQueue
{
    public function handle(NotificationSent $event): void
    {
        Log::channel('audit')->info('Notification sent', [
            'recipient_id' => $event->recipient->id,
        ]);
    }
}

// ❌ Неправильно: блокирующая логика в listener
class LogSentNotification
{
    public function handle(NotificationSent $event): void
    {
        // Блокирующий HTTP вызов
        Http::post('https://audit-system.com/log', [...]);
    }
}
```

### 2. Идемпотентность

```php
// ✅ Правильно: проверяйте дубликаты
class UpdateSentMetrics
{
    public function handle(NotificationSent $event): void
    {
        $key = "metrics:sent:{$event->recipient->id}";
        
        if (Redis::exists($key)) {
            return; // Уже обработано
        }
        
        Redis::setex($key, 86400, '1');
        $this->metrics->incrementNotificationSent(...);
    }
}
```

### 3. Обработка ошибок

```php
// ✅ Правильно: логируйте ошибки в listener
class AlertOnFailure
{
    public function handle(NotificationFailed $event): void
    {
        try {
            if ($event->recipient->notification->priority->isCritical()) {
                $this->alertService->send(...);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send alert', [
                'error' => $e->getMessage(),
                'original_event' => $event->recipient->id,
            ]);
        }
    }
}
```

---

## Мониторинг

### Метрики Prometheus

```
# Отправленные уведомления
notifications_sent_total{channel="sms",priority="critical"}

# Доставленные уведомления
notifications_delivered_total{channel="email",priority="normal"}

# Неудачные уведомления
notifications_failed_total{channel="sms",error="timeout"}

# Время обработки
notification_latency_ms_bucket{channel="sms",le="100"}
```

### Alerts

```yaml
# Prometheus alert rules
- alert: HighNotificationFailureRate
  expr: rate(notifications_failed_total[5m]) > 0.1
  for: 5m
  labels:
    severity: warning
  annotations:
    summary: "High notification failure rate"

- alert: CircuitBreakerOpen
  expr: circuit_breaker_triggered_total > 0
  for: 1m
  labels:
    severity: critical
  annotations:
    summary: "Circuit breaker is open for {{ $labels.provider }}"
```
