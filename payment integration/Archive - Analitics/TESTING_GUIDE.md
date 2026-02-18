# Руководство по тестированию платежных систем

## Общие принципы тестирования

Все три платежные системы поддерживают **тестовый и продакшн режимы**. 

**Важно понимать:**
- ✅ **Тестовый режим** - использует тестовые учетные данные и не списывает реальные деньги
- ✅ **Продакшн режим** - использует реальные учетные данные и списывает реальные деньги
- ✅ **Один и тот же код** - переключение происходит через переменные окружения или настройки
- ✅ **Разные URL** - у AmeriaBank и Arca разные URL для теста и продакшн
- ✅ **Разные учетные данные** - у всех систем отдельные тестовые и продакшн ключи

---

## 1. IDRAM - Тестирование

### 1.1 Как это работает

**Idram использует ОДИН И ТОТ ЖЕ URL** для теста и продакшн:
- URL: `https://banking.idram.am/Payment/GetPayment` (одинаковый для обоих режимов)

**Разница только в учетных данных:**
- Тестовый режим: `test_edp_rec_account` + `test_secret_key`
- Продакшн режим: `live_edp_rec_account` + `live_secret_key`

### 1.2 Получение тестовых данных

**Как получить тестовые данные:**
1. Связаться с техническим персоналом Idram
2. Запросить тестовый аккаунт (test EDP_REC_ACCOUNT и test SECRET_KEY)
3. Они предоставят тестовые учетные данные

**Важно:** Тестовые данные нужно запросить у Idram - они не публичные!

### 1.3 Настройка в Next.js

```typescript
// .env.local (для разработки)
IDRAM_TEST_MODE=true
IDRAM_TEST_REC_ACCOUNT=100000114  # Тестовый IdramID от Idram
IDRAM_TEST_SECRET_KEY=test_secret_key_here  # Тестовый ключ от Idram

IDRAM_LIVE_REC_ACCOUNT=100000115  # Продакшн IdramID
IDRAM_LIVE_SECRET_KEY=live_secret_key_here  # Продакшн ключ

// .env.production (для продакшн)
IDRAM_TEST_MODE=false
IDRAM_LIVE_REC_ACCOUNT=100000115
IDRAM_LIVE_SECRET_KEY=live_secret_key_here
```

### 1.4 Реализация переключения

```typescript
// lib/services/payment/idram/IdramService.ts
export class IdramService {
  private isTestMode: boolean;
  private recAccount: string;
  private secretKey: string;
  private resultUrl: string;
  private successUrl: string;
  private failUrl: string;

  constructor() {
    // Определяем режим из переменных окружения
    this.isTestMode = process.env.IDRAM_TEST_MODE === 'true';
    
    // Выбираем учетные данные в зависимости от режима
    this.recAccount = this.isTestMode
      ? process.env.IDRAM_TEST_REC_ACCOUNT!
      : process.env.IDRAM_LIVE_REC_ACCOUNT!;
    
    this.secretKey = this.isTestMode
      ? process.env.IDRAM_TEST_SECRET_KEY!
      : process.env.IDRAM_LIVE_SECRET_KEY!;
    
    // URL одинаковые для обоих режимов
    this.resultUrl = `${process.env.NEXT_PUBLIC_BASE_URL}/api/payment/idram/callback`;
    this.successUrl = `${process.env.NEXT_PUBLIC_BASE_URL}/payment/success`;
    this.failUrl = `${process.env.NEXT_PUBLIC_BASE_URL}/payment/failed`;
  }

  async initiatePayment(params: PaymentParams) {
    // URL одинаковый для теста и продакшн
    const formUrl = 'https://banking.idram.am/Payment/GetPayment';
    
    return {
      formUrl,
      formData: {
        EDP_LANGUAGE: 'EN',
        EDP_REC_ACCOUNT: this.recAccount,  // Меняется в зависимости от режима
        EDP_DESCRIPTION: params.description,
        EDP_AMOUNT: params.amount.toFixed(2),
        EDP_BILL_NO: params.orderId.toString(),
      }
    };
  }

  verifyChecksum(data: CallbackData, orderAmount: number): boolean {
    const stringToHash = [
      this.recAccount,
      orderAmount.toFixed(2),
      this.secretKey,  // Используется правильный ключ в зависимости от режима
      data.EDP_BILL_NO,
      data.EDP_PAYER_ACCOUNT,
      data.EDP_TRANS_ID,
      data.EDP_TRANS_DATE
    ].join(':');
    
    const calculatedChecksum = crypto
      .createHash('md5')
      .update(stringToHash)
      .digest('hex')
      .toUpperCase();
    
    return calculatedChecksum === data.EDP_CHECKSUM.toUpperCase();
  }
}
```

### 1.5 Как проверить, что работает

1. **Включите тестовый режим:**
   ```bash
   IDRAM_TEST_MODE=true
   ```

2. **Используйте тестовые учетные данные** от Idram

3. **Создайте тестовый заказ** с небольшой суммой

4. **Проверьте:**
   - Форма отправляется на `https://banking.idram.am/Payment/GetPayment`
   - Используется `test_edp_rec_account`
   - Callback приходит с тестовыми данными
   - Деньги НЕ списываются реально

5. **Проверьте логи:**
   ```typescript
   console.log('Idram Test Mode:', this.isTestMode);
   console.log('Using Account:', this.recAccount);
   ```

### 1.6 Переход на продакшн

1. **Измените переменные окружения:**
   ```bash
   IDRAM_TEST_MODE=false
   ```

2. **Используйте продакшн учетные данные:**
   ```bash
   IDRAM_LIVE_REC_ACCOUNT=your_live_account
   IDRAM_LIVE_SECRET_KEY=your_live_secret_key
   ```

3. **URL остается тот же** - `https://banking.idram.am/Payment/GetPayment`

---

## 2. AMERIABANK - Тестирование

### 2.1 Как это работает

**AmeriaBank использует РАЗНЫЕ URL** для теста и продакшн:
- Тестовый URL: `https://servicestest.ameriabank.am/VPOS/api/VPOS/InitPayment`
- Продакшн URL: `https://services.ameriabank.am/VPOS/api/VPOS/InitPayment`

**Также разные учетные данные:**
- Тестовый режим: `test_username` + `test_password` + `test_clientID`
- Продакшн режим: `live_username` + `live_password` + `live_clientID`

### 2.2 Получение тестовых данных

**Как получить тестовые данные:**
1. Обратиться в AmeriaBank
2. Запросить доступ к тестовой среде vPOS
3. Получить тестовые `Username`, `Password`, `ClientID`

**Документация:** `https://servicestest.ameriabank.am/VPOS/help`

### 2.3 Настройка в Next.js

```typescript
// .env.local (для разработки)
AMERIABANK_TEST_MODE=true
AMERIABANK_TEST_USERNAME=test_username
AMERIABANK_TEST_PASSWORD=test_password
AMERIABANK_TEST_CLIENT_ID=test_client_id

AMERIABANK_LIVE_USERNAME=live_username
AMERIABANK_LIVE_PASSWORD=live_password
AMERIABANK_LIVE_CLIENT_ID=live_client_id

// .env.production
AMERIABANK_TEST_MODE=false
AMERIABANK_LIVE_USERNAME=live_username
AMERIABANK_LIVE_PASSWORD=live_password
AMERIABANK_LIVE_CLIENT_ID=live_client_id
```

### 2.4 Реализация переключения

```typescript
// lib/services/payment/ameriabank/AmeriaBankService.ts
export class AmeriaBankService {
  private isTestMode: boolean;
  private baseUrl: string;
  private username: string;
  private password: string;
  private clientId: string;

  constructor() {
    this.isTestMode = process.env.AMERIABANK_TEST_MODE === 'true';
    
    // РАЗНЫЕ URL для теста и продакшн
    this.baseUrl = this.isTestMode
      ? 'https://servicestest.ameriabank.am/VPOS/api/VPOS'
      : 'https://services.ameriabank.am/VPOS/api/VPOS';
    
    // РАЗНЫЕ учетные данные
    this.username = this.isTestMode
      ? process.env.AMERIABANK_TEST_USERNAME!
      : process.env.AMERIABANK_LIVE_USERNAME!;
    
    this.password = this.isTestMode
      ? process.env.AMERIABANK_TEST_PASSWORD!
      : process.env.AMERIABANK_LIVE_PASSWORD!;
    
    this.clientId = this.isTestMode
      ? process.env.AMERIABANK_TEST_CLIENT_ID!
      : process.env.AMERIABANK_LIVE_CLIENT_ID!;
  }

  async initiatePayment(params: PaymentParams) {
    const response = await fetch(`${this.baseUrl}/InitPayment`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json; charset=utf-8'
      },
      body: JSON.stringify({
        ClientID: this.clientId,
        Username: this.username,
        Password: this.password,
        OrderID: params.orderId.toString(),
        Amount: params.amount,
        Currency: params.currency,
        BackURL: `${process.env.NEXT_PUBLIC_BASE_URL}/api/payment/ameriabank/callback`,
        Description: params.description || 'Online payment',
        lang: 'en'
      })
    });

    return await response.json();
  }

  async getPaymentDetails(paymentId: string) {
    const response = await fetch(`${this.baseUrl}/GetPaymentDetails`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json; charset=utf-8'
      },
      body: JSON.stringify({
        Username: this.username,
        Password: this.password,
        paymentID: paymentId
      })
    });

    return await response.json();
  }
}
```

### 2.5 Как проверить, что работает

1. **Включите тестовый режим:**
   ```bash
   AMERIABANK_TEST_MODE=true
   ```

2. **Используйте тестовые URL и учетные данные**

3. **Проверьте:**
   - Запросы идут на `servicestest.ameriabank.am`
   - Используются тестовые `username`, `password`, `clientID`
   - Деньги НЕ списываются реально

4. **Проверьте логи:**
   ```typescript
   console.log('AmeriaBank Test Mode:', this.isTestMode);
   console.log('Base URL:', this.baseUrl);
   console.log('Username:', this.username);
   ```

### 2.6 Переход на продакшн

1. **Измените переменные окружения:**
   ```bash
   AMERIABANK_TEST_MODE=false
   ```

2. **URL автоматически переключится** на `services.ameriabank.am`

3. **Используйте продакшн учетные данные**

---

## 3. ARCA - Тестирование

### 3.1 Как это работает

**Arca использует РАЗНЫЕ URL и ПОРТЫ** для теста и продакшн:
- Тестовый URL: `https://ipaytest.arca.am:8443/payment/rest/register.do`
- Продакшн URL: `https://ipay.arca.am/payment/rest/register.do`

**Также разные учетные данные:**
- Тестовый режим: `test_username` + `test_password`
- Продакшн режим: `live_username` + `live_password`

**Важно:** Тестовый сервер использует порт `8443` или `8444`

### 3.2 Получение тестовых данных

**Как получить тестовые данные:**
1. Обратиться в Arca
2. Запросить доступ к тестовой среде
3. Получить тестовые `userName` и `password`

### 3.3 Настройка в Next.js

```typescript
// .env.local (для разработки)
ARCA_TEST_MODE=true
ARCA_TEST_USERNAME=test_username
ARCA_TEST_PASSWORD=test_password
ARCA_TEST_PORT=8443

ARCA_LIVE_USERNAME=live_username
ARCA_LIVE_PASSWORD=live_password

// .env.production
ARCA_TEST_MODE=false
ARCA_LIVE_USERNAME=live_username
ARCA_LIVE_PASSWORD=live_password
```

### 3.4 Реализация переключения

```typescript
// lib/services/payment/arca/ArcaService.ts
export class ArcaService {
  private isTestMode: boolean;
  private baseUrl: string;
  private username: string;
  private password: string;

  constructor() {
    this.isTestMode = process.env.ARCA_TEST_MODE === 'true';
    
    // РАЗНЫЕ URL и ПОРТЫ для теста и продакшн
    if (this.isTestMode) {
      const port = process.env.ARCA_TEST_PORT || '8443';
      this.baseUrl = `https://ipaytest.arca.am:${port}/payment/rest`;
    } else {
      this.baseUrl = 'https://ipay.arca.am/payment/rest';
    }
    
    // РАЗНЫЕ учетные данные
    this.username = this.isTestMode
      ? process.env.ARCA_TEST_USERNAME!
      : process.env.ARCA_LIVE_USERNAME!;
    
    this.password = this.isTestMode
      ? process.env.ARCA_TEST_PASSWORD!
      : process.env.ARCA_LIVE_PASSWORD!;
  }

  async registerPayment(params: PaymentParams) {
    const currencyCode = {
      'AMD': '051',
      'USD': '840',
      'EUR': '978'
    }[params.currency] || '051';
    
    const amountInCents = Math.round(params.amount * 100);
    
    const formData = new URLSearchParams();
    formData.append('userName', this.username);
    formData.append('password', this.password);
    formData.append('orderNumber', params.orderId.toString());
    formData.append('amount', amountInCents.toString());
    formData.append('currency', currencyCode);
    formData.append('returnUrl', `${process.env.NEXT_PUBLIC_BASE_URL}/api/payment/arca/callback?orderId=${params.orderId}`);
    formData.append('description', params.description || 'Online payment');
    formData.append('language', 'en');
    
    const response = await fetch(`${this.baseUrl}/register.do`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: formData
    });

    return await response.json();
  }

  async getOrderStatus(orderId: string) {
    const formData = new URLSearchParams();
    formData.append('userName', this.username);
    formData.append('password', this.password);
    formData.append('orderId', orderId);
    
    const response = await fetch(`${this.baseUrl}/getOrderStatusExtended.do`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: formData
    });

    return await response.json();
  }
}
```

### 3.5 Как проверить, что работает

1. **Включите тестовый режим:**
   ```bash
   ARCA_TEST_MODE=true
   ```

2. **Используйте тестовые URL и учетные данные**

3. **Проверьте:**
   - Запросы идут на `ipaytest.arca.am:8443`
   - Используются тестовые `username` и `password`
   - Деньги НЕ списываются реально

4. **Проверьте логи:**
   ```typescript
   console.log('Arca Test Mode:', this.isTestMode);
   console.log('Base URL:', this.baseUrl);
   console.log('Username:', this.username);
   ```

### 3.6 Переход на продакшн

1. **Измените переменные окружения:**
   ```bash
   ARCA_TEST_MODE=false
   ```

2. **URL автоматически переключится** на `ipay.arca.am` (без порта)

3. **Используйте продакшн учетные данные**

---

## Сравнительная таблица

| Система | URL для теста | URL для продакшн | Учетные данные |
|---------|--------------|------------------|-----------------|
| **Idram** | `banking.idram.am` (одинаковый) | `banking.idram.am` (одинаковый) | Разные (test/live) |
| **AmeriaBank** | `servicestest.ameriabank.am` | `services.ameriabank.am` | Разные (test/live) |
| **Arca** | `ipaytest.arca.am:8443` | `ipay.arca.am` | Разные (test/live) |

---

## Рекомендации по тестированию

### 1. Создайте отдельный файл конфигурации

```typescript
// lib/config/payment.config.ts
export const paymentConfig = {
  idram: {
    testMode: process.env.IDRAM_TEST_MODE === 'true',
    testAccount: process.env.IDRAM_TEST_REC_ACCOUNT,
    testSecretKey: process.env.IDRAM_TEST_SECRET_KEY,
    liveAccount: process.env.IDRAM_LIVE_REC_ACCOUNT,
    liveSecretKey: process.env.IDRAM_LIVE_SECRET_KEY,
    formUrl: 'https://banking.idram.am/Payment/GetPayment', // Одинаковый
  },
  ameriabank: {
    testMode: process.env.AMERIABANK_TEST_MODE === 'true',
    testBaseUrl: 'https://servicestest.ameriabank.am/VPOS/api/VPOS',
    liveBaseUrl: 'https://services.ameriabank.am/VPOS/api/VPOS',
    testUsername: process.env.AMERIABANK_TEST_USERNAME,
    testPassword: process.env.AMERIABANK_TEST_PASSWORD,
    testClientId: process.env.AMERIABANK_TEST_CLIENT_ID,
    liveUsername: process.env.AMERIABANK_LIVE_USERNAME,
    livePassword: process.env.AMERIABANK_LIVE_PASSWORD,
    liveClientId: process.env.AMERIABANK_LIVE_CLIENT_ID,
  },
  arca: {
    testMode: process.env.ARCA_TEST_MODE === 'true',
    testBaseUrl: `https://ipaytest.arca.am:${process.env.ARCA_TEST_PORT || '8443'}/payment/rest`,
    liveBaseUrl: 'https://ipay.arca.am/payment/rest',
    testUsername: process.env.ARCA_TEST_USERNAME,
    testPassword: process.env.ARCA_TEST_PASSWORD,
    liveUsername: process.env.ARCA_LIVE_USERNAME,
    livePassword: process.env.ARCA_LIVE_PASSWORD,
  },
};
```

### 2. Добавьте визуальную индикацию тестового режима

```typescript
// components/PaymentButton.tsx
export function PaymentButton() {
  const isTestMode = process.env.NEXT_PUBLIC_PAYMENT_TEST_MODE === 'true';
  
  return (
    <div>
      {isTestMode && (
        <div className="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-2 rounded mb-4">
          ⚠️ ТЕСТОВЫЙ РЕЖИМ - Реальные деньги не списываются
        </div>
      )}
      <button>Оплатить</button>
    </div>
  );
}
```

### 3. Логируйте режим в каждом запросе

```typescript
// lib/services/payment/base/BasePaymentService.ts
export abstract class BasePaymentService {
  protected logPayment(action: string, data: any) {
    console.log(`[${this.constructor.name}] ${action}`, {
      testMode: this.isTestMode,
      ...data
    });
  }
}
```

### 4. Защитите продакшн от случайного включения теста

```typescript
// lib/services/payment/PaymentService.ts
export class PaymentService {
  constructor() {
    // В продакшн принудительно отключаем тестовый режим
    if (process.env.NODE_ENV === 'production') {
      if (process.env.IDRAM_TEST_MODE === 'true') {
        throw new Error('Test mode cannot be enabled in production!');
      }
    }
  }
}
```

---

## Чек-лист перед переходом на продакшн

- [ ] Все тестовые платежи прошли успешно
- [ ] Callback'и обрабатываются корректно
- [ ] Проверка подписей работает правильно
- [ ] Логирование включено и работает
- [ ] Переменные окружения настроены для продакшн
- [ ] Тестовый режим отключен (`TEST_MODE=false`)
- [ ] Используются продакшн учетные данные
- [ ] URL переключены на продакшн (для AmeriaBank и Arca)
- [ ] Проверена работа в продакшн с минимальной суммой

---

## Важные замечания

1. **Idram:** Один URL, но разные учетные данные - будьте внимательны!
2. **AmeriaBank:** Разные URL - автоматически переключается через код
3. **Arca:** Разные URL и порты - не забудьте про порт в тестовом режиме
4. **Безопасность:** Никогда не коммитьте учетные данные в Git
5. **Тестирование:** Всегда тестируйте в тестовом режиме перед продакшн
