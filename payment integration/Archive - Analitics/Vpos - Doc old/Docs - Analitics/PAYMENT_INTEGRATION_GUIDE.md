# Руководство по интеграции платежных систем для Next.js

## Обзор

Данный документ описывает логику интеграции трех основных платежных систем Армении:
1. **Idram** - электронный кошелек
2. **AmeriaBank** - банковская платежная система (vPOS)
3. **Arca** - платежная система

---

## 1. IDRAM (Электронный кошелек)

### 1.1 Общая информация

**Тип:** Электронный кошелек  
**Поддерживаемые способы оплаты:**
- Idram Wallet (Web)
- Idram Wallet (Mobile app)
- Банковские карты VISA/MasterCard (через iframe)

### 1.2 Необходимые параметры

Для работы с Idram требуется получить от технического персонала Idram:
- `EDP_REC_ACCOUNT` - IdramID мерчанта (номер кошелька)
- `SECRET_KEY` - секретный ключ для проверки подписи
- `SUCCESS_URL` - URL для редиректа при успешной оплате
- `FAIL_URL` - URL для редиректа при неудачной оплате
- `RESULT_URL` - URL для обработки callback'ов от Idram

### 1.3 Логика интеграции

#### Шаг 1: Инициация платежа

**Метод:** POST форма на стороне клиента  
**URL:** `https://banking.idram.am/Payment/GetPayment`

**Поля формы:**
```typescript
{
  EDP_LANGUAGE: 'RU' | 'EN' | 'AM',        // Язык интерфейса
  EDP_REC_ACCOUNT: string,                  // IdramID мерчанта
  EDP_DESCRIPTION: string,                  // Описание платежа
  EDP_AMOUNT: string,                       // Сумма (формат: "1900.00")
  EDP_BILL_NO: string,                     // Номер заказа в вашей системе
  EDP_EMAIL?: string,                      // Email для уведомлений (опционально)
  // Любые другие поля без префикса "EDP_" будут возвращены после оплаты
}
```

**Реализация в Next.js:**
```typescript
// app/api/payment/idram/initiate/route.ts
export async function POST(request: Request) {
  const { orderId, amount, description } = await request.json();
  
  // Сохраняем заказ в БД со статусом "pending"
  
  return Response.json({
    formUrl: 'https://banking.idram.am/Payment/GetPayment',
    formData: {
      EDP_LANGUAGE: 'EN',
      EDP_REC_ACCOUNT: process.env.IDRAM_REC_ACCOUNT!,
      EDP_DESCRIPTION: description,
      EDP_AMOUNT: amount.toFixed(2),
      EDP_BILL_NO: orderId.toString(),
    }
  });
}
```

**На клиенте:**
```typescript
// components/PaymentForm.tsx
const handleIdramPayment = async () => {
  const response = await fetch('/api/payment/idram/initiate', {
    method: 'POST',
    body: JSON.stringify({ orderId, amount, description })
  });
  
  const { formUrl, formData } = await response.json();
  
  // Создаем скрытую форму и отправляем
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = formUrl;
  
  Object.entries(formData).forEach(([key, value]) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = key;
    input.value = value as string;
    form.appendChild(input);
  });
  
  document.body.appendChild(form);
  form.submit();
};
```

#### Шаг 2: Обработка callback'ов (RESULT_URL)

Idram отправляет **два POST запроса** на `RESULT_URL`:

##### 2.1 Предварительная проверка (EDP_PRECHECK)

**Когда:** Перед переводом средств  
**Цель:** Проверить, что заказ существует и сумма корректна

**Параметры:**
```typescript
{
  EDP_PRECHECK: 'YES',
  EDP_BILL_NO: string,        // Номер заказа
  EDP_REC_ACCOUNT: string,    // IdramID мерчанта
  EDP_AMOUNT: string          // Сумма платежа
}
```

**Обработка:**
```typescript
// app/api/payment/idram/callback/route.ts
export async function POST(request: Request) {
  const formData = await request.formData();
  const precheck = formData.get('EDP_PRECHECK');
  
  // Предварительная проверка
  if (precheck === 'YES') {
    const billNo = formData.get('EDP_BILL_NO') as string;
    const recAccount = formData.get('EDP_REC_ACCOUNT') as string;
    const amount = parseFloat(formData.get('EDP_AMOUNT') as string);
    
    // Проверяем, что это наш мерчант
    if (recAccount !== process.env.IDRAM_REC_ACCOUNT) {
      return new Response('Invalid merchant', { status: 400 });
    }
    
    // Проверяем заказ в БД
    const order = await getOrderById(billNo);
    if (!order || order.amount !== amount) {
      return new Response('Invalid order', { status: 400 });
    }
    
    // Возвращаем "OK" - Idram продолжит платеж
    return new Response('OK', { status: 200 });
  }
  
  // Подтверждение платежа (см. ниже)
  // ...
}
```

##### 2.2 Подтверждение платежа

**Когда:** После успешного перевода средств  
**Цель:** Подтвердить получение платежа

**Параметры:**
```typescript
{
  EDP_BILL_NO: string,           // Номер заказа
  EDP_REC_ACCOUNT: string,       // IdramID мерчанта
  EDP_PAYER_ACCOUNT: string,    // IdramID плательщика
  EDP_AMOUNT: string,            // Сумма (формат: "1900.00")
  EDP_TRANS_ID: string,          // ID транзакции в Idram (14 символов)
  EDP_TRANS_DATE: string,        // Дата транзакции (формат: "dd/mm/yyyy")
  EDP_CHECKSUM: string           // MD5 подпись для проверки
}
```

**Проверка подписи (EDP_CHECKSUM):**

Формула для проверки:
```
MD5(EDP_REC_ACCOUNT:EDP_AMOUNT:SECRET_KEY:EDP_BILL_NO:EDP_PAYER_ACCOUNT:EDP_TRANS_ID:EDP_TRANS_DATE)
```

**Важно:** При проверке используйте сумму из вашей БД, а не из запроса!

```typescript
// app/api/payment/idram/callback/route.ts (продолжение)
export async function POST(request: Request) {
  const formData = await request.formData();
  
  // Если это подтверждение платежа
  if (formData.has('EDP_PAYER_ACCOUNT') && formData.has('EDP_CHECKSUM')) {
    const billNo = formData.get('EDP_BILL_NO') as string;
    const recAccount = formData.get('EDP_REC_ACCOUNT') as string;
    const payerAccount = formData.get('EDP_PAYER_ACCOUNT') as string;
    const amount = formData.get('EDP_AMOUNT') as string;
    const transId = formData.get('EDP_TRANS_ID') as string;
    const transDate = formData.get('EDP_TRANS_DATE') as string;
    const checksum = formData.get('EDP_CHECKSUM') as string;
    
    // Получаем заказ из БД
    const order = await getOrderById(billNo);
    if (!order) {
      return new Response('Order not found', { status: 400 });
    }
    
    // Проверяем мерчанта
    if (recAccount !== process.env.IDRAM_REC_ACCOUNT) {
      await updateOrderStatus(billNo, 'failed');
      return new Response('Invalid merchant', { status: 400 });
    }
    
    // Проверяем подпись (используем сумму из БД!)
    const secretKey = process.env.IDRAM_SECRET_KEY!;
    const stringToHash = [
      recAccount,
      order.amount.toFixed(2),  // ВАЖНО: используем сумму из БД
      secretKey,
      billNo,
      payerAccount,
      transId,
      transDate
    ].join(':');
    
    const calculatedChecksum = crypto
      .createHash('md5')
      .update(stringToHash)
      .digest('hex')
      .toUpperCase();
    
    if (calculatedChecksum !== checksum.toUpperCase()) {
      await updateOrderStatus(billNo, 'failed');
      return new Response('Invalid checksum', { status: 400 });
    }
    
    // Проверяем сумму
    if (parseFloat(amount) !== order.amount) {
      await updateOrderStatus(billNo, 'failed');
      return new Response('Amount mismatch', { status: 400 });
    }
    
    // Все проверки пройдены - обновляем статус заказа
    await updateOrderStatus(billNo, 'paid', {
      transactionId: transId,
      payerAccount,
      transDate
    });
    
    // ВАЖНО: Возвращаем "OK" с HTTP 200
    return new Response('OK', { status: 200 });
  }
  
  return new Response('Invalid request', { status: 400 });
}
```

#### Шаг 3: Редиректы пользователя

После оплаты пользователь перенаправляется на:
- `SUCCESS_URL` - при успешной оплате
- `FAIL_URL` - при неудачной оплате

На эти страницы Idram может передать дополнительные поля (которые вы указали в форме без префикса "EDP_").

```typescript
// app/payment/success/page.tsx
export default function PaymentSuccessPage() {
  const searchParams = useSearchParams();
  const orderId = searchParams.get('EDP_BILL_NO');
  
  // Проверяем статус заказа в БД
  // Показываем сообщение об успехе
}
```

### 1.4 Особенности для Next.js

1. **API Routes:** Используйте `/api/payment/idram/callback` для обработки callback'ов
2. **Middleware:** Убедитесь, что callback endpoint доступен без CSRF защиты (Idram не может пройти CSRF)
3. **Логирование:** Логируйте все запросы от Idram для отладки
4. **Идемпотентность:** Обрабатывайте повторные callback'и (Idram может отправить несколько раз)

### 1.5 Тестирование

**Важно:** Idram использует **ОДИН И ТОТ ЖЕ URL** для теста и продакшн!

- **URL:** `https://banking.idram.am/Payment/GetPayment` (одинаковый для обоих режимов)
- **Разница только в учетных данных:**
  - Тестовый режим: `test_edp_rec_account` + `test_secret_key`
  - Продакшн режим: `live_edp_rec_account` + `live_secret_key`

**Как получить тестовые данные:**
1. Связаться с техническим персоналом Idram
2. Запросить тестовый аккаунт (test EDP_REC_ACCOUNT и test SECRET_KEY)

**Настройка:**
```typescript
// .env.local
IDRAM_TEST_MODE=true
IDRAM_TEST_REC_ACCOUNT=test_account_from_idram
IDRAM_TEST_SECRET_KEY=test_secret_key_from_idram

IDRAM_LIVE_REC_ACCOUNT=live_account
IDRAM_LIVE_SECRET_KEY=live_secret_key
```

**Переключение режима:**
- Тест: `IDRAM_TEST_MODE=true` → использует тестовые учетные данные
- Продакшн: `IDRAM_TEST_MODE=false` → использует продакшн учетные данные
- URL остается тот же в обоих случаях

Подробнее см. `TESTING_GUIDE.md`

---

## 2. AMERIABANK (vPOS)

### 2.1 Общая информация

**Тип:** Банковская платежная система  
**API:** REST API  
**Поддерживаемые карты:** VISA, MasterCard, ArCa

### 2.2 Необходимые параметры

- `ClientID` - ID клиента в системе AmeriaBank
- `Username` - Имя пользователя API
- `Password` - Пароль API
- `BackURL` - URL для возврата после оплаты

### 2.3 Логика интеграции

#### Шаг 1: Инициация платежа (InitPayment)

**Метод:** POST запрос на сервер  
**URL (Production):** `https://services.ameriabank.am/VPOS/api/VPOS/InitPayment`  
**URL (Test):** `https://servicestest.ameriabank.am/VPOS/api/VPOS/InitPayment`

**Запрос:**
```typescript
{
  ClientID: string,
  Username: string,
  Password: string,
  OrderID: string,              // Уникальный номер заказа
  Amount: number,               // Сумма (например, 1000.00)
  Currency: string,             // "AMD", "USD", "EUR"
  BackURL: string,              // URL для возврата
  Description?: string,         // Описание платежа
  lang?: string,                // "am", "en", "ru"
  Opaque?: string,              // Дополнительные данные (например, orderId)
  CardHolderID?: string         // Для сохранения карты (опционально)
}
```

**Реализация:**
```typescript
// app/api/payment/ameriabank/initiate/route.ts
export async function POST(request: Request) {
  const { orderId, amount, currency, description } = await request.json();
  
  // Сохраняем заказ в БД
  const order = await createOrder({
    orderId,
    amount,
    currency,
    status: 'pending'
  });
  
  const apiUrl = process.env.AMERIABANK_TEST_MODE === 'true'
    ? 'https://servicestest.ameriabank.am/VPOS/api/VPOS/InitPayment'
    : 'https://services.ameriabank.am/VPOS/api/VPOS/InitPayment';
  
  const response = await fetch(apiUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json; charset=utf-8'
    },
    body: JSON.stringify({
      ClientID: process.env.AMERIABANK_CLIENT_ID!,
      Username: process.env.AMERIABANK_USERNAME!,
      Password: process.env.AMERIABANK_PASSWORD!,
      OrderID: orderId.toString(),
      Amount: amount,
      Currency: currency,
      BackURL: `${process.env.NEXT_PUBLIC_BASE_URL}/api/payment/ameriabank/callback`,
      Description: description || 'Online payment',
      lang: 'en',
      Opaque: orderId.toString()
    })
  });
  
  const data = await response.json();
  
  // Проверяем ответ
  if (data.ResponseCode === '00') {
    // Сохраняем paymentID и orderID из ответа
    await updateOrder(orderId, {
      paymentId: data.PaymentID,
      orderId: data.OrderID,
      status: 'registered'
    });
    
    // Перенаправляем пользователя на страницу оплаты
    return Response.json({
      success: true,
      redirectUrl: data.PaymentURL || data.FormURL
    });
  } else {
    // Ошибка инициализации
    await updateOrderStatus(orderId, 'failed');
    return Response.json({
      success: false,
      error: data.ResponseMessage || 'Payment initialization failed'
    }, { status: 400 });
  }
}
```

#### Шаг 2: Обработка возврата пользователя (BackURL)

После оплаты пользователь возвращается на `BackURL` с параметрами:
- `orderID` - ID заказа в системе AmeriaBank
- `paymentID` - ID платежа
- `resposneCode` - Код ответа ("00" = успех)
- `currency` - Валюта

**Обработка:**
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
  
  // Проверяем статус платежа через GetPaymentDetails
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

### 2.4 Особенности для Next.js

1. **Двухэтапная проверка:** Сначала проверяем `responseCode`, затем делаем запрос `GetPaymentDetails`
2. **Безопасность:** Не полагайтесь только на параметры URL, всегда проверяйте через API
3. **Логирование:** Сохраняйте все ответы от API для аудита

### 2.5 Тестирование

**Важно:** AmeriaBank использует **РАЗНЫЕ URL** для теста и продакшн!

- **Тестовый URL:** `https://servicestest.ameriabank.am/VPOS/api/VPOS/InitPayment`
- **Продакшн URL:** `https://services.ameriabank.am/VPOS/api/VPOS/InitPayment`

**Разные учетные данные:**
- Тестовый режим: `test_username` + `test_password` + `test_clientID`
- Продакшн режим: `live_username` + `live_password` + `live_clientID`

**Как получить тестовые данные:**
1. Обратиться в AmeriaBank
2. Запросить доступ к тестовой среде vPOS
3. Получить тестовые `Username`, `Password`, `ClientID`

**Настройка:**
```typescript
// .env.local
AMERIABANK_TEST_MODE=true
AMERIABANK_TEST_USERNAME=test_username
AMERIABANK_TEST_PASSWORD=test_password
AMERIABANK_TEST_CLIENT_ID=test_client_id

AMERIABANK_LIVE_USERNAME=live_username
AMERIABANK_LIVE_PASSWORD=live_password
AMERIABANK_LIVE_CLIENT_ID=live_client_id
```

**Переключение режима:**
- Тест: `AMERIABANK_TEST_MODE=true` → использует `servicestest.ameriabank.am` + тестовые данные
- Продакшн: `AMERIABANK_TEST_MODE=false` → использует `services.ameriabank.am` + продакшн данные

Подробнее см. `TESTING_GUIDE.md`

---

## 3. ARCA (Платежная система)

### 3.1 Общая информация

**Тип:** Платежная система  
**API:** REST API  
**Поддерживаемые карты:** ArCa, VISA, MasterCard

### 3.2 Необходимые параметры

- `userName` - Имя пользователя API
- `password` - Пароль API
- `returnUrl` - URL для возврата после оплаты

### 3.3 Логика интеграции

#### Шаг 1: Регистрация платежа (register.do)

**Метод:** POST запрос  
**URL (Production):** `https://ipay.arca.am/payment/rest/register.do`  
**URL (Test):** `https://ipaytest.arca.am:8443/payment/rest/register.do`

**Запрос:**
```typescript
{
  userName: string,
  password: string,
  orderNumber: string,          // Уникальный номер заказа
  amount: number,                // Сумма в минимальных единицах (копейки/центы) * 100
  currency: string,              // "051" (AMD), "840" (USD), "978" (EUR)
  returnUrl: string,            // URL для возврата
  description?: string,          // Описание
  language?: string,             // "en", "ru", "am"
  pageView?: string,             // "MOBILE" или "DESKTOP"
  jsonParams?: string            // Дополнительные параметры в JSON
}
```

**Реализация:**
```typescript
// app/api/payment/arca/initiate/route.ts
export async function POST(request: Request) {
  const { orderId, amount, currency, description } = await request.json();
  
  // Сохраняем заказ в БД
  const order = await createOrder({
    orderId,
    amount,
    currency,
    status: 'pending'
  });
  
  // Конвертируем валюту в код ISO 4217
  const currencyCode = {
    'AMD': '051',
    'USD': '840',
    'EUR': '978'
  }[currency] || '051';
  
  // Конвертируем сумму в минимальные единицы
  const amountInCents = Math.round(amount * 100);
  
  const apiUrl = process.env.ARCA_TEST_MODE === 'true'
    ? 'https://ipaytest.arca.am:8443/payment/rest/register.do'
    : 'https://ipay.arca.am/payment/rest/register.do';
  
  const formData = new URLSearchParams();
  formData.append('userName', process.env.ARCA_USERNAME!);
  formData.append('password', process.env.ARCA_PASSWORD!);
  formData.append('orderNumber', orderId.toString());
  formData.append('amount', amountInCents.toString());
  formData.append('currency', currencyCode);
  formData.append('returnUrl', `${process.env.NEXT_PUBLIC_BASE_URL}/api/payment/arca/callback?orderId=${orderId}`);
  formData.append('description', description || 'Online payment');
  formData.append('language', 'en');
  formData.append('jsonParams', JSON.stringify({ FORCE_3DS2: 'true' }));
  
  const response = await fetch(apiUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
    },
    body: formData
  });
  
  const data = await response.json();
  
  if (data.errorCode === 0) {
    // Сохраняем orderId из ответа Arca
    await updateOrder(orderId, {
      arcaOrderId: data.orderId,
      status: 'registered'
    });
    
    // Перенаправляем на форму оплаты
    return Response.json({
      success: true,
      redirectUrl: data.formUrl
    });
  } else {
    await updateOrderStatus(orderId, 'failed');
    return Response.json({
      success: false,
      error: data.errorMessage || `Error code: ${data.errorCode}`
    }, { status: 400 });
  }
}
```

#### Шаг 2: Обработка возврата пользователя

После оплаты пользователь возвращается на `returnUrl` с параметром `orderId` (от Arca).

**Обработка:**
```typescript
// app/api/payment/arca/callback/route.ts
export async function GET(request: Request) {
  const { searchParams } = new URL(request.url);
  const arcaOrderId = searchParams.get('orderId');
  const ourOrderId = searchParams.get('orderId'); // Наш orderId из returnUrl
  
  if (!arcaOrderId) {
    return Response.redirect(`${process.env.NEXT_PUBLIC_BASE_URL}/payment/failed`);
  }
  
  // Проверяем статус через getOrderStatusExtended.do
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

### 3.4 Особенности для Next.js

1. **Формат суммы:** Arca требует сумму в минимальных единицах (копейки/центы)
2. **Коды валют:** Используйте числовые коды ISO 4217
3. **3DS2:** Рекомендуется использовать `FORCE_3DS2: true` для безопасности

### 3.5 Тестирование

**Важно:** Arca использует **РАЗНЫЕ URL и ПОРТЫ** для теста и продакшн!

- **Тестовый URL:** `https://ipaytest.arca.am:8443/payment/rest/register.do`
- **Продакшн URL:** `https://ipay.arca.am/payment/rest/register.do`

**Разные учетные данные:**
- Тестовый режим: `test_username` + `test_password`
- Продакшн режим: `live_username` + `live_password`

**Важно:** Тестовый сервер использует порт `8443` или `8444`

**Как получить тестовые данные:**
1. Обратиться в Arca
2. Запросить доступ к тестовой среде
3. Получить тестовые `userName` и `password`

**Настройка:**
```typescript
// .env.local
ARCA_TEST_MODE=true
ARCA_TEST_USERNAME=test_username
ARCA_TEST_PASSWORD=test_password
ARCA_TEST_PORT=8443

ARCA_LIVE_USERNAME=live_username
ARCA_LIVE_PASSWORD=live_password
```

**Переключение режима:**
- Тест: `ARCA_TEST_MODE=true` → использует `ipaytest.arca.am:8443` + тестовые данные
- Продакшн: `ARCA_TEST_MODE=false` → использует `ipay.arca.am` + продакшн данные

Подробнее см. `TESTING_GUIDE.md`

---

## Общие рекомендации для Next.js

### Архитектура

```
app/
  api/
    payment/
      idram/
        initiate/route.ts      # Инициация платежа Idram
        callback/route.ts      # Обработка callback'ов Idram
      ameriabank/
        initiate/route.ts      # Инициация платежа AmeriaBank
        callback/route.ts      # Обработка возврата AmeriaBank
      arca/
        initiate/route.ts      # Инициация платежа Arca
        callback/route.ts      # Обработка возврата Arca
  payment/
    success/page.tsx           # Страница успешной оплаты
    failed/page.tsx            # Страница неудачной оплаты
```

### Безопасность

1. **Секретные ключи:** Храните в `.env.local`, никогда не коммитьте в Git
2. **Валидация:** Всегда проверяйте суммы и подписи
3. **Идемпотентность:** Обрабатывайте повторные callback'и
4. **Логирование:** Логируйте все платежные операции

### База данных

Рекомендуемая структура таблицы заказов:
```sql
CREATE TABLE orders (
  id SERIAL PRIMARY KEY,
  order_id VARCHAR(255) UNIQUE NOT NULL,
  amount DECIMAL(10, 2) NOT NULL,
  currency VARCHAR(3) NOT NULL,
  status VARCHAR(50) NOT NULL,
  payment_method VARCHAR(50),
  transaction_id VARCHAR(255),
  payment_data JSONB,
  created_at TIMESTAMP DEFAULT NOW(),
  updated_at TIMESTAMP DEFAULT NOW()
);
```

### Обработка ошибок

Всегда обрабатывайте:
- Сетевые ошибки
- Неверные ответы от API
- Таймауты
- Повторные callback'и

### Тестирование

1. **Локальное тестирование:** Используйте ngrok для тестирования callback'ов
2. **Тестовые режимы:** Всегда тестируйте в тестовом режиме перед продакшн
3. **Логирование:** Включите подробное логирование для отладки

---

## Следующие шаги

1. ✅ Изучить документацию (выполнено)
2. ⏳ Создать план реализации
3. ⏳ Настроить тестовые аккаунты
4. ⏳ Реализовать интеграцию для каждой системы
5. ⏳ Протестировать в тестовом режиме
6. ⏳ Перейти на продакшн
