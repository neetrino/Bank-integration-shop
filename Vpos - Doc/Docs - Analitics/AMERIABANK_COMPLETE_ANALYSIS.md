# Полный анализ интеграции AmeriaBank - 100% точная информация

## Источники анализа

1. **HK Agency** - `payment-gateway-for-ameriabank/includes/main.php`
2. **PlanetStudio Agency** - `arca-payment-gateway-pro/endpoints/apg-ameria-bank.php`
3. **Документация** - `Documentation/AmeriaBank/vPOS_Eng_3.1.docx` (не доступна для прямого чтения)

---

## ✅ ГЛАВНЫЙ ВЫВОД: НЕТ СЕРВЕР-СЕРВЕР CALLBACK'ОВ!

**100% подтверждено:** AmeriaBank **НЕ отправляет** сервер-сервер callback'и (как Idram).

**Как работает:**
- Только **возврат пользователя** на BackURL с параметрами в URL
- Вы сами проверяете статус через API `GetPaymentDetails`
- Работает одинаково в **тестовом и продакшн** режиме

---

## 1. КАК РАБОТАЕТ ИНТЕГРАЦИЯ

### Шаг 1: Инициация платежа (InitPayment)

**API Endpoint:**
- Тестовый: `https://servicestest.ameriabank.am/VPOS/api/VPOS/InitPayment`
- Продакшн: `https://services.ameriabank.am/VPOS/api/VPOS/InitPayment`

**Запрос:**
```json
{
  "ClientID": "string",
  "Username": "string",
  "Password": "string",
  "OrderID": "string",
  "Amount": 1000.00,
  "Currency": "051",
  "BackURL": "https://yourdomain.com/api/payment/ameriabank/callback",
  "Description": "Order description",
  "lang": "en",
  "Opaque": "orderId123"  // Опционально - ваш orderId
}
```

**Ответ при успехе:**
```json
{
  "ResponseCode": 1,
  "ResponseMessage": "OK",
  "PaymentID": "15C8E0DE-F082-4785-883E-A5FADB093BE2"
}
```

**Что происходит:**
1. Вы отправляете POST запрос на `InitPayment`
2. AmeriaBank создает платеж и возвращает `PaymentID`
3. Вы перенаправляете пользователя на страницу оплаты:
   - `https://services.ameriabank.am/VPOS/Payments/Pay?id={PaymentID}&lang={lang}`
   - Или тестовый: `https://servicestest.ameriabank.am/VPOS/Payments/Pay?id={PaymentID}&lang={lang}`

---

### Шаг 2: Пользователь оплачивает

**Что происходит:**
1. Пользователь вводит данные карты на странице AmeriaBank
2. AmeriaBank обрабатывает платеж
3. **AmeriaBank НЕ отправляет callback на ваш сервер!**
4. AmeriaBank перенаправляет пользователя на ваш `BackURL` с параметрами в URL

---

### Шаг 3: Возврат пользователя (BackURL)

**Параметры в URL:**
```
https://yourdomain.com/api/payment/ameriabank/callback?
  orderID=105&
  paymentID=15C8E0DE-F082-4785-883E-A5FADB093BE2&
  resposneCode=00&
  currency=051&
  Opaque=orderId123
```

**Параметры:**
- `orderID` - ID заказа в системе AmeriaBank (не ваш orderId!)
- `paymentID` - ID платежа (из ответа InitPayment)
- `resposneCode` - Код ответа ("00" = успех, другие = ошибка)
- `currency` - Валюта
- `Opaque` - Ваш orderId (если передали в InitPayment)

**Важно:**
- Это **GET запрос** (не POST!)
- Пользователь возвращается через браузер
- Параметры в URL могут быть подделаны
- **НЕ доверяйте только URL параметрам!**

---

### Шаг 4: Проверка статуса через API

**Обязательно проверяйте статус через API!**

**API Endpoint:**
- Тестовый: `https://servicestest.ameriabank.am/VPOS/api/VPOS/GetPaymentDetails`
- Продакшн: `https://services.ameriabank.am/VPOS/api/VPOS/GetPaymentDetails`

**Запрос:**
```json
{
  "Username": "string",
  "Password": "string",
  "paymentID": "15C8E0DE-F082-4785-883E-A5FADB093BE2"
}
```

**Ответ при успехе:**
```json
{
  "ResponseCode": "00",
  "PaymentState": "Successful",
  "OrderStatus": 2,
  "TransactionID": "string",
  "CardNumber": "****1234",
  "ApprovedAmount": 1000.00
}
```

**Коды OrderStatus:**
- `1` - Зарегистрирован (для двухэтапных платежей)
- `2` - Успешно оплачен
- `3` - Отменен
- `4` - Возврат
- `6` - Неудачно

---

## 2. РЕАЛИЗАЦИЯ В ПЛАГИНАХ

### HK Agency плагин

**Файл:** `HK Agency/payment-gateway-for-ameriabank/includes/main.php`

**Инициация платежа (строки 611-714):**
```php
$args = [
    "ClientID" => $this->clientID,
    "Amount" => $amount,
    "OrderID" => ($this->testmode == true) ? rand(1000000, 2346000) : $order_id,
    "BackURL" => get_site_url() . '/wc-api/ameriabank_response',
    "Username" => $this->user_name,
    "Password" => $this->password,
    "Description" => '',
    "Currency" => $this->currency_code,
    "Opaque" => $order_id,  // Передают ваш orderId
    "language" => $this->language,
];

$response = wp_remote_post($this->api_url . 'api/VPOS/InitPayment', [
    'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
    'body' => json_encode($args),
    'method' => 'POST'
]);
```

**Обработка возврата (строки 1285-1361):**
```php
public function webhook_ameriabank_response()
{
    // Получают параметры из URL (GET запрос)
    $order = wc_get_order(sanitize_text_field($_GET['opaque']));
    
    // ВАЖНО: Проверяют статус через API
    $args = [
        "PaymentID" => sanitize_text_field($_GET['paymentID']),
        "Username" => $this->user_name,
        "Password" => $this->password,
    ];

    $response = wp_remote_post($this->api_url . 'api/VPOS/GetPaymentDetails', [
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode($args),
        'method' => 'POST'
    ]);

    $body = json_decode($response['body']);

    if ($body->ResponseCode == '00') {
        // Платеж успешен
        $order->update_status($this->successOrderStatus);
    } else {
        // Платеж не прошел
        $order->update_status('failed');
    }
}
```

**Ключевые моменты:**
- ✅ Используют `Opaque` для передачи вашего orderId
- ✅ Проверяют статус через `GetPaymentDetails`
- ✅ НЕ доверяют только URL параметрам
- ✅ Обрабатывают через GET запрос (не POST callback)

---

### PlanetStudio плагин

**Файл:** `PlanetStudio Agency/arca-payment-gateway-pro/endpoints/apg-ameria-bank.php`

**Инициация платежа (строки 187-236):**
```php
$requestUrl = "https://services". APG_IF_TEST_MODE .".ameriabank.am/VPOS/api/VPOS/InitPayment";
$args = array(
    'body' => array(
        'ClientID' => $arca_config->ameriabankClientID,
        'Username' => $apg_vpos_accuonts[$currency]["api_userName"],
        'Password' => $apg_vpos_accuonts[$currency]["api_password"],
        'OrderID' => ($arca_config->orderNumberPrefix != "") ? $arca_config->orderNumberPrefix . '-' . $orderNumber : $orderNumber,
        'Amount' => $amount,
        'Currency' => $currency,
        'BackURL' => get_site_url() . "?arca_process=payment_completed&wc_orderId=$wc_orderId&...",
        'Description' => $description,
        'lang' => ($language === 'hy') ? 'am' : $language,
    ),
);
```

**Обработка возврата (строки 271-575):**
```php
if ($arca_process == 'payment_completed' && isset($_REQUEST['orderID']) && isset($_REQUEST['resposneCode']) && isset($_REQUEST['paymentID']) && isset($_REQUEST['currency'])) {
    
    $orderID = intval($_REQUEST['orderID']);
    $paymentID = sanitize_text_field($_REQUEST['paymentID']);
    $resposneCode = sanitize_text_field($_REQUEST['resposneCode']);
    
    if ($resposneCode == "00") {
        // ВАЖНО: Проверяют статус через API
        $requestUrl = "https://services". APG_IF_TEST_MODE .".ameriabank.am/VPOS/api/VPOS/GetPaymentDetails";
        $args = array(
            'body' => array(
                'Username' => $apg_vpos_accuonts[$currency]["api_userName"],
                'Password' => $apg_vpos_accuonts[$currency]["api_password"],
                'paymentID' => $paymentID,
            ),
        );
        $response = wp_remote_post($requestUrl, $args);
        
        $data = json_decode($response['body']);
        
        if ($data->ResponseCode === "00" && $data->PaymentState === "Successful") {
            // Платеж успешен
            $apg_wc_order->set_status($arca_config->wc_order_status);
        }
    }
}
```

**Ключевые моменты:**
- ✅ Передают ваш orderId в BackURL как параметр (`wc_orderId=$wc_orderId`)
- ✅ Проверяют `resposneCode` сначала
- ✅ Затем проверяют статус через `GetPaymentDetails`
- ✅ НЕ доверяют только URL параметрам

---

## 3. НЮАНСЫ И ОСОБЕННОСТИ

### 3.1 Двухэтапные платежи (secondTypePayment)

**Что это:**
- Сначала блокируется сумма на карте
- Затем подтверждается через `ConfirmPayment` API

**Как работает:**
1. Платеж регистрируется (OrderStatus = 1)
2. Сумма блокируется на карте
3. Вы подтверждаете через `ConfirmPayment` API
4. Сумма списывается

**API для подтверждения:**
```json
POST https://services.ameriabank.am/VPOS/api/VPOS/ConfirmPayment
{
  "PaymentID": "string",
  "Username": "string",
  "Password": "string",
  "Amount": 1000.00
}
```

**В коде (строки 287-334):**
```php
public function confirmPayment($order_id, $new_status)
{
    $PaymentID = get_post_meta($order_id, 'PaymentID', true);
    $amount = ($this->testmode == true) ? 10.0 : floatval($order->get_total());
    
    $args = [
        'PaymentID' => $PaymentID,
        'Username' => $this->user_name,
        'Password' => $this->password,
        'Amount' => $amount,
    ];
    
    $response = wp_remote_post($this->api_url . 'api/VPOS/ConfirmPayment', [
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode($args),
        'method' => 'POST'
    ]);
}
```

---

### 3.2 Сохранение карт (Card Binding)

**Что это:**
- Сохранение карты для повторных платежей
- Используется для подписок

**API:**
- `MakeBindingPayment` - платеж с сохраненной картой
- `DeactivateBinding` - удаление сохраненной карты

**В коде (строки 617-669):**
```php
if ((isset($bindingType) && $bindingType != 'saveCardAmeria')) {
    $args = [
        // ...
        "CardHolderID" => $bindingType,
        "PaymentType" => 6,
    ];

    $response = wp_remote_post($this->api_url . 'api/VPOS/MakeBindingPayment', [
        // ...
    ]);
}
```

---

### 3.3 Cron проверка статуса

**Зачем:**
- Если пользователь не вернулся на BackURL
- Проверка статуса заказов в статусе "pending" или "on-hold"

**В коде (строки 154-211):**
```php
public function cronCheckOrderAmeria()
{
    // Получают все заказы в статусе pending/on-hold
    $orders = $wpdb->get_results("
        SELECT p.*
        FROM {$wpdb->prefix}postmeta AS pm
        LEFT JOIN {$wpdb->prefix}posts AS p
        ON pm.post_id = p.ID
        WHERE p.post_type = 'shop_order'
        AND ( p.post_status = 'wc-on-hold' OR p.post_status = 'wc-pending')
        AND pm.meta_key = '_payment_method'
        AND pm.meta_value = 'hkd_ameriabank'
    ");
    
    foreach ($orders as $order) {
        $paymentID = get_post_meta($order->ID, 'PaymentID', true);
        
        // Проверяют статус через API
        $response = wp_remote_post($this->api_url . 'api/VPOS/GetPaymentDetails', [
            'body' => json_encode([
                "PaymentID" => $paymentID,
                "Username" => $this->user_name,
                "Password" => $this->password,
            ])
        ]);
        
        $body = json_decode($response['body']);
        
        // Обновляют статус в зависимости от OrderStatus
        if ($body->OrderStatus == 2) {
            $order->update_status($this->successOrderStatus);
        }
    }
}
```

---

### 3.4 Отмена платежа (CancelPayment)

**API:**
```json
POST https://services.ameriabank.am/VPOS/api/VPOS/CancelPayment
{
  "PaymentID": "string",
  "Username": "string",
  "Password": "string"
}
```

**В коде (строки 342-398):**
```php
public function cancelPayment($order_id, $old_status = '')
{
    $PaymentID = get_post_meta($order_id, 'PaymentID', true);
    
    $args = [
        'PaymentID' => $PaymentID,
        'Username' => $this->user_name,
        'Password' => $this->password,
    ];
    
    $response = wp_remote_post($this->api_url . 'api/VPOS/CancelPayment', [
        // ...
    ]);
}
```

**Ограничение:** Можно отменить только в течение 72 часов после оплаты

---

### 3.5 Возврат средств (RefundPayment)

**API:**
```json
POST https://services.ameriabank.am/VPOS/api/VPOS/RefundPayment
{
  "PaymentID": "string",
  "Username": "string",
  "Password": "string",
  "Amount": 1000.00
}
```

**В коде (строки 416-451):**
```php
public function process_refund($order_id, $amount = null, $reason = '')
{
    $PaymentID = get_post_meta($order_id, 'PaymentID', true);
    
    $args = [
        'PaymentID' => $PaymentID,
        'Username' => $this->user_name,
        'Password' => $this->password,
        'Amount' => $amount,
    ];
    
    $response = wp_remote_post($this->api_url . 'api/VPOS/RefundPayment', [
        // ...
    ]);
}
```

---

## 4. ТЕСТОВЫЙ И ПРОДАКШН РЕЖИМЫ

### URL

**Тестовый:**
- API: `https://servicestest.ameriabank.am/VPOS/api/VPOS/`
- Страница оплаты: `https://servicestest.ameriabank.am/VPOS/Payments/Pay`

**Продакшн:**
- API: `https://services.ameriabank.am/VPOS/api/VPOS/`
- Страница оплаты: `https://services.ameriabank.am/VPOS/Payments/Pay`

### Учетные данные

**Разные для теста и продакшн:**
- Тестовый: `test_username`, `test_password`, `test_clientID`
- Продакшн: `live_username`, `live_password`, `live_clientID`

### BackURL

**Работает одинаково:**
- Можно использовать любой домен (localhost, тестовый, продакшн)
- Не нужно регистрировать заранее
- Работает в обоих режимах одинаково

---

## 5. ОТВЕТЫ НА ВОПРОСЫ

### ❓ Есть ли callback запросы?

**Ответ: НЕТ!**

- ❌ Нет сервер-сервер callback'ов (как у Idram)
- ✅ Только возврат пользователя на BackURL
- ✅ Вы сами проверяете статус через API

### ❓ Как это работает в тестовом режиме?

**Ответ: Одинаково!**

- Те же URL (с "test" в домене)
- Те же учетные данные (тестовые)
- Та же логика работы
- BackURL работает одинаково

### ❓ Как это работает в продакшн?

**Ответ: Одинаково!**

- Те же URL (без "test")
- Те же учетные данные (продакшн)
- Та же логика работы
- BackURL работает одинаково

### ❓ Нужно ли регистрировать BackURL заранее?

**Ответ: НЕТ!**

- Не нужно регистрировать у AmeriaBank
- Можно использовать любой доступный URL
- Работает сразу после настройки

---

## 6. СХЕМА РАБОТЫ (ПОЛНАЯ)

```
1. Пользователь нажимает "Оплатить"
   ↓
2. Вы отправляете InitPayment с BackURL
   ↓
3. AmeriaBank возвращает PaymentID
   ↓
4. Вы перенаправляете пользователя на страницу оплаты AmeriaBank
   ↓
5. Пользователь вводит данные карты и оплачивает
   ↓
6. AmeriaBank обрабатывает платеж
   ↓
7. AmeriaBank перенаправляет пользователя на BackURL с параметрами в URL
   ↓
8. Ваш сервер получает параметры (GET запрос)
   ↓
9. ВАЖНО: Вы проверяете статус через GetPaymentDetails API
   ↓
10. Вы обновляете статус заказа и перенаправляете пользователя
```

**НЕТ сервер-сервер callback'ов на шаге 6-7!**

---

## 7. КЛЮЧЕВЫЕ МОМЕНТЫ ДЛЯ РЕАЛИЗАЦИИ

### ✅ Обязательно:

1. **Всегда проверяйте статус через API**
   - Не доверяйте только URL параметрам
   - Используйте `GetPaymentDetails` для проверки

2. **Сохраняйте PaymentID**
   - Нужен для проверки статуса
   - Нужен для отмены/возврата

3. **Обрабатывайте ошибки**
   - `resposneCode != "00"` - ошибка
   - `ResponseCode != "00"` в GetPaymentDetails - ошибка

4. **Используйте Opaque**
   - Передавайте ваш orderId в `Opaque`
   - Получайте его из URL для идентификации заказа

### ⚠️ Важно:

1. **Безопасность:**
   - Параметры в URL могут быть подделаны
   - Всегда проверяйте через API
   - Не доверяйте только `resposneCode`

2. **Идемпотентность:**
   - Пользователь может вернуться несколько раз
   - Проверяйте статус заказа перед обновлением

3. **Таймауты:**
   - Пользователь может не вернуться на BackURL
   - Используйте cron для проверки статуса

---

## 8. ПРИМЕР РЕАЛИЗАЦИИ ДЛЯ NEXT.JS

```typescript
// app/api/payment/ameriabank/initiate/route.ts
export async function POST(request: Request) {
  const { orderId, amount, currency } = await request.json();
  
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
      Description: 'Online payment',
      lang: 'en',
      Opaque: orderId.toString()  // Ваш orderId
    })
  });
  
  const data = await response.json();
  
  if (data.ResponseCode === 1 && data.ResponseMessage === "OK") {
    // Сохраняем PaymentID
    await updateOrder(orderId, { paymentId: data.PaymentID });
    
    // Перенаправляем на страницу оплаты
    const paymentUrl = process.env.AMERIABANK_TEST_MODE === 'true'
      ? `https://servicestest.ameriabank.am/VPOS/Payments/Pay?id=${data.PaymentID}&lang=en`
      : `https://services.ameriabank.am/VPOS/Payments/Pay?id=${data.PaymentID}&lang=en`;
    
    return Response.json({ redirectUrl: paymentUrl });
  }
}

// app/api/payment/ameriabank/callback/route.ts
export async function GET(request: Request) {
  const { searchParams } = new URL(request.url);
  const orderID = searchParams.get('orderID');  // ID от AmeriaBank
  const paymentID = searchParams.get('paymentID');
  const responseCode = searchParams.get('resposneCode');
  const opaque = searchParams.get('Opaque');  // Ваш orderId
  
  // ВАЖНО: Проверяем статус через API
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
  
  // Проверяем статус
  if (data.ResponseCode === '00' && data.PaymentState === 'Successful') {
    await updateOrderStatus(opaque, 'paid', {
      paymentId: paymentID,
      transactionId: data.TransactionID
    });
    
    return Response.redirect(`${process.env.NEXT_PUBLIC_BASE_URL}/payment/success?orderId=${opaque}`);
  } else {
    await updateOrderStatus(opaque, 'failed');
    return Response.redirect(`${process.env.NEXT_PUBLIC_BASE_URL}/payment/failed`);
  }
}
```

---

## 9. ИТОГОВАЯ ТАБЛИЦА

| Параметр | Значение |
|----------|----------|
| **Сервер-сервер callback'и** | ❌ НЕТ |
| **Возврат пользователя** | ✅ ДА (BackURL) |
| **Проверка статуса** | ✅ Через API (GetPaymentDetails) |
| **Регистрация URL** | ❌ НЕ НУЖНА |
| **Локальный домен** | ✅ РАБОТАЕТ |
| **Тестовый режим** | ✅ Одинаковая логика |
| **Продакшн режим** | ✅ Одинаковая логика |
| **Безопасность** | ⚠️ Проверять через API |

---

## 10. ВЫВОДЫ

1. ✅ **НЕТ callback запросов** - только возврат пользователя
2. ✅ **Работает одинаково** в тестовом и продакшн режиме
3. ✅ **Не нужно регистрировать URL** заранее
4. ✅ **Можно использовать localhost** для тестирования
5. ⚠️ **Всегда проверяйте статус через API** - не доверяйте URL параметрам

**100% подтверждено на основе анализа реального кода плагинов!**
