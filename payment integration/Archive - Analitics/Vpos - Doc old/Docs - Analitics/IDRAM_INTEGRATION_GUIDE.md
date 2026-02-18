# Полное руководство по интеграции Idram в Next.js интернет-магазин

## 📋 Краткое резюме

**Idram** - это платежная система Армении, которая использует **сервер-сервер callback'и** для подтверждения платежей. В отличие от IDBank/Arca, Idram требует **3 URL** и отправляет **2 POST запроса** на ваш сервер для каждого платежа.

---

## 🔍 Что я изучил

### 1. Документация
- ✅ **Idram Merchant API** - полная документация API
- ✅ **Idram Callback Guide** - объяснение callback'ов и доменов
- ✅ **WordPress плагины** от двух разработчиков:
  - PlanetStudio Agency (`arca-payment-gateway`)
  - HK Agency (`hk-idram-payment-gateway`)

### 2. Ключевые отличия от IDBank/Arca

| Параметр | Idram | IDBank/Arca |
|----------|-------|-------------|
| **Callback URL** | 3 URL (RESULT_URL, SUCCESS_URL, FAIL_URL) | 1 URL (returnUrl) |
| **Сервер-сервер callback'и** | ✅ Да (2 POST запроса) | ❌ Нет |
| **Регистрация URL** | ✅ Нужна (у Idram) | ❌ Не нужна |
| **Локальный домен** | ❌ Не работает (для callback'ов) | ✅ Работает |
| **Проверка статуса** | Через callback'и | Через API запрос |
| **Подпись** | MD5 checksum | Нет (только API) |
| **Валюты** | Только AMD | AMD, USD, EUR, RUB |

---

## 🏗️ Архитектура работы Idram

### Схема платежа

```
1. Пользователь нажимает "Оплатить" в интернет-магазине
   ↓
2. Магазин создает HTML форму с параметрами:
   - EDP_REC_ACCOUNT (IdramID мерчанта)
   - EDP_AMOUNT (сумма)
   - EDP_BILL_NO (номер заказа)
   - EDP_DESCRIPTION (описание)
   - EDP_LANGUAGE (RU/EN/AM)
   ↓
3. Форма отправляется на https://banking.idram.am/Payment/GetPayment
   ↓
4. Пользователь авторизуется в Idram и подтверждает платеж
   ↓
5. Idram отправляет ПЕРВЫЙ POST на RESULT_URL (EDP_PRECHECK=YES)
   ↓
6. Ваш сервер проверяет заказ и отвечает "OK"
   ↓
7. Idram переводит деньги
   ↓
8. Idram отправляет ВТОРОЙ POST на RESULT_URL (с данными платежа + EDP_CHECKSUM)
   ↓
9. Ваш сервер проверяет подпись (MD5) и обновляет статус заказа
   ↓
10. Ваш сервер отвечает "OK"
   ↓
11. Idram перенаправляет пользователя на SUCCESS_URL или FAIL_URL
```

---

## 🔑 Ключевые параметры Idram

### Параметры, которые вы получаете от Idram:

1. **EDP_REC_ACCOUNT** - IdramID мерчанта (выдается Idram)
2. **SECRET_KEY** - Секретный ключ (выдается Idram)
3. **EMAIL** - Email для уведомлений (выдается Idram)
4. **RESULT_URL** - URL для callback'ов (вы регистрируете у Idram)
5. **SUCCESS_URL** - URL успешной оплаты (вы регистрируете у Idram)
6. **FAIL_URL** - URL неудачной оплаты (вы регистрируете у Idram)

---

## 📝 API методы Idram

### 1. Инициализация платежа (HTML форма)

**URL:** `https://banking.idram.am/Payment/GetPayment`

**Метод:** POST (HTML форма)

**Параметры формы:**
```typescript
{
  EDP_LANGUAGE: string,      // "RU" | "EN" | "AM"
  EDP_REC_ACCOUNT: string,   // IdramID мерчанта
  EDP_DESCRIPTION: string,   // Описание заказа
  EDP_AMOUNT: string,       // Сумма (формат: "1900.00")
  EDP_BILL_NO: string,      // Номер заказа в вашей системе
  EDP_EMAIL?: string,        // Email (опционально, переопределяет EMAIL)
  // Дополнительные поля (без префикса EDP_) передаются обратно в callback'ах
}
```

**Пример HTML формы:**
```html
<form action="https://banking.idram.am/Payment/GetPayment" method="POST">
  <input type="hidden" name="EDP_LANGUAGE" value="RU">
  <input type="hidden" name="EDP_REC_ACCOUNT" value="100000114">
  <input type="hidden" name="EDP_DESCRIPTION" value="Order #123">
  <input type="hidden" name="EDP_AMOUNT" value="1900.00">
  <input type="hidden" name="EDP_BILL_NO" value="123">
  <input type="submit" value="Оплатить">
</form>
```

---

### 2. Callback: Предварительная проверка (EDP_PRECHECK)

**URL:** Ваш RESULT_URL (например: `/api/payment/idram/callback`)

**Метод:** POST (Content-Type: application/x-www-form-urlencoded)

**Параметры запроса:**
```typescript
{
  EDP_PRECHECK: "YES",       // Признак предварительной проверки
  EDP_BILL_NO: string,       // Номер заказа
  EDP_REC_ACCOUNT: string,   // IdramID мерчанта
  EDP_AMOUNT: string         // Сумма платежа
}
```

**Что делать:**
1. Проверить, что заказ существует в вашей системе
2. Проверить, что сумма совпадает
3. Проверить, что EDP_REC_ACCOUNT совпадает с вашим IdramID
4. Вернуть **"OK"** (без HTML, без пробелов) с HTTP 200

**Если не вернуть "OK":**
- Idram не позволит пользователю оплатить
- Пользователь будет перенаправлен на FAIL_URL

---

### 3. Callback: Подтверждение платежа

**URL:** Ваш RESULT_URL (например: `/api/payment/idram/callback`)

**Метод:** POST (Content-Type: application/x-www-form-urlencoded)

**Параметры запроса:**
```typescript
{
  EDP_BILL_NO: string,           // Номер заказа
  EDP_REC_ACCOUNT: string,       // IdramID мерчанта
  EDP_PAYER_ACCOUNT: string,     // IdramID плательщика
  EDP_AMOUNT: string,            // Сумма (формат: "1900.00")
  EDP_TRANS_ID: string,          // ID транзакции в системе Idram (14 символов)
  EDP_TRANS_DATE: string,        // Дата транзакции (формат: "dd/mm/yyyy")
  EDP_CHECKSUM: string,          // MD5 подпись (проверка!)
  // Дополнительные поля, которые вы передали в форме
}
```

**Проверка подписи (EDP_CHECKSUM):**

Подпись вычисляется как MD5 хеш от строки:
```
EDP_REC_ACCOUNT:EDP_AMOUNT:SECRET_KEY:EDP_BILL_NO:EDP_PAYER_ACCOUNT:EDP_TRANS_ID:EDP_TRANS_DATE
```

**Пример:**
```typescript
const txtToHash = `${EDP_REC_ACCOUNT}:${EDP_AMOUNT}:${SECRET_KEY}:${EDP_BILL_NO}:${EDP_PAYER_ACCOUNT}:${EDP_TRANS_ID}:${EDP_TRANS_DATE}`;
const calculatedChecksum = md5(txtToHash).toUpperCase();
const isValid = calculatedChecksum === EDP_CHECKSUM.toUpperCase();
```

**Что делать:**
1. Проверить подпись (EDP_CHECKSUM)
2. Проверить, что заказ существует
3. Проверить, что сумма совпадает
4. Обновить статус заказа на "оплачен"
5. Вернуть **"OK"** (без HTML, без пробелов) с HTTP 200

**Важно:**
- Если HTTP код не 200, Idram отменит транзакцию
- Если ответ не "OK", Idram отменит транзакцию
- Idram может отправить callback несколько раз (идемпотентность!)

---

## 💻 Реализация для Next.js

### Структура файлов

```
src/
  app/
    api/
      payment/
        idram/
          init/
            route.ts          # Создание формы для перенаправления
          callback/
            route.ts          # RESULT_URL - обработка callback'ов
    payment/
      success/
        page.tsx              # SUCCESS_URL - страница успеха
      failed/
        page.tsx              # FAIL_URL - страница ошибки
    checkout/
      page.tsx                # Страница оформления заказа (добавить Idram)
```

---

### 1. API Route: Инициализация платежа (`/api/payment/idram/init`)

```typescript
// src/app/api/payment/idram/init/route.ts
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
    const isTestMode = process.env.IDRAM_TEST_MODE === 'true';
    const edpRecAccount = isTestMode
      ? process.env.IDRAM_TEST_REC_ACCOUNT!
      : process.env.IDRAM_REC_ACCOUNT!;
    
    // Idram принимает только AMD
    if (order.currency !== 'AMD') {
      return NextResponse.json(
        { error: 'Idram accepts only AMD currency' },
        { status: 400 }
      );
    }
    
    // Сумма в формате "1900.00" (с точкой как разделителем)
    const amount = order.total.toFixed(2);
    
    // Параметры формы
    const formData = {
      EDP_LANGUAGE: 'RU', // или 'EN', 'AM'
      EDP_REC_ACCOUNT: edpRecAccount,
      EDP_DESCRIPTION: `Order #${orderId}`,
      EDP_AMOUNT: amount,
      EDP_BILL_NO: orderId,
      // Дополнительные поля передаются обратно в callback'ах
      orderId: orderId,
      userId: order.userId || ''
    };
    
    // Возвращаем данные для создания формы на клиенте
    return NextResponse.json({
      formUrl: 'https://banking.idram.am/Payment/GetPayment',
      formData: formData
    });
    
  } catch (error) {
    console.error('Idram init error:', error);
    return NextResponse.json(
      { error: 'Internal server error' },
      { status: 500 }
    );
  }
}
```

---

### 2. API Route: Callback обработка (`/api/payment/idram/callback`)

```typescript
// src/app/api/payment/idram/callback/route.ts
import { NextRequest, NextResponse } from 'next/server';
import { prisma } from '@/lib/prisma';
import crypto from 'crypto';

// Функция для вычисления MD5
function md5(text: string): string {
  return crypto.createHash('md5').update(text).digest('hex');
}

export async function POST(request: NextRequest) {
  try {
    const formData = await request.formData();
    
    // Логируем все запросы для отладки
    const requestData = Object.fromEntries(formData);
    console.log('Idram callback received:', requestData);
    
    // ============================================
    // ПЕРВЫЙ ЗАПРОС: Предварительная проверка (EDP_PRECHECK)
    // ============================================
    if (formData.get('EDP_PRECHECK') === 'YES') {
      const billNo = formData.get('EDP_BILL_NO') as string;
      const amount = parseFloat(formData.get('EDP_AMOUNT') as string);
      const recAccount = formData.get('EDP_REC_ACCOUNT') as string;
      
      // Проверяем IdramID
      const isTestMode = process.env.IDRAM_TEST_MODE === 'true';
      const expectedRecAccount = isTestMode
        ? process.env.IDRAM_TEST_REC_ACCOUNT!
        : process.env.IDRAM_REC_ACCOUNT!;
      
      if (recAccount !== expectedRecAccount) {
        console.error('Invalid EDP_REC_ACCOUNT:', recAccount);
        return new Response('Invalid merchant account', { status: 400 });
      }
      
      // Проверяем заказ в БД
      const order = await prisma.order.findUnique({
        where: { id: billNo }
      });
      
      if (!order) {
        console.error('Order not found:', billNo);
        return new Response('Order not found', { status: 400 });
      }
      
      // Проверяем сумму (с учетом возможных округлений)
      const orderAmount = parseFloat(order.total.toFixed(2));
      if (Math.abs(amount - orderAmount) > 0.01) {
        console.error('Amount mismatch:', amount, 'vs', orderAmount);
        return new Response('Amount mismatch', { status: 400 });
      }
      
      // Обновляем статус заказа (опционально)
      await prisma.order.update({
        where: { id: billNo },
        data: {
          paymentStatus: 'PENDING',
          paymentId: 'precheck'
        }
      });
      
      // ВАЖНО: Возвращаем "OK" без HTML, без пробелов
      return new Response('OK', {
        status: 200,
        headers: {
          'Content-Type': 'text/plain; charset=utf-8'
        }
      });
    }
    
    // ============================================
    // ВТОРОЙ ЗАПРОС: Подтверждение платежа
    // ============================================
    if (
      formData.has('EDP_PAYER_ACCOUNT') &&
      formData.has('EDP_CHECKSUM') &&
      formData.has('EDP_TRANS_ID')
    ) {
      const billNo = formData.get('EDP_BILL_NO') as string;
      const recAccount = formData.get('EDP_REC_ACCOUNT') as string;
      const payerAccount = formData.get('EDP_PAYER_ACCOUNT') as string;
      const amount = formData.get('EDP_AMOUNT') as string;
      const transId = formData.get('EDP_TRANS_ID') as string;
      const transDate = formData.get('EDP_TRANS_DATE') as string;
      const checksum = formData.get('EDP_CHECKSUM') as string;
      
      // Проверяем IdramID
      const isTestMode = process.env.IDRAM_TEST_MODE === 'true';
      const expectedRecAccount = isTestMode
        ? process.env.IDRAM_TEST_REC_ACCOUNT!
        : process.env.IDRAM_REC_ACCOUNT!;
      
      if (recAccount !== expectedRecAccount) {
        console.error('Invalid EDP_REC_ACCOUNT:', recAccount);
        return new Response('Invalid merchant account', { status: 400 });
      }
      
      // Получаем заказ из БД
      const order = await prisma.order.findUnique({
        where: { id: billNo }
      });
      
      if (!order) {
        console.error('Order not found:', billNo);
        return new Response('Order not found', { status: 400 });
      }
      
      // Проверяем сумму
      const orderAmount = parseFloat(order.total.toFixed(2));
      const paymentAmount = parseFloat(amount);
      if (Math.abs(paymentAmount - orderAmount) > 0.01) {
        console.error('Amount mismatch:', paymentAmount, 'vs', orderAmount);
        return new Response('Amount mismatch', { status: 400 });
      }
      
      // Проверяем подпись (EDP_CHECKSUM)
      const secretKey = isTestMode
        ? process.env.IDRAM_TEST_SECRET_KEY!
        : process.env.IDRAM_SECRET_KEY!;
      
      // Формируем строку для проверки подписи
      // Используем сумму из заказа, а не из запроса (для безопасности)
      const txtToHash = `${recAccount}:${orderAmount.toFixed(2)}:${secretKey}:${billNo}:${payerAccount}:${transId}:${transDate}`;
      const calculatedChecksum = md5(txtToHash).toUpperCase();
      const receivedChecksum = checksum.toUpperCase();
      
      if (calculatedChecksum !== receivedChecksum) {
        console.error('Invalid checksum:', {
          calculated: calculatedChecksum,
          received: receivedChecksum,
          txtToHash
        });
        
        // Обновляем статус заказа на ошибку
        await prisma.order.update({
          where: { id: billNo },
          data: {
            paymentStatus: 'FAILED',
            paymentId: transId
          }
        });
        
        return new Response('Invalid checksum', { status: 400 });
      }
      
      // Проверяем, не был ли заказ уже оплачен (идемпотентность)
      if (order.paymentStatus === 'PAID') {
        console.log('Order already paid, returning OK:', billNo);
        return new Response('OK', {
          status: 200,
          headers: {
            'Content-Type': 'text/plain; charset=utf-8'
          }
        });
      }
      
      // Обновляем статус заказа на "оплачен"
      await prisma.order.update({
        where: { id: billNo },
        data: {
          paymentStatus: 'PAID',
          paymentId: transId,
          status: 'CONFIRMED',
          // Сохраняем дополнительную информацию
          paymentData: {
            payerAccount,
            transDate,
            amount: paymentAmount
          }
        }
      });
      
      // ВАЖНО: Возвращаем "OK" без HTML, без пробелов
      return new Response('OK', {
        status: 200,
        headers: {
          'Content-Type': 'text/plain; charset=utf-8'
        }
      });
    }
    
    // Неизвестный тип запроса
    console.error('Unknown request type:', requestData);
    return new Response('Invalid request', { status: 400 });
    
  } catch (error) {
    console.error('Idram callback error:', error);
    // В случае ошибки возвращаем ошибку, но Idram может повторить запрос
    return new Response('Internal server error', { status: 500 });
  }
}
```

---

### 3. Обновление страницы Checkout

```typescript
// src/app/checkout/page.tsx
// Добавить метод оплаты Idram

const handleIdramPayment = async () => {
  try {
    // Создаем заказ
    const orderResponse = await fetch('/api/orders', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ...formData,
        paymentMethod: 'idram',
        currency: 'AMD' // Idram принимает только AMD
      })
    });
    
    const order = await orderResponse.json();
    
    // Инициализируем платеж
    const paymentResponse = await fetch('/api/payment/idram/init', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ orderId: order.id })
    });
    
    const { formUrl, formData: idramFormData } = await paymentResponse.json();
    
    // Создаем и отправляем форму
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = formUrl;
    
    Object.entries(idramFormData).forEach(([key, value]) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = key;
      input.value = String(value);
      form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
    
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
  const orderId = searchParams.get('EDP_BILL_NO') || searchParams.get('orderId');
  
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
            Заказ #{orderId} успешно оплачен через Idram
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
  const orderId = searchParams.get('EDP_BILL_NO') || searchParams.get('orderId');
  
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
          <p className="text-gray-600 mb-6">
            К сожалению, оплата заказа #{orderId} не была завершена
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
# Idram Payment Gateway
IDRAM_TEST_MODE=true
IDRAM_TEST_REC_ACCOUNT=your_test_idram_id
IDRAM_TEST_SECRET_KEY=your_test_secret_key
IDRAM_REC_ACCOUNT=your_production_idram_id
IDRAM_SECRET_KEY=your_production_secret_key

# Base URL для callback'ов
NEXT_PUBLIC_BASE_URL=http://localhost:3000

# Callback URL (должны быть зарегистрированы у Idram)
IDRAM_RESULT_URL=https://yourdomain.com/api/payment/idram/callback
IDRAM_SUCCESS_URL=https://yourdomain.com/payment/success
IDRAM_FAIL_URL=https://yourdomain.com/payment/failed
```

---

## ⚠️ Важные замечания

### Безопасность

1. **Всегда проверяйте подпись (EDP_CHECKSUM)!**
   - Используйте сумму из вашей БД, а не из запроса
   - Сравнивайте в верхнем регистре (toUpperCase)

2. **Идемпотентность:**
   - Idram может отправить callback несколько раз
   - Проверяйте, не был ли заказ уже оплачен
   - Возвращайте "OK" даже если заказ уже оплачен

3. **Логирование:**
   - Логируйте все callback'и для отладки
   - Сохраняйте данные платежей для аудита

### Локальное тестирование

- ❌ **localhost НЕ РАБОТАЕТ** для callback'ов (Idram не может достучаться)
- ✅ Используйте **ngrok** для локального тестирования:
  ```bash
  ngrok http 3000
  # Используйте полученный URL для RESULT_URL
  ```
- ✅ Или используйте **Vercel preview** для тестирования

### Регистрация URL у Idram

1. Подготовьте 3 URL:
   - RESULT_URL: `https://yourdomain.com/api/payment/idram/callback`
   - SUCCESS_URL: `https://yourdomain.com/payment/success`
   - FAIL_URL: `https://yourdomain.com/payment/failed`

2. Свяжитесь с техническим персоналом Idram

3. Предоставьте им эти URL для регистрации

4. После регистрации можно начинать тестирование

### Валюты

- **Idram принимает только AMD** (армянские драмы)
- Если заказ в другой валюте, конвертируйте в AMD перед отправкой

### Формат суммы

- Сумма передается в формате `"1900.00"` (с точкой как разделителем)
- Используйте `toFixed(2)` для форматирования

### Ответы на callback'и

- **Должен быть точно "OK"** (без HTML, без пробелов, без переносов строк)
- **HTTP код должен быть 200**
- **Content-Type: text/plain; charset=utf-8**

---

## ✅ Чек-лист реализации

### Этап 1: Базовая интеграция
- [ ] Создать API route `/api/payment/idram/init`
- [ ] Создать API route `/api/payment/idram/callback`
- [ ] Добавить переменные окружения
- [ ] Обновить страницу checkout (добавить Idram)
- [ ] Обновить типы (добавить 'idram' в PaymentMethod)

### Этап 2: Обработка callback'ов
- [ ] Реализовать обработку EDP_PRECHECK
- [ ] Реализовать проверку подписи (EDP_CHECKSUM)
- [ ] Реализовать обработку подтверждения платежа
- [ ] Добавить идемпотентность (проверка повторных callback'ов)
- [ ] Создать страницу `/payment/success`
- [ ] Создать страницу `/payment/failed`

### Этап 3: Регистрация у Idram
- [ ] Подготовить 3 URL (RESULT_URL, SUCCESS_URL, FAIL_URL)
- [ ] Связаться с Idram для регистрации URL
- [ ] Получить тестовые credentials (EDP_REC_ACCOUNT, SECRET_KEY)
- [ ] Настроить переменные окружения

### Этап 4: Тестирование
- [ ] Настроить ngrok для локального тестирования
- [ ] Протестировать EDP_PRECHECK callback
- [ ] Протестировать подтверждение платежа
- [ ] Протестировать проверку подписи
- [ ] Протестировать обработку ошибок

### Этап 5: Продакшн
- [ ] Получить продакшн credentials от Idram
- [ ] Обновить переменные окружения
- [ ] Переключить IDRAM_TEST_MODE=false
- [ ] Зарегистрировать продакшн URL у Idram
- [ ] Протестировать реальной оплатой

---

## 🔄 Сравнение с IDBank/Arca

| Параметр | Idram | IDBank/Arca |
|----------|-------|-------------|
| **Callback'и** | Сервер-сервер (2 POST) | Только возврат пользователя |
| **Проверка статуса** | Через callback'и | Через API запрос |
| **Подпись** | MD5 checksum | Нет (только API) |
| **Локальное тестирование** | Нужен ngrok | Работает localhost |
| **Регистрация URL** | Нужна | Не нужна |
| **Валюты** | Только AMD | AMD, USD, EUR, RUB |

---

## 📚 Полезные ссылки

- Документация Idram: `example-Vpos/Documentation/Idram/IdramMerchantAPI_16102025.md`
- Callback Guide: `example-Vpos/Documentation/AmeriaBank/AmeriaBank/IDRAM_CALLBACK_AND_DOMAINS.md`
- Пример плагина PlanetStudio: `example-Vpos/PlanetStudio Agency/arca-payment-gateway-test/endpoints/apg-idram.php`
- Пример плагина HK Agency: `example-Vpos/HK Agency/hk-idram-payment-gateway/`

---

## 🎯 Следующие шаги

1. **Получить credentials от Idram:**
   - Тестовые: EDP_REC_ACCOUNT, SECRET_KEY
   - Продакшн: EDP_REC_ACCOUNT, SECRET_KEY

2. **Реализовать базовую интеграцию:**
   - API routes
   - Обновить checkout
   - Добавить переменные окружения

3. **Зарегистрировать URL у Idram:**
   - RESULT_URL
   - SUCCESS_URL
   - FAIL_URL

4. **Протестировать:**
   - Локально с ngrok
   - С тестовыми данными

5. **Деплой:**
   - Настроить продакшн credentials
   - Зарегистрировать продакшн URL
   - Протестировать реальной оплатой

---

**Готов к реализации!** 🚀
