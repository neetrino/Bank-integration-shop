# Ameria Bank vPOS 3.1 Integration Module

Модуль интеграции с платежной системой Ameria Bank vPOS 3.1 для Next.js приложения.

## Описание

Этот модуль реализует полную интеграцию с Ameria Bank vPOS 3.1 API согласно официальной документации. Модуль поддерживает:

- Инициализацию платежей
- Обработку callback от банка
- Проверку статуса платежей
- Возвраты (refund)
- Отмену платежей (cancel)
- Двухэтапные платежи (preauthorization + confirmation)
- Привязку карт (binding) для повторных платежей

## Структура модуля

```
ameriabank/
├── types.ts          # TypeScript типы и интерфейсы для API
├── config.ts         # Конфигурация и получение credentials
├── service.ts        # Основной сервис для работы с API
├── index.ts          # Экспорт модуля
└── README.md         # Документация
```

## Установка и настройка

### 1. Переменные окружения

Добавьте следующие переменные в ваш `.env` файл:

```env
# Test environment (для тестирования)
AMERIA_BANK_ENVIRONMENT=test
AMERIA_BANK_CLIENT_ID=f707bd34-5263-4a35-a5d5-50f67967ba57
AMERIA_BANK_USERNAME=3d19541048
AMERIA_BANK_PASSWORD=lazY2k

# Production environment (после получения реальных credentials)
# AMERIA_BANK_ENVIRONMENT=production
# AMERIA_BANK_CLIENT_ID=your-production-client-id
# AMERIA_BANK_USERNAME=your-production-username
# AMERIA_BANK_PASSWORD=your-production-password
```

### 2. Тестовые данные

Для тестирования используйте следующие данные:

**Тестовая карта:**
- Card number: `4083060013681818`
- Cardholder: `TEST CARD VPOS`
- Exp. date: `05/28`
- CVV: `233`

**OrderID диапазон для тестов:** `3584001-3585000`

**Тестовая сумма:** `10 AMD`

## Использование

### Инициализация платежа

```typescript
import { AmeriaBankService } from '@/lib/payments/ameriabank'

const ameriaService = new AmeriaBankService()

// Инициализация платежа
const initResponse = await ameriaService.initPayment({
  OrderID: 1234567, // Должен быть integer
  Amount: 1000, // Сумма в AMD
  Currency: '051', // Код валюты (051 = AMD)
  Description: 'Order #12345',
  BackURL: 'https://yoursite.com/api/payments/ameriabank/callback',
  Opaque: 'order-12345', // Ваш ID заказа для идентификации в callback
})
```

### Проверка статуса платежа

```typescript
const paymentDetails = await ameriaService.getPaymentDetails(paymentID)

if (ameriaService.isPaymentSuccessful(paymentDetails)) {
  // Платеж успешен
  console.log('Payment successful!')
} else if (ameriaService.isPaymentDeclined(paymentDetails)) {
  // Платеж отклонен
  console.log('Payment declined')
}
```

### Возврат средств

```typescript
const refundResponse = await ameriaService.refundPayment(paymentID, amount)
```

### Отмена платежа

```typescript
const cancelResponse = await ameriaService.cancelPayment(paymentID)
```

## API Routes

Модуль включает готовые API routes:

### POST `/api/payments/ameriabank/init`

Инициализирует платеж и возвращает PaymentID и URL для редиректа.

**Request:**
```json
{
  "orderId": "order-12345",
  "amount": 1000,
  "currency": "AMD",
  "description": "Order #12345"
}
```

**Response:**
```json
{
  "success": true,
  "paymentID": "payment-uuid",
  "paymentUrl": "https://servicestest.ameriabank.am/VPOS/Payments/Pay?id=...",
  "bankOrderID": 1234567
}
```

### GET `/api/payments/ameriabank/callback`

Обрабатывает callback от банка после оплаты. Автоматически обновляет статус заказа.

### POST `/api/payments/ameriabank/status`

Проверяет статус платежа по PaymentID.

**Request:**
```json
{
  "paymentID": "payment-uuid",
  "orderId": "order-12345" // опционально
}
```

## Интеграция в Checkout

Модуль уже интегрирован в страницу checkout (`/checkout`). Пользователь может выбрать "Онлайн оплата" (Ameria Bank) как способ оплаты.

При выборе этого метода:
1. Создается заказ
2. Инициализируется платеж через API
3. Пользователь перенаправляется на страницу оплаты банка
4. После оплаты банк перенаправляет на callback URL
5. Callback обновляет статус заказа и перенаправляет на страницу успеха

## Важные замечания

### OrderID

- OrderID **должен быть integer** (не строка)
- В тестовом режиме используйте диапазон `3584001-3585000`
- В продакшене можно использовать хеш от вашего order ID

### Currency

- Используйте числовые коды ISO 4217:
  - `051` = AMD (Армянский драм)
  - `978` = EUR
  - `840` = USD
  - `643` = RUB

### Description

- Поле `Description` обязательное
- Рекомендуется передавать осмысленное описание (например, "Order #12345")

### Callback параметры

Банк отправляет параметры в **lowercase** и с опечаткой:
- `orderID` (не `orderId`)
- `resposneCode` (не `responseCode`) - опечатка в API!
- `paymentID` (не `paymentId`)
- `opaque` (не `Opaque`)

### Payment State

Проверяйте статус платежа по:
- `ResponseCode === "00"` - успешный ответ
- `OrderStatus === 2` - платеж завершен (payment_deposited)
- `OrderStatus === 1` - платеж одобрен, но не завершен (preauthorized)
- `OrderStatus === 6` - платеж отклонен

## Тестирование

1. Убедитесь, что переменные окружения настроены для тестового режима
2. Создайте заказ на сумму 10 AMD
3. Выберите "Онлайн оплата" (Ameria Bank)
4. Используйте тестовую карту для оплаты
5. Проверьте, что заказ обновился после callback

## Переход на Production

1. Получите production credentials от банка
2. Обновите переменные окружения:
   ```env
   AMERIA_BANK_ENVIRONMENT=production
   AMERIA_BANK_CLIENT_ID=your-production-client-id
   AMERIA_BANK_USERNAME=your-production-username
   AMERIA_BANK_PASSWORD=your-production-password
   ```
3. Обновите логику генерации OrderID (уберите тестовый диапазон)
4. Протестируйте на реальных картах

## Дополнительные функции

### Binding (привязка карт)

Модуль поддерживает привязку карт для повторных платежей:

```typescript
// Получить список привязанных карт
const bindings = await ameriaService.getBindings(PaymentType.Binding)

// Создать платеж с привязанной картой
const bindingPayment = await ameriaService.makeBindingPayment({
  CardHolderID: 'user-card-id',
  OrderID: 1234567,
  Amount: 1000,
  BackURL: 'https://yoursite.com/callback',
  PaymentType: PaymentType.Binding,
})
```

## Поддержка

При возникновении проблем проверьте:
1. Правильность credentials в `.env`
2. Логи в консоли сервера
3. Ответы от API банка (все запросы логируются)

## Ссылки

- [Официальная документация vPOS 3.1](./example-Vpos/Documentation/AmeriaBank/vPOS%20-%20Ameriabank.md)
- [Анализ плагинов](./example-Vpos/AMERIABANK_PLUGINS_ANALYSIS.md)
