# FastShift Pay — краткая выжимка и про IP для регистрации

Источник: `Vpos - Documentation/Documentation/FastShift/PayByFastShift (vers25.02.25).md`

---

## 1. Что к чему (по документу)

### Куда слать запросы

- **Базовый URL:** `https://merchants.fastshift.am/api/en/`
- **Регистрация заказа:** `POST https://merchants.fastshift.am/api/en/vpos/order/register`
- **Проверка статуса:** `GET https://merchants.fastshift.am/api/en/vpos/order/status/{order_number}`

### Авторизация

В каждом запросе заголовок:

```
Authorization: Bearer {Token}
Content-Type: application/json
```

Токен выдаёт FastShift.

### Регистрация заказа (POST `/vpos/order/register`)

**Тело запроса (JSON):**

| Поле | Описание | Обязательно |
|------|----------|-------------|
| `order_number` | Уникальный номер заказа (GUID) | Да |
| `amount` | Сумма к оплате (unsigned int) | Да |
| `description` | Текст на странице оплаты | Да |
| `callback_url` | URL возврата пользователя после оплаты (параметры: `status`, `order_number`) | Да |
| `webhook_url` | URL для серверного уведомления (POST с `status`, `order_number`) | Нет |
| `external_order_id` | Ваш ID заказа (со стороны мерчанта) | Нет |
| `username` | Номер телефона пользователя FastShift | Нет |
| `user_ssn` | СНИЛС для сверки с FastShift | Нет |
| `check_evoca_account` | Проверять привязку Evoca | Нет |
| `account_guid` | GUID привязанного Evoca-аккаунта | Нет |

**Успешный ответ (200):**

```json
{
  "status": "OK",
  "data": {
    "order": { "order_number", "order_guid", "amount", "description", "status", "created_at", "expires_at", ... },
    "redirect_url": "https://fastshift..."
  }
}
```

Пользователя нужно перенаправить на `data.redirect_url`.

### После оплаты

- FastShift перенаправляет пользователя на ваш **callback_url** с параметрами `status` и `order_number`.
- Если указан **webhook_url** — FastShift дополнительно шлёт POST на этот URL с результатом.

**Значения `status`:** `pending` | `completed` | `rejected` | `expired`.

### Проверка статуса

`GET /vpos/order/status/{order_number}` с тем же заголовком `Authorization: Bearer {Token}`.

---

## 2. Какой IP им давать для регистрации

**Имеется в виду не локальный IP пользователя** (не 192.168.x.x, не адрес его компьютера/телефона).

**Нужен исходящий IP вашего сервера** — то есть адрес, **с которого ваш бэкенд вызывает их API** (тот IP, который они увидят в логах при запросах к `https://merchants.fastshift.am/...`).

- **Где смотреть:** на том сервере/хостинге, где крутится ваш API (Next.js и т.п.), выполните `curl -s ifconfig.me` или откройте в браузере https://ifconfig.me — это и есть IP для указания FastShift.
- **Локальный IP (192.168.x.x, 10.x.x.x)** им не подходит — из интернета его не видно.
- **Если хостинг без фиксированного IP** (Vercel, serverless): исходящие IP могут меняться. Тогда нужно уточнить у FastShift, принимают ли они динамические IP или требуют фиксированный (в последнем случае — VPS или прокси с фиксированным IP).

**Итого:** для регистрации указывайте **публичный исходящий IP сервера**, с которого идут запросы к FastShift.
