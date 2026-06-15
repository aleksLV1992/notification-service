# API Error Codes

Документация по кодам ошибок API Notification Service.

---

## Обзор

API использует стандартные HTTP коды состояния и расширенную информацию об ошибках в теле ответа.

**Формат ответа об ошибке:**
```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Человекочитаемое описание ошибки",
    "details": {}
  }
}
```

---

## 400 Bad Request

### VALIDATION_ERROR

**Описание:** Неверные входные данные.

**Причины:**
- Отсутствуют обязательные поля
- Неверный формат данных
- Пустой массив получателей

**Пример:**
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": {
      "recipients": ["The recipients field is required."],
      "message": ["The message field is required."]
    }
  }
}
```

---

### INVALID_CHANNEL

**Описание:** Неверный канал уведомления.

**Причины:**
- Канал не `sms` или `email`

**Пример:**
```json
{
  "error": {
    "code": "INVALID_CHANNEL",
    "message": "Channel must be 'sms' or 'email'",
    "details": {
      "provided": "push",
      "allowed": ["sms", "email"]
    }
  }
}
```

---

### EMPTY_RECIPIENTS

**Описание:** Массив получателей пуст.

**Причины:**
- `recipients: []`

**Пример:**
```json
{
  "error": {
    "code": "EMPTY_RECIPIENTS",
    "message": "At least one recipient is required",
    "details": {
      "recipients_count": 0
    }
  }
}
```

---

### INVALID_PRIORITY

**Описание:** Неверный приоритет уведомления.

**Причины:**
- Приоритет не `critical`, `normal` или `marketing`

**Пример:**
```json
{
  "error": {
    "code": "INVALID_PRIORITY",
    "message": "Priority must be 'critical', 'normal', or 'marketing'",
    "details": {
      "provided": "urgent",
      "allowed": ["critical", "normal", "marketing"]
    }
  }
}
```

---

## 404 Not Found

### NOTIFICATION_NOT_FOUND

**Описание:** Уведомление не найдено.

**Причины:**
- Неверный UUID уведомления
- Уведомление было удалено

**Пример:**
```json
{
  "error": {
    "code": "NOTIFICATION_NOT_FOUND",
    "message": "Notification not found",
    "details": {
      "notification_id": "550e8400-e29b-41d4-a716-446655440000"
    }
  }
}
```

---

### RECIPIENT_NOT_FOUND

**Описание:** Получатель не найден.

**Причины:**
- Неверный UUID получателя
- Получатель не принадлежит указанному уведомлению

**Пример:**
```json
{
  "error": {
    "code": "RECIPIENT_NOT_FOUND",
    "message": "Recipient not found for this notification",
    "details": {
      "notification_id": "550e8400-e29b-41d4-a716-446655440000",
      "recipient_identifier": "+1234567890"
    }
  }
}
```

---

## 409 Conflict

### DUPLICATE_REQUEST

**Описание:** Запрос с таким `idempotency_key` уже был обработан.

**Причины:**
- Повторная отправка с тем же ключом идемпотентности
- Сработала дедубликация

**Пример:**
```json
{
  "error": {
    "code": "DUPLICATE_REQUEST",
    "message": "Duplicate request - idempotency key already used",
    "details": {
      "idempotency_key": "550e8400-e29b-41d4-a716-446655440000",
      "original_notification_id": "660e8400-e29b-41d4-a716-446655440001",
      "original_created_at": "2024-01-01T12:00:00Z"
    }
  }
}
```

**HTTP заголовки:**
```
Location: /api/v1/notifications/660e8400-e29b-41d4-a716-446655440001
```

---

## 429 Too Many Requests

### RATE_LIMIT_EXCEEDED

**Описание:** Превышен лимит отправки сообщений.

**Причины:**
- Marketing рассылка: более 5 сообщений в час на получателя
- Critical уведомления: более 10 сообщений в минуту на получателя

**Пример:**
```json
{
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Rate limit exceeded for marketing notifications",
    "details": {
      "priority": "marketing",
      "recipient": "+1234567890",
      "limit": 5,
      "window": "1 hour",
      "retry_after": 1800
    }
  }
}
```

**HTTP заголовки:**
```
Retry-After: 1800
```

---

## 503 Service Unavailable

### CIRCUIT_BREAKER_OPEN

**Описание:** Провайдер временно недоступен, сработал Circuit Breaker.

**Причины:**
- Более 5 неудачных попыток подряд
- Провайдер не восстановился за 30 секунд

**Пример:**
```json
{
  "error": {
    "code": "CIRCUIT_BREAKER_OPEN",
    "message": "Provider temporarily unavailable",
    "details": {
      "provider": "sms_mock",
      "failure_count": 5,
      "retry_after": 25
    }
  }
}
```

**HTTP заголовки:**
```
Retry-After: 25
```

---

### SERVICE_UNHEALTHY

**Описание:** Один из зависимых сервисов недоступен.

**Причины:**
- PostgreSQL недоступен
- Redis недоступен
- RabbitMQ недоступен

**Пример:**
```json
{
  "error": {
    "code": "SERVICE_UNHEALTHY",
    "message": "Dependent service is unavailable",
    "details": {
      "service": "database",
      "error": "Connection refused",
      "checked_at": "2024-01-01T12:00:00Z"
    }
  }
}
```

**Проверка здоровья:**
```bash
GET /api/health
GET /api/health/database
GET /api/health/redis
GET /api/health/rabbitmq
```

---

## 500 Internal Server Error

### INTERNAL_ERROR

**Описание:** Внутренняя ошибка сервера.

**Причины:**
- Необработанное исключение
- Ошибка в коде

**Пример:**
```json
{
  "error": {
    "code": "INTERNAL_ERROR",
    "message": "An unexpected error occurred",
    "details": {
      "correlation_id": "550e8400-e29b-41d4-a716-446655440000"
    }
  }
}
```

**Примечание:** Используйте `correlation_id` для поиска ошибки в логах.

---

## Сводная таблица кодов

| HTTP Код | Code | Описание |
|----------|------|----------|
| 400 | VALIDATION_ERROR | Ошибка валидации |
| 400 | INVALID_CHANNEL | Неверный канал |
| 400 | EMPTY_RECIPIENTS | Пустой массив получателей |
| 400 | INVALID_PRIORITY | Неверный приоритет |
| 404 | NOTIFICATION_NOT_FOUND | Уведомление не найдено |
| 404 | RECIPIENT_NOT_FOUND | Получатель не найден |
| 409 | DUPLICATE_REQUEST | Дубликат запроса |
| 429 | RATE_LIMIT_EXCEEDED | Превышен лимит |
| 503 | CIRCUIT_BREAKER_OPEN | Circuit Breaker открыт |
| 503 | SERVICE_UNHEALTHY | Сервис недоступен |
| 500 | INTERNAL_ERROR | Внутренняя ошибка |

---

## Обработка ошибок на клиенте

### Пример на JavaScript

```javascript
async function sendNotification(data) {
  try {
    const response = await fetch('/api/v1/notifications/bulk', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Request-ID': crypto.randomUUID(),
      },
      body: JSON.stringify(data),
    });

    if (!response.ok) {
      const error = await response.json();
      
      switch (error.error.code) {
        case 'DUPLICATE_REQUEST':
          console.log('Request already processed:', error.details.original_notification_id);
          return { status: 'duplicate', notificationId: error.details.original_notification_id };
        
        case 'RATE_LIMIT_EXCEEDED':
          const retryAfter = response.headers.get('Retry-After');
          await sleep(retryAfter * 1000);
          return sendNotification(data); // Retry
          
        case 'CIRCUIT_BREAKER_OPEN':
          console.log('Provider unavailable, retry later');
          throw new Error('Service temporarily unavailable');
          
        default:
          throw new Error(error.error.message);
      }
    }

    return await response.json();
  } catch (error) {
    console.error('Failed to send notification:', error);
    throw error;
  }
}
```

### Пример на PHP

```php
try {
    $response = $httpClient->post('/api/v1/notifications/bulk', [
        'json' => $notificationData,
        'headers' => [
            'X-Request-ID' => (string) Str::uuid(),
        ],
    ]);

    return $response->json();
} catch (ClientException $e) {
    $error = json_decode($e->getResponse()->getBody(), true);
    
    switch ($error['error']['code']) {
        case 'DUPLICATE_REQUEST':
            // Возвращаем существующее уведомление
            return [
                'status' => 'duplicate',
                'notification_id' => $error['details']['original_notification_id'],
            ];
            
        case 'RATE_LIMIT_EXCEEDED':
            // Ждём и повторяем
            sleep($error['details']['retry_after']);
            return $this->sendNotification($notificationData);
            
        default:
            throw $e;
    }
}
```

---

## Логирование ошибок

Все ошибки логируются с контекстом:

```json
{
  "level": "error",
  "message": "API error",
  "correlation_id": "550e8400-e29b-41d4-a716-446655440000",
  "error_code": "RATE_LIMIT_EXCEEDED",
  "http_status": 429,
  "request": {
    "method": "POST",
    "path": "/api/v1/notifications/bulk",
    "ip": "192.168.1.1"
  },
  "context": {
    "priority": "marketing",
    "recipients_count": 100
  },
  "timestamp": "2024-01-01T12:00:00Z"
}
```
