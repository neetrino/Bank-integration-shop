# Ameriabank: проверка статусов и требования к тестам

## 1. Что говорит документация vPOS - Ameriabank.md

В документе описаны **основные операции** (не формулировка «для прохождения теста», а полный протокол):

| Операция | Метод API | Описание |
|----------|-----------|----------|
| **Создать платёж** | InitPayment | Регистрация заказа, получение PaymentID, редирект на форму оплаты |
| **Получить статус** | GetPaymentDetails | После возврата с оплаты — запрос статуса по PaymentID (обязателен по документу: «To complete the transaction it is necessary to send GetPaymentDetails request») |
| **Подтвердить (two-stage)** | ConfirmPayment | Второй этап при предавторизации — списание суммы |
| **Отменить** | CancelPayment | Отмена платежа (до 72 часов с момента инициализации) |
| **Вернуть** | RefundPayment | Возврат суммы (полный или частичный) |

Документ явно **не пишет** фразу вроде «для прохождения теста нужно сделать create, cancel, refund». Но в нём перечислены именно эти сценарии, и банки при приёме интеграции обычно просят **продемонстрировать**:

- создание платежа (InitPayment + оплата);
- получение статуса (GetPaymentDetails);
- отмену (CancelPayment);
- возврат (RefundPayment).

То есть да: по духу документа и типичной практике теста — «создать, отменить, вернуть» (плюс запрос статуса) как раз те сценарии, которые имеют смысл проверить.

---

## 2. Проверка статусов через терминал или Postman

### 2.1 Статусы заказов в нашем приложении

**Список заказов пользователя (нужна авторизация):**

```bash
# Сначала залогиньтесь в браузере на http://localhost:3000/login,
# затем скопируйте cookie next-auth.session-token из DevTools → Application → Cookies

curl -s "http://localhost:3000/api/orders" \
  -H "Cookie: next-auth.session-token=ВАШ_ТОКЕН"
```

**Список заказов в админке (роль ADMIN):**

```bash
curl -s "http://localhost:3000/api/admin/orders?page=1&limit=20" \
  -H "Cookie: next-auth.session-token=ВАШ_ТОКЕН"
```

В ответе у каждого заказа есть `status`, `paymentStatus`, `paymentTransactionId` (PaymentID банка для Ameria).

---

### 2.2 Статус платежа в Ameriabank (GetPaymentDetails)

Прямой запрос в банк по **PaymentID** (его можно взять из заказа: `paymentTransactionId`, или из callback после оплаты).

**Тест (servicestest.ameriabank.am):**

```bash
curl -s -X POST "https://servicestest.ameriabank.am/VPOS/api/VPOS/GetPaymentDetails" \
  -H "Content-Type: application/json" \
  -d '{
    "PaymentID": "СЮДА_PAYMENT_ID_ИЗ_ЗАКАЗА",
    "Username": "ВАШ_AMERIA_USERNAME",
    "Password": "ВАШ_AMERIA_PASSWORD"
  }'
```

В ответе будут, в частности:

- `ResponseCode` — "00" = успех
- `OrderStatus` — 0/1/2/3/4/6 (см. Table 2 в документе: 1 = approved, 2 = deposited и т.д.)
- `PaymentState`, `Amount`, `DepositedAmount` и др.

**Postman:**  
Method: POST  
URL: `https://servicestest.ameriabank.am/VPOS/api/VPOS/GetPaymentDetails`  
Body → raw → JSON — те же три поля: `PaymentID`, `Username`, `Password`.

---

### 2.3 Отмена платежа (CancelPayment) — для теста

Из документации: доступна в течение 72 часов с момента инициализации.

```bash
curl -s -X POST "https://servicestest.ameriabank.am/VPOS/api/VPOS/CancelPayment" \
  -H "Content-Type: application/json" \
  -d '{
    "PaymentID": "СЮДА_PAYMENT_ID",
    "Username": "ВАШ_AMERIA_USERNAME",
    "Password": "ВАШ_AMERIA_PASSWORD"
  }'
```

Успех: `ResponseCode: "00"`.

---

### 2.4 Возврат (RefundPayment) — для теста

После успешной оплаты (OrderStatus 2 или подтверждённый платёж):

```bash
curl -s -X POST "https://servicestest.ameriabank.am/VPOS/api/VPOS/RefundPayment" \
  -H "Content-Type: application/json" \
  -d '{
    "PaymentID": "СЮДА_PAYMENT_ID",
    "Username": "ВАШ_AMERIA_USERNAME",
    "Password": "ВАШ_AMERIA_PASSWORD",
    "Amount": 10
  }'
```

В тесте сумма должна соответствовать правилам (например, 10 AMD). Успех: `ResponseCode: "00"`.

---

## 3. Итог

- **Документация vPOS - Ameriabank.md** описывает все операции (создать, получить статус, подтвердить, отменить, вернуть); отдельной фразы «для прохождения теста сделайте то-то» в ней нет, но по смыслу и практике теста как раз нужны сценарии: **создать → получить статус → отменить и/или вернуть**.
- **Проверка статусов:**  
  - наши заказы — через `GET /api/orders` или `GET /api/admin/orders` (с сессией);  
  - статус платежа в банке — через **GetPaymentDetails** по PaymentID (терминал или Postman, как выше).
- Для **прохождения теста Ameriabank** имеет смысл уметь выполнять и проверять: InitPayment (создание), GetPaymentDetails (статус), CancelPayment (отмена), RefundPayment (возврат) — всё это есть в документе и в нашем коде (в т.ч. через админку: возврат/отмена по заказу).
