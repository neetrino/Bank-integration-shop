# Полное руководство по интеграции Ameriabank в Next.js интернет-магазин

## 📋 Краткое резюме

**Ameriabank** - это банковская платежная система Армении, которая использует **REST API vPOS 3.1** для обработки платежей. В отличие от Idram, Ameriabank **НЕ использует сервер-сервер callback'и** - только возврат пользователя через браузер и проверка статуса через API.

**⚠️ Важно:** Данный файл создан на основе **официальной документации vPOS 3.1** от Ameriabank и проверен дважды на соответствие.

**Основные исправления после проверки официальной документации:**
- ✅ Currency: Используются числовые коды ISO 4217 как строки ("051", "978", "840", "643"), НЕ буквенные коды
- ✅ OrderID: Должен быть integer (не string)
- ✅ PaymentState: Официальное значение "payment_deposited" (OrderStatus: 2), но может быть "Successful" в некоторых реализациях
- ✅ Description: Обязательный параметр (не опциональный)
- ✅ BackURL параметры: lowercase (orderID, paymentID, resposneCode, opaque)
- ✅ Timeout: Добавлен параметр (максимум 1200 секунд)

---

## 🔍 Что я изучил

### 1. Документация
- ✅ **Официальная документация vPOS 3.1** - `example-Vpos/Documentation/AmeriaBank/vPOS - Ameriabank.md` (основной источник)
- ✅ **Ameriabank Callback Guide** - объяснение BackURL и доменов
- ✅ **Payment Integration Guide** - полное руководство по интеграции
- ✅ **Testing Guide** - руководство по тестированию
- ✅ **WordPress плагины** от двух разработчиков:
  - PlanetStudio Agency (`arca-payment-gateway`)
  - HK Agency (`payment-gateway-for-ameriabank`)

### 2. Ключевые отличия от других систем

| Параметр | Idram | Ameriabank | IDBank/Arca |
|----------|-------|------------|-------------|
| **Callback URL** | 3 URL (RESULT, SUCCESS, FAIL) | 1 URL (BackURL) | 1 URL (returnUrl) |
| **Сервер-сервер callback'и** | ✅ Да (2 POST запроса) | ❌ Нет | ❌ Нет |
| **Регистрация URL** | ✅ Нужна | ❌ Не нужна | ❌ Не нужна |
| **Локальный домен** | ❌ Не работает | ✅ Работает | ✅ Работает |
| **Проверка статуса** | Через callback'и | Через API (GetPaymentDetails) | Через API (getOrderStatusExtended) |
| **API формат** | HTML форма | REST JSON | REST form-data |
| **Валюты** | Только AMD | AMD, USD, EUR, RUB (коды: 051, 840, 978, 643) | AMD, USD, EUR, RUB |

---

## 🏗️ Архитектура работы Ameriabank

### Схема платежа

```
1. Пользователь нажимает "Оплатить" в интернет-магазине
   ↓
2. Магазин отправляет POST запрос InitPayment с параметрами:
   - ClientID, Username, Password
   - OrderID (номер заказа в магазине)
   - Amount (сумма)
   - Currency (коды ISO 4217: "051"=AMD, "978"=EUR, "840"=USD, "643"=RUB)
   - BackURL (URL возврата)
   - Description (описание)
   - lang (am/ru/en)
   - Opaque (ваш orderId, опционально)
   ↓
3. Ameriabank возвращает:
   - ResponseCode: 1 = успех, иначе ошибка
   - PaymentID: ID платежа в системе Ameriabank
   - ResponseMessage: "OK" при успехе
   ↓
4. Магазин перенаправляет пользователя на:
   https://services.ameriabank.am/VPOS/Payments/Pay?id={PaymentID}&lang={lang}
   ↓
5. Пользователь вводит данные карты на странице Ameriabank
   ↓
6. Ameriabank обрабатывает платеж
   ↓
7. Ameriabank перенаправляет пользователя на BackURL с параметрами:
   - orderID (ID заказа в системе Ameriabank, string)
   - paymentID (ID платежа, string)
   - resposneCode (код ответа: "00" = успех, string) ⚠️ ОПЕЧАТКА в API: "resposneCode" вместо "responseCode"
   - opaque (дополнительные данные, string) - ваш orderId, если передали в Opaque
   ↓
8. Магазин получает параметры и проверяет статус через GetPaymentDetails API
   ↓
9. Магазин обновляет статус заказа и перенаправляет пользователя на страницу успеха/ошибки
```

---

## 🔑 Ключевые API методы Ameriabank

### 1. Инициализация платежа: `InitPayment`

**URL:**
- Тест: `https://servicestest.ameriabank.am/VPOS/api/VPOS/InitPayment`
- Продакшн: `https://services.ameriabank.am/VPOS/api/VPOS/InitPayment`

**Метод:** POST  
**Content-Type:** `application/json; charset=utf-8`

**Параметры запроса (согласно официальной документации):**
```typescript
{
  ClientID: string,        // ID клиента в системе Ameriabank (обязательный)
  Username: string,        // Имя пользователя API (обязательный)
  Password: string,        // Пароль API (обязательный)
  OrderID: number,         // Уникальный номер заказа в вашей системе (integer, обязательный)
  Amount: number,          // Сумма платежа (decimal, обязательный, например: 1000.00)
  Currency: string,        // Код валюты ISO 4217 как строка (опциональный, по умолчанию "051"):
                           // "051" - AMD (по умолчанию)
                           // "978" - EUR
                           // "840" - USD
                           // "643" - RUB
  Description: string,     // Описание транзакции (обязательный)
  BackURL?: string,        // URL возврата (опциональный в документации, но необходим на практике):
                           // "http://localhost:3000/api/payment/ameriabank/callback"
  Opaque?: string,         // Дополнительные данные (опционально, ваш orderId для идентификации)
  CardHolderID?: string,   // ID держателя карты (опционально, для сохранения карты при binding)
  Timeout?: number         // Длительность сессии в секундах (опционально, integer):
                           // Максимум 1200 секунд (20 минут)
                           // По умолчанию: 1200, если не указан
}
```

**Ответ при успехе:**
```json
{
  "ResponseCode": 1,
  "ResponseMessage": "OK",
  "PaymentID": "15C8E0DE-F082-4785-883E-A5FADB093BE2",
  "OrderID": "12345"
}
```

**Ответ при ошибке:**
```json
{
  "ResponseCode": 2,
  "ResponseMessage": "Invalid credentials",
  "PaymentID": null
}
```

**Коды ответа InitPayment (согласно Table 1 официальной документации):**
- `1` - Успех ✅
- `01` - Заказ уже существует (Order already exists)
- `02` - Заказ отклонен из-за ошибок в данных платежа
- `03` - Неверная валюта (Incorrect Currency)
- `04` - Отсутствует обязательный параметр (Required parameter missed)
- `05` - Неверные параметры (Incorrect Parameters)
- `06` - Незарегистрированный OrderId (Unregistered OrderId)
- `07` - Системная ошибка (System Error)
- `20` - Неверные Username и Password (Incorrect Username and Password)
- `30` - Неверное значение поля Opaque (Incorrect Value of Opque field)

**Важно:**
- `ResponseCode = 1` и `ResponseMessage = "OK"` означает успех
- `PaymentID` используется для перенаправления пользователя на страницу оплаты
- URL для оплаты: `https://services.ameriabank.am/VPOS/Payments/Pay?id={PaymentID}&lang={lang}`

---

### 2. Проверка статуса платежа: `GetPaymentDetails`

**URL:**
- Тест: `https://servicestest.ameriabank.am/VPOS/api/VPOS/GetPaymentDetails`
- Продакшн: `https://services.ameriabank.am/VPOS/api/VPOS/GetPaymentDetails`

**Метод:** POST  
**Content-Type:** `application/json; charset=utf-8`

**Параметры запроса:**
```typescript
{
  Username: string,        // Имя пользователя API
  Password: string,        // Пароль API
  paymentID: string        // ID платежа (из InitPayment или BackURL)
}
```

**Ответ при успехе (согласно официальной документации):**
```json
{
  "ResponseCode": "00",
  "ResponseMessage": "OK",
  "PaymentState": "payment_deposited",
  "OrderStatus": 2,
  "PaymentID": "15C8E0DE-F082-4785-883E-A5FADB093BE2",
  "OrderID": 12345,
  "Amount": 1000.00,
  "ApprovedAmount": 1000.00,
  "DepositedAmount": 1000.00,
  "RefundedAmount": 0.00,
  "Currency": "051",
  "CardNumber": "411111****1111",
  "ExpDate": "2512",
  "ClientName": "IVAN IVANOV",
  "ClientEmail": "client@example.com",
  "DateTime": "2026-01-27T12:00:00",
  "ApprovalCode": "123456",
  "rrn": "unique_transaction_code",
  "MDOrderID": "arca_order_id",
  "MerchantId": "merchant_id",
  "TerminalId": "terminal_id",
  "Description": "Order #12345",
  "Opaque": "your_order_id",
  "PaymentType": 5,
  "PrimaryRC": "00",
  "ProcessingIP": "192.168.1.1",
  "TrxnDescription": "Transaction description",
  "ActionCode": "00",
  "ExchangeRate": 1.0
}
```

**Важно:**
- `ResponseCode = "00"` означает успешный запрос
- `PaymentState = "payment_deposited"` означает успешный платеж
- `OrderStatus = 2` означает успешный платеж (payment_deposited)
- Можно проверять как `PaymentState`, так и `OrderStatus` для надежности

**Ответ при ошибке:**
```json
{
  "ResponseCode": "01",
  "ResponseMessage": "Payment not found",
  "PaymentState": null
}
```

**Коды ответа GetPaymentDetails (согласно Table 1 официальной документации):**
- `"00"` - Успех (Approved. Payment successfully completed) ✅
- `"01"` - Заказ уже существует (Order already exists)
- `"02"` - Заказ отклонен из-за ошибок в данных платежа
- `"03"` - Неверная валюта (Incorrect Currency)
- `"04"` - Отсутствует обязательный параметр (Required parameter missed)
- `"05"` - Неверные параметры (Incorrect Parameters)
- `"06"` - Незарегистрированный OrderId (Unregistered OrderId)
- `"07"` - Системная ошибка (System Error)
- `"20"` - Неверные Username и Password (Incorrect Username and Password)
- `"30"` - Неверное значение поля Opaque (Incorrect Value of Opque field)
- И другие коды ошибок (см. Table 1 в официальной документации)

**Статусы платежа (PaymentState):**
Согласно официальной документации (Table 2):
- `"payment_started"` (OrderStatus: 0) - Заказ зарегистрирован, но не оплачен
- `"payment_approved"` (OrderStatus: 1) - Сумма предварительно авторизована (двухстадийный платеж)
- `"payment_deposited"` (OrderStatus: 2) - Сумма успешно авторизована ✅
- `"payment_void"` (OrderStatus: 3) - Авторизация отменена
- `"payment_refunded"` (OrderStatus: 4) - Сумма возвращена
- `"payment_autoauthorized"` (OrderStatus: 5) - Авторизация через ACS банка-эмитента
- `"payment_declined"` (OrderStatus: 6) - Авторизация отклонена

**Важно:**
- `ResponseCode = "00"` означает успешный запрос
- `PaymentState = "payment_deposited"` означает успешный платеж ✅
- В некоторых реализациях может использоваться `"Successful"` вместо `"payment_deposited"` - проверяйте оба варианта
- Всегда проверяйте статус через API, не доверяйте только URL параметрам!

---

### 3. Дополнительные методы (опционально)

#### Отмена платежа: `CancelPayment`
Для отмены платежа в течение 72 часов после оплаты.

**Параметры:**
```typescript
{
  Username: string,
  Password: string,
  PaymentID: string
}
```

#### Возврат средств: `RefundPayment`
Для возврата средств после списания.

**Параметры:**
```typescript
{
  Username: string,
  Password: string,
  PaymentID: string,
  Amount: number  // Сумма возврата
}
```

#### Подтверждение двухстадийного платежа: `ConfirmPayment`
Для завершения двухстадийного платежа (если используется).

**Параметры:**
```typescript
{
  Username: string,
  Password: string,
  PaymentID: string,
  Amount: number
}
```

---

## 💻 Реализация для Next.js

### Структура файлов

```
src/
  app/
    api/
      payment/
        ameriabank/
          init/
            route.ts          # Инициализация платежа (InitPayment)
          callback/
            route.ts          # Обработка возврата пользователя (BackURL)
          status/
            route.ts          # Проверка статуса (GetPaymentDetails, опционально)
    payment/
      success/
        page.tsx              # Страница успеха
      failed/
        page.tsx              # Страница ошибки
    checkout/
      page.tsx                # Страница оформления заказа (добавить Ameriabank)
```

---

### 1. API Route: Инициализация платежа (`/api/payment/ameriabank/init`)

```typescript
// src/app/api/payment/ameriabank/init/route.ts
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
    const isTestMode = process.env.AMERIABANK_TEST_MODE === 'true';
    const apiUrl = isTestMode
      ? 'https://servicestest.ameriabank.am/VPOS/api/VPOS/InitPayment'
      : 'https://services.ameriabank.am/VPOS/api/VPOS/InitPayment';
    
    const clientId = isTestMode
      ? process.env.AMERIABANK_TEST_CLIENT_ID!
      : process.env.AMERIABANK_CLIENT_ID!;
    
    const username = isTestMode
      ? process.env.AMERIABANK_TEST_USERNAME!
      : process.env.AMERIABANK_USERNAME!;
    
    const password = isTestMode
      ? process.env.AMERIABANK_TEST_PASSWORD!
      : process.env.AMERIABANK_PASSWORD!;
    
    // Конвертация валюты в код ISO 4217 (числовой код как строка)
    const currencyMap: Record<string, string> = {
      'AMD': '051',  // Армянский драм (по умолчанию)
      'EUR': '978',  // Евро
      'USD': '840',  // Доллар США
      'RUB': '643'   // Российский рубль
    };
    
    const currencyCode = currencyMap[order.currency || 'AMD'] || '051';
    
    if (!currencyMap[order.currency || 'AMD']) {
      return NextResponse.json(
        { error: `Currency ${order.currency} is not supported. Supported: ${Object.keys(currencyMap).join(', ')}` },
        { status: 400 }
      );
    }
    
    // Base URL для BackURL
    const baseUrl = process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000';
    const backUrl = `${baseUrl}/api/payment/ameriabank/callback?ourOrderId=${orderId}`;
    
    // Язык (конвертируем hy в am)
    const langMap: Record<string, string> = {
      'hy': 'am',
      'ru': 'ru',
      'en': 'en'
    };
    const lang = langMap[process.env.NEXT_PUBLIC_LANGUAGE || 'ru'] || 'ru';
    
    // Параметры запроса
    const requestBody = {
      ClientID: clientId,
      Username: username,
      Password: password,
      OrderID: parseInt(orderId) || orderId, // OrderID должен быть integer
      Amount: order.total,
      Currency: currencyCode, // Используем числовой код ISO 4217 как строку ("051", "978", "840", "643")
      Description: `Order #${orderId}`, // Description обязательный (согласно документации)
      BackURL: backUrl, // Рекомендуется указать для возврата пользователя (опциональный в документации, но необходим на практике)
      Opaque: orderId,  // Передаем наш orderId для идентификации (опциональный)
      Timeout: 1200     // Длительность сессии в секундах (опциональный, максимум 1200, по умолчанию 1200 = 20 минут)
    };
    
    // Отправка запроса
    const response = await fetch(apiUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json; charset=utf-8'
      },
      body: JSON.stringify(requestBody)
    });
    
    if (!response.ok) {
      console.error('Ameriabank InitPayment HTTP error:', response.status);
      return NextResponse.json(
        { error: 'Payment initialization failed' },
        { status: 500 }
      );
    }
    
    const data = await response.json();
    
    // Проверяем ответ
    if (data.ResponseCode === 1 && data.ResponseMessage === 'OK') {
      // Сохраняем PaymentID в заказе
      await prisma.order.update({
        where: { id: orderId },
        data: {
          paymentId: data.PaymentID,
          paymentStatus: 'PENDING'
        }
      });
      
      // Формируем URL для перенаправления на страницу оплаты
      const paymentBaseUrl = isTestMode
        ? 'https://servicestest.ameriabank.am/VPOS'
        : 'https://services.ameriabank.am/VPOS';
      
      const paymentUrl = `${paymentBaseUrl}/Payments/Pay?id=${data.PaymentID}&lang=${lang}`;
      
      return NextResponse.json({
        success: true,
        paymentId: data.PaymentID,
        paymentUrl: paymentUrl
      });
    } else {
      // Ошибка инициализации
      console.error('Ameriabank InitPayment error:', data);
      
      await prisma.order.update({
        where: { id: orderId },
        data: {
          paymentStatus: 'FAILED'
        }
      });
      
      return NextResponse.json({
        success: false,
        error: data.ResponseMessage || `Error code: ${data.ResponseCode}`
      }, { status: 400 });
    }
    
  } catch (error) {
    console.error('Ameriabank init error:', error);
    return NextResponse.json(
      { error: 'Internal server error' },
      { status: 500 }
    );
  }
}
```

---

### 2. API Route: Callback обработка (`/api/payment/ameriabank/callback`)

```typescript
// src/app/api/payment/ameriabank/callback/route.ts
import { NextRequest, NextResponse } from 'next/server';
import { prisma } from '@/lib/prisma';
import { redirect } from 'next/navigation';

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    
    // ⚠️ ВАЖНО: В API Ameriabank опечатка - "resposneCode" вместо "responseCode"
    // ⚠️ ВАЖНО: Регистр параметров в BackURL (согласно официальной документации)
    const orderID = searchParams.get('orderID'); // ID заказа в системе Ameriabank (lowercase)
    const paymentID = searchParams.get('paymentID'); // ID платежа (lowercase)
    const responseCode = searchParams.get('resposneCode'); // Код ответа (опечатка в API: "resposneCode" вместо "responseCode")
    const opaque = searchParams.get('opaque'); // Дополнительные данные (lowercase, наш orderId)
    
    // Используем opaque (наш orderId) для идентификации, если передали
    // Если opaque не передан, используем orderID (но это ID от Ameriabank, не наш)
    const ourOrderId = opaque || orderID;
    
    if (!paymentID) {
      console.error('Ameriabank callback: missing paymentID');
      return redirect('/payment/failed?error=no_payment_id');
    }
    
    // Первая проверка: если responseCode не "00", платеж не прошел
    if (responseCode !== '00') {
      console.error('Ameriabank callback: responseCode is not 00:', responseCode);
      
      if (ourOrderId) {
        await prisma.order.update({
          where: { id: ourOrderId },
          data: {
            paymentStatus: 'FAILED',
            paymentId: paymentID
          }
        });
      }
      
      return redirect(`/payment/failed?orderId=${ourOrderId || ''}&error=payment_failed`);
    }
    
    // ВАЖНО: Проверяем статус через API (не доверяем только URL параметрам!)
    const isTestMode = process.env.AMERIABANK_TEST_MODE === 'true';
    const apiUrl = isTestMode
      ? 'https://servicestest.ameriabank.am/VPOS/api/VPOS/GetPaymentDetails'
      : 'https://services.ameriabank.am/VPOS/api/VPOS/GetPaymentDetails';
    
    const username = isTestMode
      ? process.env.AMERIABANK_TEST_USERNAME!
      : process.env.AMERIABANK_USERNAME!;
    
    const password = isTestMode
      ? process.env.AMERIABANK_TEST_PASSWORD!
      : process.env.AMERIABANK_PASSWORD!;
    
    const response = await fetch(apiUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json; charset=utf-8'
      },
      body: JSON.stringify({
        Username: username,
        Password: password,
        paymentID: paymentID
      })
    });
    
    if (!response.ok) {
      console.error('Ameriabank GetPaymentDetails HTTP error:', response.status);
      return redirect(`/payment/failed?orderId=${ourOrderId || ''}&error=api_error`);
    }
    
    const data = await response.json();
    
    // Проверяем статус платежа
    // Согласно официальной документации: PaymentState = "payment_deposited" означает успех
    // В некоторых реализациях может использоваться "Successful" - проверяем оба варианта
    const isPaymentSuccessful = data.ResponseCode === '00' && 
      (data.PaymentState === 'payment_deposited' || 
       data.PaymentState === 'Successful' ||
       data.OrderStatus === 2); // OrderStatus: 2 = payment_deposited
    
    if (isPaymentSuccessful) {
      // Платеж успешен
      if (ourOrderId) {
        // Проверяем, не был ли заказ уже оплачен (идемпотентность)
        const order = await prisma.order.findUnique({
          where: { id: ourOrderId }
        });
        
        if (order && order.paymentStatus !== 'PAID') {
          await prisma.order.update({
            where: { id: ourOrderId },
            data: {
              paymentStatus: 'PAID',
              paymentId: paymentID,
              status: 'CONFIRMED',
              // Сохраняем дополнительную информацию из ответа (все поля согласно документации)
              paymentData: {
                rrn: data.rrn, // Уникальный код транзакции
                approvalCode: data.ApprovalCode, // Код авторизации
                cardNumber: data.CardNumber,
                expDate: data.ExpDate,
                clientName: data.ClientName,
                clientEmail: data.ClientEmail,
                approvedAmount: data.ApprovedAmount,
                depositedAmount: data.DepositedAmount,
                refundedAmount: data.RefundedAmount || 0,
                currency: data.Currency,
                dateTime: data.DateTime,
                orderStatus: data.OrderStatus, // Integer: 2 = payment_deposited
                paymentState: data.PaymentState, // String: "payment_deposited"
                mdOrderID: data.MDOrderID,
                merchantId: data.MerchantId,
                terminalId: data.TerminalId,
                paymentType: data.PaymentType,
                primaryRC: data.PrimaryRC,
                processingIP: data.ProcessingIP,
                trxnDescription: data.TrxnDescription,
                actionCode: data.ActionCode,
                exchangeRate: data.ExchangeRate
              }
            }
          });
        }
      }
      
      return redirect(`/payment/success?orderId=${ourOrderId || ''}`);
    } else {
      // Платеж не прошел
      console.error('Ameriabank GetPaymentDetails: payment not successful', {
        ResponseCode: data.ResponseCode,
        PaymentState: data.PaymentState,
        ResponseMessage: data.ResponseMessage
      });
      
      if (ourOrderId) {
        await prisma.order.update({
          where: { id: ourOrderId },
          data: {
            paymentStatus: 'FAILED',
            paymentId: paymentID
          }
        });
      }
      
      return redirect(`/payment/failed?orderId=${ourOrderId || ''}&error=${data.ResponseMessage || 'Payment failed'}`);
    }
    
  } catch (error) {
    console.error('Ameriabank callback error:', error);
    return redirect('/payment/failed?error=internal_error');
  }
}
```

---

### 3. Обновление страницы Checkout

```typescript
// src/app/checkout/page.tsx
// Добавить метод оплаты Ameriabank

const handleAmeriabankPayment = async () => {
  try {
    // Создаем заказ
    const orderResponse = await fetch('/api/orders', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ...formData,
        paymentMethod: 'ameriabank',
        currency: formData.currency || 'AMD' // В БД храним буквенный код, конвертируем в "051" для API
      })
    });
    
    const order = await orderResponse.json();
    
    // Инициализируем платеж
    const paymentResponse = await fetch('/api/payment/ameriabank/init', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ orderId: order.id })
    });
    
    const { paymentUrl } = await paymentResponse.json();
    
    // Перенаправляем на страницу оплаты Ameriabank
    window.location.href = paymentUrl;
    
  } catch (error) {
    console.error('Payment error:', error);
    alert('Ошибка при инициализации платежа');
  }
};
```

---

### 4. Страница успеха (`/payment/success`)

```typescript
// src/app/payment/success/page.tsx
'use client';

import { useSearchParams } from 'next/navigation';
import Link from 'next/link';

export default function PaymentSuccessPage() {
  const searchParams = useSearchParams();
  const orderId = searchParams.get('orderId');
  
  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
        <div className="mb-6">
          <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100">
            <svg
              className="h-8 w-8 text-green-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M5 13l4 4L19 7"
              />
            </svg>
          </div>
        </div>
        
        <h1 className="text-2xl font-bold text-gray-900 mb-4">
          Оплата успешна!
        </h1>
        
        {orderId && (
          <p className="text-gray-600 mb-6">
            Заказ #{orderId} успешно оплачен через Ameriabank
          </p>
        )}
        
        <div className="space-y-4">
          <Link
            href="/orders"
            className="block w-full bg-orange-500 text-white py-3 px-4 rounded-lg hover:bg-orange-600 transition"
          >
            Посмотреть заказы
          </Link>
          
          <Link
            href="/"
            className="block w-full bg-gray-200 text-gray-800 py-3 px-4 rounded-lg hover:bg-gray-300 transition"
          >
            Вернуться на главную
          </Link>
        </div>
      </div>
    </div>
  );
}
```

---

### 5. Страница ошибки (`/payment/failed`)

```typescript
// src/app/payment/failed/page.tsx
'use client';

import { useSearchParams } from 'next/navigation';
import Link from 'next/link';

export default function PaymentFailedPage() {
  const searchParams = useSearchParams();
  const orderId = searchParams.get('orderId');
  const error = searchParams.get('error');
  
  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
        <div className="mb-6">
          <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100">
            <svg
              className="h-8 w-8 text-red-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </div>
        </div>
        
        <h1 className="text-2xl font-bold text-gray-900 mb-4">
          Оплата не прошла
        </h1>
        
        {orderId && (
          <p className="text-gray-600 mb-2">
            К сожалению, оплата заказа #{orderId} не была завершена
          </p>
        )}
        
        {error && (
          <p className="text-gray-500 text-sm mb-6">
            Причина: {error}
          </p>
        )}
        
        <p className="text-gray-500 mb-6">
          Пожалуйста, попробуйте еще раз или выберите другой способ оплаты
        </p>
        
        <div className="space-y-4">
          <Link
            href="/checkout"
            className="block w-full bg-orange-500 text-white py-3 px-4 rounded-lg hover:bg-orange-600 transition"
          >
            Попробовать снова
          </Link>
          
          <Link
            href="/"
            className="block w-full bg-gray-200 text-gray-800 py-3 px-4 rounded-lg hover:bg-gray-300 transition"
          >
            Вернуться на главную
          </Link>
        </div>
      </div>
    </div>
  );
}
```

---

## 🔐 Переменные окружения

Добавить в `.env.local`:

```bash
# Ameriabank Payment Gateway
AMERIABANK_TEST_MODE=true
AMERIABANK_TEST_CLIENT_ID=your_test_client_id
AMERIABANK_TEST_USERNAME=your_test_username
AMERIABANK_TEST_PASSWORD=your_test_password
AMERIABANK_CLIENT_ID=your_production_client_id
AMERIABANK_USERNAME=your_production_username
AMERIABANK_PASSWORD=your_production_password

# Base URL для BackURL
NEXT_PUBLIC_BASE_URL=http://localhost:3000
NEXT_PUBLIC_LANGUAGE=ru  # ru, en, hy (hy конвертируется в am)
```

---

## ⚠️ Важные замечания

### Безопасность

1. **Всегда проверяйте статус через API!**
   - Не доверяйте только URL параметрам
   - Используйте `GetPaymentDetails` для получения реального статуса
   - Параметры в URL могут быть подделаны

2. **Идемпотентность:**
   - Пользователь может вернуться несколько раз
   - Проверяйте, не был ли заказ уже оплачен
   - Обрабатывайте повторные запросы корректно

3. **Логирование:**
   - Логируйте все запросы для отладки
   - Сохраняйте ответы от API для аудита

### Локальное тестирование

- ✅ **Можно использовать `http://localhost:3000`** для BackURL
- ✅ Ameriabank не требует регистрации URL заранее
- ✅ Пользователь возвращается через браузер, поэтому localhost работает

### Валюты

Поддерживаемые валюты (согласно официальной документации):
- `"051"` - AMD (Армянский драм) - по умолчанию
- `"978"` - EUR (Евро)
- `"840"` - USD (Доллар США)
- `"643"` - RUB (Российский рубль)

**Важно:** 
- Передавайте валюту как **числовой код ISO 4217 в виде строки** (например, `"051"` для AMD)
- НЕ используйте буквенные коды типа "AMD", "USD" - только числовые!
- Тип данных: string (но значение - числовой код)

### Языки

- `"am"` - Հայերեն (армянский)
- `"ru"` - Русский
- `"en"` - English

**Важно:** Если у вас язык "hy", конвертируйте его в "am" перед отправкой!

### Опечатка в API

⚠️ **В API Ameriabank есть опечатка:**
- Параметр в BackURL называется `resposneCode` (с опечаткой)
- НЕ `responseCode` (правильное написание)
- Учитывайте это при обработке callback'а!

### ResponseCode различия

**InitPayment:**
- `ResponseCode: 1` (integer) = успех
- `ResponseMessage: "OK"` = успех

**GetPaymentDetails:**
- `ResponseCode: "00"` (string) = успех
- `PaymentState: "payment_deposited"` = успешный платеж (официально)
- `PaymentState: "Successful"` = может использоваться в некоторых реализациях

**Важно:** 
- Обратите внимание на разные форматы (integer vs string)!
- Проверяйте оба варианта PaymentState: "payment_deposited" и "Successful"

---

## ✅ Чек-лист реализации

### Этап 1: Базовая интеграция
- [ ] Создать API route `/api/payment/ameriabank/init`
- [ ] Создать API route `/api/payment/ameriabank/callback`
- [ ] Добавить переменные окружения
- [ ] Обновить страницу checkout (добавить Ameriabank)
- [ ] Обновить типы (добавить 'ameriabank' в PaymentMethod)

### Этап 2: Обработка статусов
- [ ] Реализовать проверку через GetPaymentDetails
- [ ] Обработать опечатку "resposneCode"
- [ ] Добавить идемпотентность (проверка повторных запросов)
- [ ] Создать страницу `/payment/success`
- [ ] Создать страницу `/payment/failed`
- [ ] Логирование платежей

### Этап 3: Тестирование
- [ ] Получить тестовые credentials от Ameriabank
- [ ] Настроить переменные окружения для теста
- [ ] Тестовая инициализация платежа
- [ ] Тестовая проверка статуса
- [ ] Тестовая оплата с тестовой картой
- [ ] Проверка обработки ошибок

### Этап 4: Продакшн
- [ ] Получить продакшн credentials от Ameriabank
- [ ] Обновить переменные окружения
- [ ] Переключить AMERIABANK_TEST_MODE=false
- [ ] Тестовая оплата реальной картой

---

## 🔄 Сравнение с другими платежными системами

| Параметр | Idram | Ameriabank | IDBank/Arca |
|----------|-------|------------|-------------|
| **Callback'и** | Сервер-сервер (2 POST) | Только возврат пользователя | Только возврат пользователя |
| **Проверка статуса** | Через callback'и | Через API (GetPaymentDetails) | Через API (getOrderStatusExtended) |
| **Подпись** | MD5 checksum | Нет (только API) | Нет (только API) |
| **Локальное тестирование** | Нужен ngrok | Работает localhost | Работает localhost |
| **Регистрация URL** | Нужна | Не нужна | Не нужна |
| **Валюты** | Только AMD | AMD, USD, EUR, RUB (коды: 051, 840, 978, 643) | AMD, USD, EUR, RUB |
| **API формат** | HTML форма | REST JSON | REST form-data |

---

## 📚 Полезные ссылки

- **Официальная документация vPOS 3.1:** `example-Vpos/Documentation/AmeriaBank/vPOS - Ameriabank.md` ⭐ (основной источник)
- Callback Guide: `example-Vpos/Documentation/AmeriaBank/AmeriaBank/AMERIABANK_ARCA_CALLBACK_AND_DOMAINS.md`
- Payment Integration Guide: `example-Vpos/Documentation/AmeriaBank/AmeriaBank/PAYMENT_INTEGRATION_GUIDE.md`
- Testing Guide: `example-Vpos/Documentation/AmeriaBank/AmeriaBank/TESTING_GUIDE.md`
- Пример плагина PlanetStudio: `example-Vpos/PlanetStudio Agency/arca-payment-gateway-test/endpoints/apg-ameria-bank.php`
- Пример плагина HK Agency: `example-Vpos/HK Agency/payment-gateway-for-ameriabank/`

---

## 🎯 Следующие шаги

1. **Получить credentials от Ameriabank:**
   - Тестовые: ClientID, Username, Password
   - Продакшн: ClientID, Username, Password

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

## 🔍 Дополнительные детали

### Формат суммы

- Сумма передается как число с десятичными знаками
- Пример: `1000.00` для 1000 драм
- Не нужно умножать на 100 (в отличие от Arca)

### Формат валюты

- Используются **числовые коды ISO 4217 в виде строки**: `"051"` (AMD), `"978"` (EUR), `"840"` (USD), `"643"` (RUB)
- НЕ буквенные коды типа "AMD", "USD"!
- Тип данных: string, но значение - числовой код ISO 4217

### URL для оплаты

- Тест: `https://servicestest.ameriabank.am/VPOS/Payments/Pay?id={PaymentID}&lang={lang}`
- Продакшн: `https://services.ameriabank.am/VPOS/Payments/Pay?id={PaymentID}&lang={lang}`

### Обработка ошибок

Всегда обрабатывайте:
- Сетевые ошибки
- Неверные ResponseCode
- Таймауты
- Повторные callback'и

---

**Готов к реализации!** 🚀
