# IDram Payment Integration

Модуль интеграции платежной системы IDram для Next.js.

## Документация

- Руководство: `example-Vpos/IDRAM_INTEGRATION_GUIDE.md`
- Официальный API: `example-Vpos/Documentation/Idram/IdramMerchantAPI_16102025.md`
- Callback и домены: `example-Vpos/Documentation/Other/IDRAM_CALLBACK_AND_DOMAINS.md`

## Особенности

- **3 URL** регистрируются у Idram: RESULT_URL, SUCCESS_URL, FAIL_URL
- **2 POST-запроса** на RESULT_URL: precheck (EDP_PRECHECK=YES) и подтверждение платежа
- **Подпись:** EDP_CHECKSUM = MD5(EDP_REC_ACCOUNT:EDP_AMOUNT:SECRET_KEY:EDP_BILL_NO:EDP_PAYER_ACCOUNT:EDP_TRANS_ID:EDP_TRANS_DATE)
- Для проверки подписи использовать **сумму из БД** (заказа), не из запроса
- Ответ на callback: строго **"OK"**, HTTP 200, Content-Type: text/plain
- **Валюта:** только AMD

## Переменные окружения

- `IDRAM_TEST_MODE` — true для теста
- `IDRAM_TEST_REC_ACCOUNT`, `IDRAM_TEST_SECRET_KEY` — тестовые
- `IDRAM_REC_ACCOUNT`, `IDRAM_SECRET_KEY` — продакшн
- `IDRAM_EMAIL` — опционально
- RESULT_URL, SUCCESS_URL, FAIL_URL регистрируются у Idram (используется base URL приложения)

## API Routes

- `POST /api/payments/idram/init` — возвращает formUrl и formData для отправки формы на Idram
- `POST /api/payments/idram/callback` — RESULT_URL, обрабатывает precheck и подтверждение платежа

## Регистрация URL у Idram

Зарегистрируйте у технического персонала Idram:

- **RESULT_URL:** `https://yourdomain.com/api/payments/idram/callback`
- **SUCCESS_URL:** `https://yourdomain.com/order-success?clearCart=true` (Idram может добавить EDP_BILL_NO)
- **FAIL_URL:** `https://yourdomain.com/order-success?error=payment_failed`

Для локального тестирования нужен публичный URL (ngrok, Vercel preview): localhost для callback не подходит.
