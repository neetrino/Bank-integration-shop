# AmeriaBank и Arca: Callback URL и требования к доменам

## Сравнение с Idram

**Главное отличие:** AmeriaBank и Arca **НЕ используют сервер-сервер callback'и** как Idram!

| Система | Количество URL | Тип callback'ов | Проверка статуса |
|---------|---------------|-----------------|------------------|
| **Idram** | 3 URL (RESULT_URL, SUCCESS_URL, FAIL_URL) | Сервер-сервер (POST запросы) | Через callback'и |
| **AmeriaBank** | 1 URL (BackURL) | Только возврат пользователя | Через API запрос |
| **Arca** | 1 URL (returnUrl) | Только возврат пользователя | Через API запрос |

---

## 1. AMERIABANK

### Вопрос 1: Можно ли тестировать с локального/тестового домена?

### Ответ: **ДА, БЕЗ ОГРАНИЧЕНИЙ!**

✅ **Можно использовать:**
- Локальный домен: `http://localhost:3000` (для возврата пользователя)
- Тестовый домен: `https://test.yourdomain.com`
- Временный домен: `https://yourproject.vercel.app`
- ngrok туннель: `https://abc123.ngrok.io`

**Почему это работает:**
- AmeriaBank **НЕ отправляет сервер-сервер callback'и**
- AmeriaBank только **перенаправляет пользователя** на ваш BackURL
- Пользователь возвращается через браузер, поэтому localhost работает!

### Для тестового режима:

✅ **Можно использовать любой домен:**
- Локальный: `http://localhost:3000`
- Тестовый: `https://test.yourdomain.com`
- Временный: `https://yourproject.vercel.app`

### Для продакшн режима:

✅ **Можно использовать любой домен:**
- Постоянный продакшн домен: `https://yourdomain.com`
- Или любой другой доступный домен

**Важно:** AmeriaBank не требует регистрации домена заранее!

---

### Вопрос 2: Какие callback URL нужны AmeriaBank и зачем?

### Ответ: **ТОЛЬКО 1 URL - BackURL**

### BackURL

**Что это:**
- URL, на который возвращается пользователь после оплаты
- Это страница для пользователя, не для сервера
- Вы указываете его в запросе `InitPayment`

**Зачем нужен:**
- Пользователь возвращается на ваш сайт после оплаты
- Вы получаете параметры в URL и проверяете статус через API

**Когда вызывается:**
- После того, как пользователь завершил оплату на странице AmeriaBank
- AmeriaBank перенаправляет пользователя на BackURL с параметрами в URL

**Пример:**
```
BackURL: https://yourdomain.com/api/payment/ameriabank/callback
```

**Параметры, которые приходят в URL:**
- `orderID` - ID заказа в системе AmeriaBank
- `paymentID` - ID платежа
- `resposneCode` - Код ответа ("00" = успех)
- `currency` - Валюта
- `Opaque` - Дополнительные данные (ваш orderId, если передали)

**Реализация в Next.js:**
```typescript
// app/api/payment/ameriabank/callback/route.ts
export async function GET(request: Request) {
  const { searchParams } = new URL(request.url);
  const orderID = searchParams.get('orderID');
  const paymentID = searchParams.get('paymentID');
  const responseCode = searchParams.get('resposneCode');
  const currency = searchParams.get('currency');
  const opaque = searchParams.get('Opaque'); // Наш orderId
  
  if (responseCode !== '00') {
    // Платеж не прошел
    await updateOrderStatus(opaque, 'failed');
    return Response.redirect(`${process.env.NEXT_PUBLIC_BASE_URL}/payment/failed`);
  }
  
  // ВАЖНО: Проверяем статус через API (не доверяем только URL параметрам!)
  const apiUrl = process.env.AMERIABANK_TEST_MODE === 'true'
    ? 'https://servicestest.ameriabank.am/VPOS/api/VPOS/GetPaymentDetails'
    : 'https://services.ameriabank.am/VPOS/api/VPOS/GetPaymentDetails';
  
  const response = await fetch(apiUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json; charset=utf-8'
    },
    body: JSON.stringify({
      Username: process.env.AMERIABANK_USERNAME!,
      Password: process.env.AMERIABANK_PASSWORD!,
      paymentID: paymentID
    })
  });
  
  const data = await response.json();
  
  if (data.ResponseCode === '00' && data.PaymentState === 'Successful') {
    // Платеж успешен
    await updateOrderStatus(opaque, 'paid', {
      paymentId: paymentID,
      transactionId: data.TransactionID,
      cardNumber: data.CardNumber
    });
    
    return Response.redirect(`${process.env.NEXT_PUBLIC_BASE_URL}/payment/success?orderId=${opaque}`);
  } else {
    // Платеж не прошел
    await updateOrderStatus(opaque, 'failed');
    return Response.redirect(`${process.env.NEXT_PUBLIC_BASE_URL}/payment/failed`);
  }
}
```

**Важно:**
- **НЕ доверяйте только URL параметрам!** Всегда проверяйте статус через API
- BackURL может быть любым доступным URL
- Не нужно регистрировать URL заранее у AmeriaBank

---

### Схема работы AmeriaBank

```
1. Пользователь нажимает "Оплатить"
   ↓
2. Вы отправляете запрос InitPayment с BackURL
   ↓
3. Пользователь перенаправляется на страницу оплаты AmeriaBank
   ↓
4. Пользователь вводит данные карты и подтверждает платеж
   ↓
5. AmeriaBank обрабатывает платеж
   ↓
6. AmeriaBank перенаправляет пользователя на BackURL с параметрами
   ↓
7. Ваш сервер получает параметры и проверяет статус через GetPaymentDetails API
   ↓
8. Ваш сервер обновляет статус заказа и перенаправляет пользователя на страницу успеха/ошибки
```

---

## 2. ARCA

### Вопрос 1: Можно ли тестировать с локального/тестового домена?

### Ответ: **ДА, БЕЗ ОГРАНИЧЕНИЙ!**

✅ **Можно использовать:**
- Локальный домен: `http://localhost:3000` (для возврата пользователя)
- Тестовый домен: `https://test.yourdomain.com`
- Временный домен: `https://yourproject.vercel.app`
- ngrok туннель: `https://abc123.ngrok.io`

**Почему это работает:**
- Arca **НЕ отправляет сервер-сервер callback'и**
- Arca только **перенаправляет пользователя** на ваш returnUrl
- Пользователь возвращается через браузер, поэтому localhost работает!

### Для тестового режима:

✅ **Можно использовать любой домен:**
- Локальный: `http://localhost:3000`
- Тестовый: `https://test.yourdomain.com`
- Временный: `https://yourproject.vercel.app`

### Для продакшн режима:

✅ **Можно использовать любой домен:**
- Постоянный продакшн домен: `https://yourdomain.com`
- Или любой другой доступный домен

**Важно:** Arca не требует регистрации домена заранее!

---

### Вопрос 2: Какие callback URL нужны Arca и зачем?

### Ответ: **ТОЛЬКО 1 URL - returnUrl**

### returnUrl

**Что это:**
- URL, на который возвращается пользователь после оплаты
- Это страница для пользователя, не для сервера
- Вы указываете его в запросе `register.do`

**Зачем нужен:**
- Пользователь возвращается на ваш сайт после оплаты
- Вы получаете параметр `orderId` в URL и проверяете статус через API

**Когда вызывается:**
- После того, как пользователь завершил оплату на странице Arca
- Arca перенаправляет пользователя на returnUrl с параметром `orderId` в URL

**Пример:**
```
returnUrl: https://yourdomain.com/api/payment/arca/callback?orderId=123
```

**Параметры, которые приходят в URL:**
- `orderId` - ID заказа в системе Arca (не ваш orderId!)

**Реализация в Next.js:**
```typescript
// app/api/payment/arca/callback/route.ts
export async function GET(request: Request) {
  const { searchParams } = new URL(request.url);
  const arcaOrderId = searchParams.get('orderId'); // ID от Arca
  const ourOrderId = searchParams.get('orderId'); // Наш orderId из returnUrl
  
  if (!arcaOrderId) {
    return Response.redirect(`${process.env.NEXT_PUBLIC_BASE_URL}/payment/failed`);
  }
  
  // ВАЖНО: Проверяем статус через API (не доверяем только URL параметрам!)
  const apiUrl = process.env.ARCA_TEST_MODE === 'true'
    ? 'https://ipaytest.arca.am:8443/payment/rest/getOrderStatusExtended.do'
    : 'https://ipay.arca.am/payment/rest/getOrderStatusExtended.do';
  
  const formData = new URLSearchParams();
  formData.append('userName', process.env.ARCA_USERNAME!);
  formData.append('password', process.env.ARCA_PASSWORD!);
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
    await updateOrderStatus(ourOrderId, 'paid', {
      arcaOrderId,
      transactionId: data.paymentAmountInfo?.transactionId,
      cardNumber: data.paymentAmountInfo?.pan
    });
    
    return Response.redirect(`${process.env.NEXT_PUBLIC_BASE_URL}/payment/success?orderId=${ourOrderId}`);
  } else {
    // Платеж не прошел
    await updateOrderStatus(ourOrderId, 'failed');
    return Response.redirect(`${process.env.NEXT_PUBLIC_BASE_URL}/payment/failed`);
  }
}
```

**Важно:**
- **НЕ доверяйте только URL параметрам!** Всегда проверяйте статус через API
- returnUrl может быть любым доступным URL
- Не нужно регистрировать URL заранее у Arca
- В returnUrl можно передать ваш orderId как параметр: `?orderId=123&ourOrderId=456`

---

### Схема работы Arca

```
1. Пользователь нажимает "Оплатить"
   ↓
2. Вы отправляете запрос register.do с returnUrl
   ↓
3. Пользователь перенаправляется на страницу оплаты Arca
   ↓
4. Пользователь вводит данные карты и подтверждает платеж
   ↓
5. Arca обрабатывает платеж
   ↓
6. Arca перенаправляет пользователя на returnUrl с orderId
   ↓
7. Ваш сервер получает orderId и проверяет статус через getOrderStatusExtended.do API
   ↓
8. Ваш сервер обновляет статус заказа и перенаправляет пользователя на страницу успеха/ошибки
```

---

## Сравнительная таблица

| Параметр | Idram | AmeriaBank | Arca |
|----------|-------|------------|------|
| **Количество URL** | 3 (RESULT_URL, SUCCESS_URL, FAIL_URL) | 1 (BackURL) | 1 (returnUrl) |
| **Сервер-сервер callback'и** | ✅ Да (2 POST запроса) | ❌ Нет | ❌ Нет |
| **Регистрация URL** | ✅ Нужна (у Idram) | ❌ Не нужна | ❌ Не нужна |
| **Локальный домен** | ❌ Не работает (для callback'ов) | ✅ Работает | ✅ Работает |
| **Проверка статуса** | Через callback'и | Через API (GetPaymentDetails) | Через API (getOrderStatusExtended) |
| **Безопасность** | Проверка подписи (EDP_CHECKSUM) | Проверка через API | Проверка через API |

---

## Рекомендации для Next.js

### 1. Структура файлов

```
app/
  api/
    payment/
      ameriabank/
        callback/
          route.ts          # BackURL - обработка возврата пользователя
      arca/
        callback/
          route.ts          # returnUrl - обработка возврата пользователя
  payment/
    success/
      page.tsx             # Страница успеха
    failed/
      page.tsx            # Страница ошибки
```

### 2. Настройка переменных окружения

```typescript
// .env.local
NEXT_PUBLIC_BASE_URL=http://localhost:3000  # Для локального тестирования

AMERIABANK_BACK_URL=http://localhost:3000/api/payment/ameriabank/callback
ARCA_RETURN_URL=http://localhost:3000/api/payment/arca/callback?orderId=OUR_ORDER_ID
```

### 3. Передача вашего orderId

**Для AmeriaBank:**
```typescript
// Используйте поле Opaque
const response = await fetch(apiUrl, {
  method: 'POST',
  body: JSON.stringify({
    // ...
    BackURL: `${baseUrl}/api/payment/ameriabank/callback`,
    Opaque: orderId.toString(), // Ваш orderId
  })
});
```

**Для Arca:**
```typescript
// Передайте в returnUrl как параметр
const formData = new URLSearchParams();
formData.append('returnUrl', `${baseUrl}/api/payment/arca/callback?ourOrderId=${orderId}`);
```

### 4. Безопасность

**Важно для AmeriaBank и Arca:**
- **НЕ доверяйте только URL параметрам!**
- Всегда проверяйте статус через API запрос
- Параметры в URL могут быть подделаны
- Используйте API для получения реального статуса платежа

---

## Примеры использования

### Локальное тестирование

```typescript
// .env.local
NEXT_PUBLIC_BASE_URL=http://localhost:3000
AMERIABANK_BACK_URL=http://localhost:3000/api/payment/ameriabank/callback
ARCA_RETURN_URL=http://localhost:3000/api/payment/arca/callback
```

### Тестовый домен

```typescript
// .env.local
NEXT_PUBLIC_BASE_URL=https://test.yourdomain.com
AMERIABANK_BACK_URL=https://test.yourdomain.com/api/payment/ameriabank/callback
ARCA_RETURN_URL=https://test.yourdomain.com/api/payment/arca/callback
```

### Продакшн

```typescript
// .env.production
NEXT_PUBLIC_BASE_URL=https://yourdomain.com
AMERIABANK_BACK_URL=https://yourdomain.com/api/payment/ameriabank/callback
ARCA_RETURN_URL=https://yourdomain.com/api/payment/arca/callback
```

---

## Чек-лист

### Для AmeriaBank:
- [ ] BackURL настроен и доступен
- [ ] Проверка статуса через GetPaymentDetails реализована
- [ ] Не доверяете только URL параметрам
- [ ] Обработка ошибок реализована

### Для Arca:
- [ ] returnUrl настроен и доступен
- [ ] Проверка статуса через getOrderStatusExtended реализована
- [ ] Не доверяете только URL параметрам
- [ ] Обработка ошибок реализована

---

## Важные замечания

1. **AmeriaBank и Arca проще, чем Idram:**
   - Не нужно регистрировать URL заранее
   - Не нужны сервер-сервер callback'и
   - Можно использовать localhost для тестирования

2. **Но нужно быть осторожным:**
   - Всегда проверяйте статус через API
   - Не доверяйте только URL параметрам
   - Параметры в URL могут быть подделаны

3. **Логирование:**
   - Логируйте все запросы для отладки
   - Сохраняйте ответы от API для аудита

4. **Идемпотентность:**
   - Пользователь может вернуться несколько раз
   - Обрабатывайте повторные запросы корректно
