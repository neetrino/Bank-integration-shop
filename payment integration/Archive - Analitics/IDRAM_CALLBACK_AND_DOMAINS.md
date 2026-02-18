# Idram: Callback URL и требования к доменам

## Вопрос 1: Можно ли тестировать с локального/тестового домена?

### Ответ: **ДА, но с оговорками**

### Для тестового режима:

✅ **Можно использовать:**
- Локальный домен: `http://localhost:3000`
- Тестовый домен: `https://test.yourdomain.com`
- Временный домен: `https://yourproject.vercel.app` (для Next.js)
- ngrok туннель: `https://abc123.ngrok.io`

⚠️ **Важно:**
- Idram должен иметь возможность **достучаться до вашего сервера** для отправки callback'ов
- Локальный `localhost` **НЕ РАБОТАЕТ** для callback'ов (Idram не может достучаться до вашего компьютера)
- Нужен **публично доступный URL** (через ngrok, тестовый домен, или Vercel/Netlify)

### Для продакшн режима:

❌ **НЕЛЬЗЯ использовать:**
- Локальный домен
- Тестовый домен
- Временные домены

✅ **НУЖНО использовать:**
- Постоянный продакшн домен: `https://yourdomain.com`
- Этот домен должен быть **зарегистрирован у Idram** техническим персоналом

### Решение для локального тестирования:

#### Вариант 1: Использовать ngrok (рекомендуется)

```bash
# Установите ngrok
npm install -g ngrok

# Запустите туннель на ваш локальный сервер
ngrok http 3000

# Вы получите публичный URL, например:
# https://abc123.ngrok.io
```

**Настройка:**
```typescript
// .env.local
NEXT_PUBLIC_BASE_URL=https://abc123.ngrok.io
IDRAM_RESULT_URL=https://abc123.ngrok.io/api/payment/idram/callback
IDRAM_SUCCESS_URL=https://abc123.ngrok.io/payment/success
IDRAM_FAIL_URL=https://abc123.ngrok.io/payment/failed
```

⚠️ **Проблема:** URL ngrok меняется при каждом перезапуске (если не используете платную версию)

#### Вариант 2: Использовать Vercel/Netlify для тестирования

1. Деплойте проект на Vercel/Netlify
2. Используйте preview URL для тестирования
3. После тестирования переключитесь на продакшн домен

```typescript
// .env.local (для Vercel preview)
NEXT_PUBLIC_BASE_URL=https://your-project-git-main.vercel.app
```

#### Вариант 3: Использовать тестовый поддомен

1. Создайте поддомен: `test.yourdomain.com`
2. Настройте DNS на ваш тестовый сервер
3. Используйте этот домен для тестирования

```typescript
// .env.local
NEXT_PUBLIC_BASE_URL=https://test.yourdomain.com
```

### Рекомендация:

1. **Для разработки:** Используйте ngrok или Vercel preview
2. **Для тестирования:** Используйте тестовый поддомен
3. **Для продакшн:** Используйте постоянный продакшн домен

---

## Вопрос 2: Какие callback URL нужны Idram и зачем?

### Ответ: Idram требует **3 callback URL**

### 1. RESULT_URL (самый важный!)

**Что это:**
- URL скрипта, который обрабатывает запросы от Idram
- Idram отправляет сюда **два POST запроса** для каждого платежа

**Зачем нужен:**
- **Первый запрос (EDP_PRECHECK):** Проверка заказа перед переводом средств
- **Второй запрос:** Подтверждение успешного платежа

**Когда вызывается:**
1. Пользователь нажимает "Оплатить" на странице Idram
2. Idram отправляет первый POST на RESULT_URL с `EDP_PRECHECK=YES`
3. Ваш сервер должен ответить "OK" если заказ валиден
4. Если "OK" - Idram переводит деньги
5. После перевода Idram отправляет второй POST на RESULT_URL с данными платежа
6. Ваш сервер проверяет подпись и обновляет статус заказа
7. Ваш сервер должен ответить "OK"

**Пример:**
```
RESULT_URL: https://yourdomain.com/api/payment/idram/callback
```

**Реализация в Next.js:**
```typescript
// app/api/payment/idram/callback/route.ts
export async function POST(request: Request) {
  const formData = await request.formData();
  
  // Первый запрос - предварительная проверка
  if (formData.get('EDP_PRECHECK') === 'YES') {
    const billNo = formData.get('EDP_BILL_NO') as string;
    const amount = parseFloat(formData.get('EDP_AMOUNT') as string);
    
    // Проверяем заказ в БД
    const order = await getOrderById(billNo);
    if (!order || order.amount !== amount) {
      return new Response('Invalid order', { status: 400 });
    }
    
    // Возвращаем "OK" - Idram продолжит платеж
    return new Response('OK', { status: 200 });
  }
  
  // Второй запрос - подтверждение платежа
  if (formData.has('EDP_PAYER_ACCOUNT') && formData.has('EDP_CHECKSUM')) {
    // Проверяем подпись и обновляем статус заказа
    const isValid = await verifyChecksum(formData);
    if (!isValid) {
      return new Response('Invalid checksum', { status: 400 });
    }
    
    await updateOrderStatus(billNo, 'paid');
    
    // ВАЖНО: Возвращаем "OK" с HTTP 200
    return new Response('OK', { status: 200 });
  }
  
  return new Response('Invalid request', { status: 400 });
}
```

**Важно:**
- RESULT_URL должен быть **публично доступен** (Idram должен достучаться до него)
- Должен отвечать **быстро** (таймаут обычно 30 секунд)
- Должен возвращать **точно "OK"** (без HTML, без пробелов)
- Должен возвращать **HTTP 200** для успеха

---

### 2. SUCCESS_URL

**Что это:**
- URL страницы, на которую перенаправляется пользователь после **успешной** оплаты
- Это страница для пользователя, не для сервера

**Зачем нужен:**
- Показать пользователю сообщение об успешной оплате
- Позволить пользователю вернуться на сайт
- Показать детали заказа

**Когда вызывается:**
- После успешного платежа
- Idram перенаправляет пользователя на этот URL
- Пользователь видит эту страницу в браузере

**Пример:**
```
SUCCESS_URL: https://yourdomain.com/payment/success
```

**Реализация в Next.js:**
```typescript
// app/payment/success/page.tsx
export default function PaymentSuccessPage() {
  const searchParams = useSearchParams();
  const orderId = searchParams.get('EDP_BILL_NO');
  
  return (
    <div>
      <h1>Оплата успешна!</h1>
      <p>Заказ #{orderId} успешно оплачен</p>
      <Link href="/orders">Вернуться к заказам</Link>
    </div>
  );
}
```

**Важно:**
- Это страница для пользователя, не API endpoint
- Idram может передать дополнительные параметры в URL
- Не используйте эту страницу для обработки платежа (используйте RESULT_URL)

---

### 3. FAIL_URL

**Что это:**
- URL страницы, на которую перенаправляется пользователь после **неудачной** оплаты
- Это страница для пользователя, не для сервера

**Зачем нужен:**
- Показать пользователю сообщение об ошибке
- Позволить пользователю попробовать снова
- Объяснить причину неудачи

**Когда вызывается:**
- Если пользователь отменил платеж
- Если платеж не прошел
- Если проверка заказа не прошла (RESULT_URL вернул ошибку)

**Пример:**
```
FAIL_URL: https://yourdomain.com/payment/failed
```

**Реализация в Next.js:**
```typescript
// app/payment/failed/page.tsx
export default function PaymentFailedPage() {
  const searchParams = useSearchParams();
  const orderId = searchParams.get('EDP_BILL_NO');
  
  return (
    <div>
      <h1>Оплата не прошла</h1>
      <p>К сожалению, оплата заказа #{orderId} не была завершена</p>
      <Link href="/checkout">Попробовать снова</Link>
    </div>
  );
}
```

**Важно:**
- Это страница для пользователя, не API endpoint
- Idram может передать дополнительные параметры в URL
- Не используйте эту страницу для обработки платежа

---

## Как зарегистрировать callback URL у Idram?

### Шаг 1: Подготовьте URL

Убедитесь, что у вас есть:
1. **RESULT_URL** - публично доступный API endpoint
2. **SUCCESS_URL** - страница успешной оплаты
3. **FAIL_URL** - страница неудачной оплаты

### Шаг 2: Свяжитесь с Idram

1. Обратитесь к техническому персоналу Idram
2. Сообщите, что хотите зарегистрировать callback URL
3. Предоставьте им три URL:

```
RESULT_URL: https://yourdomain.com/api/payment/idram/callback
SUCCESS_URL: https://yourdomain.com/payment/success
FAIL_URL: https://yourdomain.com/payment/failed
```

### Шаг 3: Для тестового режима

Если тестируете на тестовом домене, предоставьте тестовые URL:

```
RESULT_URL: https://test.yourdomain.com/api/payment/idram/callback
SUCCESS_URL: https://test.yourdomain.com/payment/success
FAIL_URL: https://test.yourdomain.com/payment/failed
```

**Важно:** Idram может зарегистрировать разные URL для теста и продакшн, или один набор для обоих режимов.

### Шаг 4: Проверка

После регистрации:
1. Создайте тестовый заказ
2. Проверьте, что Idram может достучаться до RESULT_URL
3. Проверьте логи на вашем сервере
4. Убедитесь, что callback'и приходят корректно

---

## Схема работы callback'ов

```
1. Пользователь нажимает "Оплатить"
   ↓
2. Форма отправляется на https://banking.idram.am/Payment/GetPayment
   ↓
3. Пользователь авторизуется в Idram и подтверждает платеж
   ↓
4. Idram отправляет POST на RESULT_URL (EDP_PRECHECK=YES)
   ↓
5. Ваш сервер проверяет заказ и отвечает "OK"
   ↓
6. Idram переводит деньги
   ↓
7. Idram отправляет POST на RESULT_URL (с данными платежа)
   ↓
8. Ваш сервер проверяет подпись и обновляет статус заказа
   ↓
9. Ваш сервер отвечает "OK"
   ↓
10. Idram перенаправляет пользователя на SUCCESS_URL или FAIL_URL
```

---

## Рекомендации для Next.js

### 1. Структура файлов

```
app/
  api/
    payment/
      idram/
        callback/
          route.ts          # RESULT_URL - обработка callback'ов
  payment/
    success/
      page.tsx             # SUCCESS_URL - страница успеха
    failed/
      page.tsx            # FAIL_URL - страница ошибки
```

### 2. Настройка переменных окружения

```typescript
// .env.local
NEXT_PUBLIC_BASE_URL=https://yourdomain.com
IDRAM_RESULT_URL=https://yourdomain.com/api/payment/idram/callback
IDRAM_SUCCESS_URL=https://yourdomain.com/payment/success
IDRAM_FAIL_URL=https://yourdomain.com/payment/failed
```

### 3. Защита callback endpoint

```typescript
// app/api/payment/idram/callback/route.ts
export async function POST(request: Request) {
  // Отключаем CSRF для этого endpoint (Idram не может пройти CSRF)
  // Но проверяем подпись через EDP_CHECKSUM!
  
  const formData = await request.formData();
  
  // Логируем все запросы для отладки
  console.log('Idram callback:', Object.fromEntries(formData));
  
  // Обработка...
}
```

### 4. Тестирование локально

```bash
# Используйте ngrok
ngrok http 3000

# Обновите .env.local
NEXT_PUBLIC_BASE_URL=https://abc123.ngrok.io
IDRAM_RESULT_URL=https://abc123.ngrok.io/api/payment/idram/callback
IDRAM_SUCCESS_URL=https://abc123.ngrok.io/payment/success
IDRAM_FAIL_URL=https://abc123.ngrok.io/payment/failed

# Предоставьте эти URL Idram для тестового режима
```

---

## Чек-лист перед регистрацией у Idram

- [ ] RESULT_URL публично доступен (не localhost)
- [ ] RESULT_URL возвращает "OK" при успешной проверке
- [ ] RESULT_URL обрабатывает оба типа запросов (precheck и payment)
- [ ] SUCCESS_URL показывает страницу успеха
- [ ] FAIL_URL показывает страницу ошибки
- [ ] Все URL используют HTTPS (для продакшн)
- [ ] Логирование включено для отладки
- [ ] Проверка подписи реализована корректно

---

## Важные замечания

1. **RESULT_URL - это сервер-сервер запрос**, пользователь его не видит
2. **SUCCESS_URL и FAIL_URL - это для пользователя**, они видят эти страницы
3. **Не обрабатывайте платеж на SUCCESS_URL/FAIL_URL** - используйте только RESULT_URL
4. **Всегда проверяйте подпись** в RESULT_URL через EDP_CHECKSUM
5. **Логируйте все запросы** для отладки и аудита
6. **Идемпотентность:** Обрабатывайте повторные callback'и (Idram может отправить несколько раз)
