# E-HDM: полная картина и чеклист

> **Проверено:** скриншоты плагина (6 шагов) и официальная документация ПЕК (uc_hhpek_electronic_HDM_integration_manual_11_2024). Ниже — что изучено, что сделано, что осталось, как всё работает.

---

## 1. Что изучено

### 1.1. Скриншоты плагина WordPress (virtual-hdm-for-taxservice-am)

Изучены **все 6 скриншотов** из `Vpos - Doc/HK Agency/virtual-hdm-for-taxservice-am/assets/` (screenshot-1.png … screenshot-6.png):

| Шаг | Экран | Данные | Учтено в проекте |
|-----|--------|--------|-------------------|
| **1** | Նույնականացում (Идентификация) | TAX-SER (лицензия плагина), срок действия | Не нужно для API ПЕК — мы используем .crt + .key. |
| **2** | Կազմակերպության Նույնականացում | TIN, CRN, загрузка .jks | **EHDM_TIN**, **EHDM_CRN**; сертификат в Private/ (.crt + .key). |
| **3** | Հարկման կարգավորում | Тип налога (с НДС), Բաժին 1-1, ԱԱՀ 16.67%, Գանձապահ 1 | **EHDM_DEP=1**, **EHDM_CASHIER_ID=1**. |
| **4** | Ապրանքների կարգավորում | Код АТГ товара (0901 в примере), единица Հատ; доставка: АТГ 49.42, код 007, единица Հատ | **EHDM_DEFAULT_ADG_CODE=2201**, **EHDM_DEFAULT_UNIT=Հատ**; доставка: **EHDM_SHIPPING_*** (enabled, adg, goodCode, description, unit). |
| **5** | Կտրոնի տպման կարգավորում | Шлюз оплаты, автопечать при «Processing» | Логика: вызывать print после успешной оплаты. |
| **6** | Пример чека в письме | TIN, CRN, SN, Fiscal, ԱՏԳ, кассир, Բաժին | Подтверждает соответствие наших реквизитов. |

### 1.2. Официальная документация ПЕК (EHDM)

Изучена документация в `Vpos - Doc/Doc from Bank/EHDM/uc_hhpek_electronic_HDM_integration_manual_11_2024_6734a3be6964d.html`:

- **Аутентификация:** клиентский сертификат (mutual TLS), не логин/пароль.
- **Методы:** checkConnection (проверка связи), activate (активация), configureDepartments (настройка отделов/налога), getGoodList (номенклатура, опционально), **print** (печать чека), printCopy, getReturnedReceiptInfo, printReturnReceipt.
- **print (режим 2 — товары):**
  - URL: POST `/api/v1.0/print`
  - Тело: **crn**, **seq**, **cardAmount** / **cashAmount** (безнал/наличные, в драммах), **partialAmount**, **prePaymentAmount** (можно 0), **cashierId**, **mode** (2 = товары), **partnerTin** (null или ИНН покупателя), **items** — массив позиций.
  - Каждый **item:** **adgCode**, **dep**, **goodCode** (макс. 50 символов, не пустой), **goodName** (макс. 50, не пустой), **quantity**, **unit**, **price**; опционально discount, discountType, additionalDiscount, additionalDiscountType.
  - Деньги: 2 знака после запятой (лума = 0,01 драма); количество — до 3 знаков.
  - **seq:** первый запрос — любое число; все последующие — строго предыдущий + 1; хранить в ПЕК после configureDepartments и после каждого успешного запроса.
- **configureDepartments:** один раз (или при смене режимов) передать crn, seq, departments: [{ dep, taxRegime }]. После этого dep можно использовать в print. У клиента уже сделан при настройке E-HDM в WordPress.

---

## 2. Как это работает (полная картина)

```
1. Клиент оформляет заказ и оплачивает (Idram / Ameria / и т.д.).
2. Ваш бэкенд получает подтверждение оплаты (webhook или проверка статуса).
3. Статус заказа → «оплачен» (или «в обработке», как в плагине — шаг 5).
4. Бэкенд формирует тело для POST /api/v1.0/print:
   - crn, seq (взять из хранилища и затем увеличить на 1),
   - mode: 2,
   - cashierId, cardAmount/cashAmount (по способу оплаты), partialAmount: 0, prePaymentAmount: 0,
   - partnerTin: null (или ИНН покупателя, если нужен чек с ИНН),
   - items: для каждой позиции заказа — dep, adgCode, goodCode, goodName, quantity, unit, price;
     при необходимости — отдельная позиция «доставка» (adgCode, goodCode, goodName, quantity: 1, unit, price).
5. Запрос отправляется по HTTPS с клиентским сертификатом (.crt + .key + passphrase).
6. ПЕК возвращает result (receiptId, qr, sn, fiscal, total и т.д.) или ошибку.
7. При успехе: сохранить новый seq (предыдущий + 1), сохранить результат чека в БД; при желании — отправить клиенту (email, страница заказа).
```

E-HDM **не принимает платежи** — только регистрирует чек после того, как оплата уже прошла у платёжной системы.

---

## 3. Что уже сделано (конфиг и документы)

| Что | Где |
|-----|-----|
| CRN, TIN, пути к .crt и .key, passphrase | .env (EHDM_CRN, EHDM_TIN, EHDM_CERT_PATH, EHDM_KEY_PATH, EHDM_KEY_PASSPHRASE) |
| Начальный seq | .env EHDM_INITIAL_SEQ=65 |
| Код АТГ по умолчанию | .env EHDM_DEFAULT_ADG_CODE=2201 |
| Режим налога (dep) | .env EHDM_DEP=1 |
| Единица по умолчанию | .env EHDM_DEFAULT_UNIT=Հատ |
| ID кассира | .env EHDM_CASHIER_ID=1 |
| Доставка в чеке | .env EHDM_SHIPPING_ENABLED, EHDM_SHIPPING_ADG_CODE, EHDM_SHIPPING_GOOD_CODE, EHDM_SHIPPING_DESCRIPTION, EHDM_SHIPPING_UNIT |
| Дублирование для справки | Private/ehdm-config.txt |
| Пример переменных (без секретов) | .env.example |
| Документация | E-HDM_ИЗУЧЕНИЕ_И_ПЛАН_РЕАЛИЗАЦИИ.md, E-HDM_ФАЙЛЫ_И_ПЕРЕНОС_ИЗ_WORDPRESS.md, E-HDM_КАК_РАБОТАЕТ_И_КОДЫ_ТОВАРОВ.md, раздел по скриншотам в E-HDM_ФАЙЛЫ_И_ПЕРЕНОС_ИЗ_WORDPRESS.md |

---

## 4. Что из документации мы ещё не реализовали в коде

- **Хранение и увеличение seq** — в БД (модель или ключ в настройках), брать при каждом print и увеличивать после успешного ответа.
- **Сервис вызова API ПЕК** — HTTPS POST с mutual TLS (загрузка .crt и .key из путей в .env, passphrase), формирование тела print из заказа (items из order items + при EHDM_SHIPPING_ENABLED одна позиция доставки).
- **Маппинг заказа → items:** для каждой OrderItem: dep из EHDM_DEP, adgCode из EHDM_DEFAULT_ADG_CODE (или из product, если позже добавим поле), goodCode = product.id или SKU (макс. 50), goodName (макс. 50), quantity, unit из EHDM_DEFAULT_UNIT, price (за единицу, 2 знака после запятой); при скидке — discount/discountType по документации.
- **cardAmount / cashAmount** — по способу оплаты заказа (безнал/наличные), сумма в драммах, 2 знака после запятой; partialAmount, prePaymentAmount = 0, если не используем.
- **Вызов после оплаты** — из webhook/обработчика успешной оплаты (Idram, Ameria и т.д.) вызвать сервис print для данного заказа; при ошибке — логировать и при необходимости повторить/обработать вручную.
- **Сохранение результата чека** — receiptId, qr, sn, fiscal, total, created_at в БД для отображения клиенту и отчётов.
- Опционально: **checkConnection** при старте или по кнопке; **configureDepartments** — только если настраиваете новый E-HDM (у текущего клиента уже выполнено).

---

## 5. Чего нет в нашей конфигурации и не требуется по документации

- **TAX-SER** (шаг 1 скриншота) — лицензия плагина HK Agency; для API ПЕК не используется.
- **getGoodList** — не обязателен; goodCode можем формировать сами (id товара, SKU).
- **Атрибуты товара в БД для E-HDM** — пока не добавляли (adgCode/unit по товару); можно позже, пока достаточно глобальных значений из .env.

---

## 6. Краткий чеклист

| # | Пункт | Статус |
|---|--------|--------|
| 1 | Изучены скриншоты плагина (6 шагов) | ✅ |
| 2 | Изучена официальная документация ПЕК (print, seq, items, dep, adgCode, goodCode и т.д.) | ✅ |
| 3 | Все нужные для print параметры перенесены в .env и ehdm-config.txt | ✅ |
| 4 | Добавлены параметры доставки в чеке (шаг 4 скриншота) | ✅ |
| 5 | Реализация в коде: хранение seq, сервис print, вызов после оплаты, сохранение чека | ❌ предстоит |

Дальнейший шаг: реализовать в Next.js хранение seq, сервис E-HDM (print с mutual TLS) и вызов этого сервиса после успешной оплаты заказа.
