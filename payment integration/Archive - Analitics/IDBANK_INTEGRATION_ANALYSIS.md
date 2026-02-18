# Анализ подключения IDBank к интернет-магазину

## 📋 Краткое резюме

**IDBank использует систему Arca** для обработки платежей. Это означает, что интеграция IDBank идентична интеграции Arca. Основное отличие от Idram - **НЕ используются сервер-сервер callback'и**, только возврат пользователя через браузер.

---

## 🔍 Что я изучил

### 1. Документация
- ✅ **IDBank Merchant Manual** - полная документация API
- ✅ **Arca Merchant Manual** - идентичная документация (IDBank использует Arca)
- ✅ **AmeriaBank/Arca Callback Guide** - объяснение отличий от Idram
- ✅ **WordPress плагины** от двух разработчиков:
  - HK Agency (`payment-gateway-for-idbank`)
  - PlanetStudio Agency (`arca-payment-gateway`)

### 2. Ключевые выводы

#### IDBank = Arca
- IDBank полностью использует инфраструктуру Arca
- API endpoints идентичны Arca
- Тестовый URL: `https://ipaytest.arca.am:8445/payment/rest`
- Продакшн URL: `https://ipay.arca.am/payment/rest`

#### Отличия от Idram
| Параметр | Idram | IDBank/Arca |
|----------|-------|-------------|
| **Callback URL** | 3 URL (RESULT_URL, SUCCESS_URL, FAIL_URL) | 1 URL (returnUrl) |
| **Сервер-сервер callback'и** | ✅ Да (POST запросы) | ❌ Нет |
| **Регистрация URL** | ✅ Нужна | ❌ Не нужна |
| **Локальный домен** | ❌ Не работает для callback'ов | ✅ Работает |
| **Проверка статуса** | Через callback'и | Через API запрос |

---

## 🏗️ Архитектура работы IDBank/Arca

### Схема платежа (с вводом реквизитов на стороне платежного шлюза)

```
1. Пользователь нажимает "Оплатить" в интернет-магазине
   ↓
2. Магазин отправляет запрос register.do с параметрами:
   - userName, password (от банка)
   - orderNumber (номер заказа в магазине)
   - amount (сумма в минимальных единицах валюты, например копейки)
   - currency (код валюты ISO 4217: 051=AMD, 840=USD, 978=EUR, 643=RUB)
   - returnUrl (URL возврата пользователя)
   - description (описание заказа)
   - language (hy/ru/en)
   - jsonParams: {"FORCE_3DS2":"true"} (для 3DS2)
   ↓
3. Arca возвращает:
   - orderId (ID заказа в системе Arca)
   - formUrl (URL платежной формы)
   - errorCode (0 = успех)
   ↓
4. Магазин перенаправляет пользователя на formUrl
   ↓
5. Пользователь вводит данные карты на странице Arca
   ↓
6. Arca обрабатывает платеж (с 3DS или без)
   ↓
7. Arca перенаправляет пользователя на returnUrl с параметром orderId
   ↓
8. Магазин получает orderId и проверяет статус через getOrderStatusExtended.do
   ↓
9. Магазин обновляет статус заказа и показывает результат пользователю
```

---

## 🔑 Ключевые API методы

### 1. Регистрация заказа: `register.do`

**URL:**
- Тест: `https://ipaytest.arca.am:8445/payment/rest/register.do`
- Продакшн: `https://ipay.arca.am/payment/rest/register.do`

**Параметры запроса (POST, form-data):**
```typescript
{
  userName: string,        // Логин от банка
  password: string,         // Пароль от банка
  orderNumber: string,     // Номер заказа в магазине (уникальный)
  amount: number,          // Сумма в минимальных единицах (копейки/центы)
  currency: string,        // Код валюты: "051"=AMD, "840"=USD, "978"=EUR, "643"=RUB
  returnUrl: string,      // URL возврата: "http://localhost:3000/api/payment/idbank/callback?orderId=123"
  description?: string,   // Описание заказа
  language?: string,      // "hy" | "ru" | "en"
  pageView?: string,      // "MOBILE" | "DESKTOP"
  jsonParams?: string,    // '{"FORCE_3DS2":"true"}' для 3DS2
  clientId?: string       // ID клиента (для связок карт)
}
```

**Ответ:**
```json
{
  "orderId": "32faa424-858a-4f22-92c5-a50a9cfe56dc",
  "formUrl": "https://ipaytest.arca.am:8445/payment/merchants/...",
  "errorCode": 0,
  "errorMessage": ""
}
```

**Коды ошибок:**
- `0` - Успех
- `1` - Заказ с таким номером уже зарегистрирован
- `3` - Неизвестная валюта
- `4` - Отсутствует обязательный параметр
- `5` - Ошибка значения параметра
- `7` - Системная ошибка

---

### 2. Проверка статуса заказа: `getOrderStatusExtended.do`

**URL:**
- Тест: `https://ipaytest.arca.am:8445/payment/rest/getOrderStatusExtended.do`
- Продакшн: `https://ipay.arca.am/payment/rest/getOrderStatusExtended.do`

**Параметры запроса (POST, form-data):**
```typescript
{
  userName: string,    // Логин от банка
  password: string,    // Пароль от банка
  orderId: string     // ID заказа от Arca (из register.do)
}
```

**Ответ:**
```json
{
  "errorCode": 0,
  "errorMessage": "",
  "orderNumber": "12345",
  "orderStatus": 2,
  "actionCode": 0,
  "actionCodeDescription": "",
  "amount": 10000,
  "currency": "051",
  "date": "2026-01-27T12:00:00.000+04:00",
  "paymentAmountInfo": {
    "paymentState": "DEPOSITED",
    "approvedAmount": 10000,
    "depositedAmount": 10000,
    "refundedAmount": 0,
    "pan": "411111****1111",
    "expiration": "2512",
    "cardholderName": "IVAN IVANOV",
    "transactionId": "1234567890"
  }
}
```

**Статусы заказа:**
- `CREATED` (0) - Заказ создан
- `APPROVED` (1) - Пред-авторизация успешна (двухстадийный платеж)
- `DEPOSITED` (2) - Средства списаны ✅
- `DECLINED` (3) - Отклонен
- `REVERSED` (4) - Отменен
- `REFUNDED` (5) - Возврат средств

**paymentState:**
- `DEPOSITED` - Платеж успешен ✅
- `APPROVED` - Пред-авторизация (нужен deposit.do)
- `DECLINED` - Отклонен
- `REVERSED` - Отменен
- `REFUNDED` - Возврат

---

### 3. Дополнительные методы (опционально)

#### Отмена платежа: `reverse.do`
Для отмены платежа в течение 72 часов после оплаты.

#### Возврат средств: `refund.do`
Для возврата средств после списания.

#### Двухстадийный платеж: `registerPreAuth.do` + `deposit.do`
1. `registerPreAuth.do` - блокировка средств
2. `deposit.do` - списание средств (после подтверждения)

---

## 💻 Реализация для Next.js

### Структура файлов

```
src/
  app/
    api/
      payment/
        idbank/
          init/
            route.ts          # Регистрация заказа (register.do)
          callback/
            route.ts          # Обработка возврата пользователя (returnUrl)
          status/
            route.ts          # Проверка статуса (getOrderStatusExtended.do)
    checkout/
      page.tsx                # Страница оформления заказа (добавить IDBank)
    payment/
      success/
        page.tsx              # Страница успеха
      failed/
        page.tsx              # Страница ошибки
```

---

### 1. API Route: Инициализация платежа (`/api/payment/idbank/init`)

```typescript
// src/app/api/payment/idbank/init/route.ts
import { NextRequest, NextResponse } from 'next/server';
import { prisma } from '@/lib/prisma';

export async function POST(request: NextRequest) {
  try {
    const { orderId } = await request.json();
    
    // Получаем заказ из БД
    const order = await prisma.order.findUnique({
      where: { id: orderId }
    });
    
    if (!order) {
      return NextResponse.json({ error: 'Order not found' }, { status: 404 });
    }
    
    // Конфигурация
    const isTestMode = process.env.IDBANK_TEST_MODE === 'true';
    const apiUrl = isTestMode
      ? 'https://ipaytest.arca.am:8445/payment/rest/register.do'
      : 'https://ipay.arca.am/payment/rest/register.do';
    
    const userName = isTestMode
      ? process.env.IDBANK_TEST_USERNAME!
      : process.env.IDBANK_USERNAME!;
    
    const password = isTestMode
      ? process.env.IDBANK_TEST_PASSWORD!
      : process.env.IDBANK_PASSWORD!;
    
    // Конвертация валюты
    const currencyMap: Record<string, string> = {
      'AMD': '051',
      'USD': '840',
      'EUR': '978',
      'RUB': '643'
    };
    
    const currency = currencyMap[order.currency] || '051';
    
    // Сумма в минимальных единицах (копейки/центы)
    const amount = Math.round(order.total * 100);
    
    // returnUrl с нашим orderId для идентификации
    const baseUrl = process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000';
    const returnUrl = `${baseUrl}/api/payment/idbank/callback?ourOrderId=${orderId}`;
    
    // Параметры запроса
    const formData = new URLSearchParams();
    formData.append('userName', userName);
    formData.append('password', password);
    formData.append('orderNumber', orderId);
    formData.append('amount', amount.toString());
    formData.append('currency', currency);
    formData.append('returnUrl', returnUrl);
    formData.append('description', `Order #${orderId}`);
    formData.append('language', 'ru');
    formData.append('jsonParams', JSON.stringify({ FORCE_3DS2: 'true' }));
    
    // Отправка запроса
    const response = await fetch(apiUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: formData
    });
    
    const data = await response.json();
    
    if (data.errorCode !== 0) {
      return NextResponse.json({
        error: data.errorMessage || 'Payment registration failed',
        errorCode: data.errorCode
      }, { status: 400 });
    }
    
    // Сохраняем orderId от Arca в заказе
    await prisma.order.update({
      where: { id: orderId },
      data: {
        paymentId: data.orderId,
        paymentStatus: 'PENDING'
      }
    });
    
    return NextResponse.json({
      orderId: data.orderId,
      formUrl: data.formUrl
    });
    
  } catch (error) {
    console.error('IDBank init error:', error);
    return NextResponse.json(
      { error: 'Internal server error' },
      { status: 500 }
    );
  }
}
```

---

### 2. API Route: Callback обработка (`/api/payment/idbank/callback`)

```typescript
// src/app/api/payment/idbank/callback/route.ts
import { NextRequest, NextResponse } from 'next/server';
import { prisma } from '@/lib/prisma';
import { redirect } from 'next/navigation';

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const arcaOrderId = searchParams.get('orderId'); // ID от Arca
    const ourOrderId = searchParams.get('ourOrderId'); // Наш orderId
    
    if (!arcaOrderId) {
      return redirect('/payment/failed?error=no_order_id');
    }
    
    // ВАЖНО: Проверяем статус через API (не доверяем только URL параметрам!)
    const isTestMode = process.env.IDBANK_TEST_MODE === 'true';
    const apiUrl = isTestMode
      ? 'https://ipaytest.arca.am:8445/payment/rest/getOrderStatusExtended.do'
      : 'https://ipay.arca.am/payment/rest/getOrderStatusExtended.do';
    
    const userName = isTestMode
      ? process.env.IDBANK_TEST_USERNAME!
      : process.env.IDBANK_USERNAME!;
    
    const password = isTestMode
      ? process.env.IDBANK_TEST_PASSWORD!
      : process.env.IDBANK_PASSWORD!;
    
    const formData = new URLSearchParams();
    formData.append('userName', userName);
    formData.append('password', password);
    formData.append('orderId', arcaOrderId);
    
    const response = await fetch(apiUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: formData
    });
    
    const data = await response.json();
    
    // Проверяем статус платежа
    const paymentState = data.paymentAmountInfo?.paymentState;
    
    if (paymentState === 'DEPOSITED') {
      // Платеж успешен
      if (ourOrderId) {
        await prisma.order.update({
          where: { id: ourOrderId },
          data: {
            paymentStatus: 'PAID',
            paymentId: arcaOrderId,
            status: 'CONFIRMED'
          }
        });
      }
      
      return redirect(`/payment/success?orderId=${ourOrderId || ''}`);
    } else {
      // Платеж не прошел
      if (ourOrderId) {
        await prisma.order.update({
          where: { id: ourOrderId },
          data: {
            paymentStatus: 'FAILED',
            paymentId: arcaOrderId
          }
        });
      }
      
      return redirect(`/payment/failed?orderId=${ourOrderId || ''}&error=${data.errorMessage || 'Payment failed'}`);
    }
    
  } catch (error) {
    console.error('IDBank callback error:', error);
    return redirect('/payment/failed?error=internal_error');
  }
}
```

---

### 3. Обновление страницы Checkout

```typescript
// src/app/checkout/page.tsx
// Добавить метод оплаты IDBank

const handlePayment = async () => {
  if (formData.paymentMethod === 'idbank') {
    try {
      // Создаем заказ
      const orderResponse = await fetch('/api/orders', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ...formData,
          paymentMethod: 'idbank'
        })
      });
      
      const order = await orderResponse.json();
      
      // Инициализируем платеж
      const paymentResponse = await fetch('/api/payment/idbank/init', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ orderId: order.id })
      });
      
      const { formUrl } = await paymentResponse.json();
      
      // Перенаправляем на страницу оплаты Arca
      window.location.href = formUrl;
      
    } catch (error) {
      console.error('Payment error:', error);
    }
  }
};
```

---

## 🔐 Переменные окружения

Добавить в `.env.local`:

```bash
# IDBank/Arca Payment Gateway
IDBANK_TEST_MODE=true
IDBANK_TEST_USERNAME=your_test_username
IDBANK_TEST_PASSWORD=your_test_password
IDBANK_USERNAME=your_production_username
IDBANK_PASSWORD=your_production_password

# Base URL для returnUrl
NEXT_PUBLIC_BASE_URL=http://localhost:3000
```

---

## ✅ Чек-лист реализации

### Этап 1: Базовая интеграция
- [ ] Создать API route `/api/payment/idbank/init`
- [ ] Создать API route `/api/payment/idbank/callback`
- [ ] Добавить переменные окружения
- [ ] Обновить страницу checkout (добавить IDBank)
- [ ] Обновить типы (добавить 'idbank' в PaymentMethod)

### Этап 2: Обработка статусов
- [ ] Создать страницу `/payment/success`
- [ ] Создать страницу `/payment/failed`
- [ ] Обновить модель Order в Prisma (добавить paymentId, paymentStatus)
- [ ] Логирование платежей

### Этап 3: Тестирование
- [ ] Тестовая регистрация заказа
- [ ] Тестовая проверка статуса
- [ ] Тестовая оплата с тестовой картой
- [ ] Проверка обработки ошибок

### Этап 4: Продакшн
- [ ] Получить продакшн credentials от банка
- [ ] Обновить переменные окружения
- [ ] Переключить IDBANK_TEST_MODE=false
- [ ] Тестовая оплата реальной картой

---

## 📝 Важные замечания

### Безопасность
1. **НЕ доверяйте только URL параметрам!** Всегда проверяйте статус через API
2. Параметры в URL могут быть подделаны
3. Используйте API `getOrderStatusExtended.do` для получения реального статуса

### Локальное тестирование
- ✅ Можно использовать `http://localhost:3000` для returnUrl
- ✅ Arca не требует регистрации URL заранее
- ✅ Пользователь возвращается через браузер, поэтому localhost работает

### Валюты
- AMD: `051`
- USD: `840`
- EUR: `978`
- RUB: `643`

### Сумма
- Всегда передавать в минимальных единицах валюты
- AMD: в драммах (1 драм = 100 лум, но обычно передают в драммах)
- USD/EUR: в центах (1 доллар = 100 центов)

### 3DS2
- Используйте `jsonParams: {"FORCE_3DS2":"true"}` для принудительного 3DS2
- Это повышает безопасность платежей

---

## 🔄 Сравнение с существующими платежами

В проекте уже есть:
- ✅ Структура заказов (Prisma)
- ✅ Страница checkout
- ✅ API routes для заказов

Нужно добавить:
- ❌ IDBank payment gateway
- ❌ API routes для инициализации и callback
- ❌ Страницы success/failed
- ❌ Обновить типы и модели

---

## 📚 Полезные ссылки

- Документация IDBank: `example-Vpos/Documentation/IDBank/Merchant Manual_1.55.1.0.md`
- Документация Arca: `example-Vpos/Documentation/Arca/Merchant Manual_1.55.1.0.md`
- Пример плагина HK Agency: `example-Vpos/HK Agency/payment-gateway-for-idbank/`
- Пример плагина PlanetStudio: `example-Vpos/PlanetStudio Agency/arca-payment-gateway-test/`

---

## 🎯 Следующие шаги

1. **Получить credentials от IDBank:**
   - Тестовые: username, password
   - Продакшн: username, password

2. **Реализовать базовую интеграцию:**
   - API routes
   - Обновить checkout
   - Добавить переменные окружения

3. **Протестировать:**
   - Локально с тестовыми credentials
   - С тестовыми картами

4. **Деплой:**
   - Настроить продакшн credentials
   - Протестировать реальной картой

---

**Готов к реализации!** 🚀
