# E-HDM: что уже есть (по базе и файлам) и что делать дальше

## Что у вас уже есть

### 1. Файлы в папке `Private/`

| Файл | Назначение |
|------|------------|
| **00505298.crt** | Сертификат клиента для API ПЕК (TIN = 00505298) |
| **00505298.key** | Приватный ключ к этому сертификату |

В `Private/` также лежит **ehdm-config.txt** — справочный файл с CRN, TIN, начальным seq (пароль в нём не хранится, только в `.env`).  
Папка `Private/` и файл `.env` добавлены в `.gitignore` — в git не попадают.

---

### 2. Данные из базы WordPress (по вашим скриншотам)

**Таблица `wp_tax_service`** — успешно пробитые чеки E-HDM:

| Поле | Пример | Зачем в Next.js |
|------|--------|------------------|
| order_id | 8089, 8329... | Связь с заказом |
| crn | 52031720 | Номер регистрации E-HDM (у вас уже есть) |
| sn | 16DE4B4C | Серийный номер чека от ПЕК |
| tin | 00505298 | ИНН (совпадает с именем сертификата) |
| taxpayer | «ՆՅՈՒ ԱԿՎԱ» | Название организации |
| address | ԿԵՆՏՐՈՆ ԹԱՂԱՄԱՍ... | Адрес |
| status | print / refund | Статус чека (продажа / возврат) |
| fiscal | 39391423 | Фискальный номер |
| total | 120, 3600... | Сумма чека |
| qr | TIN: 00505298, CRN: 52031720... | Строка для QR (сохранять для клиента) |
| created_at | 2025-02-12 15:10:55 | Время создания записи |

**Таблица `wp_tax_service_requests`** — лог запросов к API:

- **url:** `https://ecrm.taxservice.am/taxsystem-rs-vcr/api/v1...` — production API.
- **request_data:** видно структуру запросов:
  - `crn: "52031720"`
  - `tpin: "00505298"` (в activate/configure; в print обычно не передаётся отдельно)
  - `seq`: 1, 2, 3... — порядковый номер (обязательно продолжать с последнего при переходе на Next.js).
  - `mode: 2` — режим «товары» для печати чека продажи.
  - `cashierId: 1`, `departments`, `items` и т.д.

**Таблица `wp_tax_service_report`** — отчёты об ошибках:

- Полезно для отладки: `error_reason`, `message` (в т.ч. «FIELD_REQUIRED», ошибки по полю `dep`, проверка суммы оплаты).

**Итого из базы уже известно:**

- **CRN:** `52031720`
- **TIN:** `00505298` (имена файлов .crt/.key совпадают с TIN)
- **API:** `https://ecrm.taxservice.am/taxsystem-rs-vcr/api/v1.0`
- **Текущий seq** можно взять: из опции `taxServiceSeqNumber` в `wp_options` или как максимальный `seq` из `request_data` в `wp_tax_service_requests`

Таблицы `wp_term_taxonomy` и `wp_wc_tax_rate_classes` к E-HDM не относятся (категории и классы налогов WooCommerce).

---

## Конфиг перенесён (Private/ и .env)

Данные из WordPress сохранены локально:

- **Private/ehdm-config.txt** — CRN, TIN, начальный seq (справочно).
- **.env** — полный конфиг E-HDM: `EHDM_API_URL`, `EHDM_CRN`, `EHDM_TIN`, `EHDM_CERT_PATH`, `EHDM_KEY_PATH`, `EHDM_KEY_PASSPHRASE`, `EHDM_INITIAL_SEQ=65`.
- Папка `Private/` и файл `.env` в `.gitignore` — в репозиторий не коммитятся.

При первом вызове API использовать **seq = 65**, далее хранить seq в БД и увеличивать на 1 после каждого успешного запроса.

---

## Справка: как вытащить из WordPress (если понадобится снова)

**Запрос к wp_options:**

```sql
SELECT option_name, option_value
FROM wp_options
WHERE option_name IN (
  'hkd_tax_service_passphrase',
  'taxServiceSeqNumber',
  'hkd_tax_service_tax_type',
  'hkd_tax_service_atg_code',
  'hkd_tax_service_units_value',
  'hkd_tax_service_register_number',
  'hkd_tax_service_tin'
);
```

Проверьте: `hkd_tax_service_register_number` должен быть `52031720`, `hkd_tax_service_tin` — `00505298`.

---

## Что сделать дальше (по шагам)

### Шаг 1. ~~Добрать из WordPress~~ — выполнено

Passphrase и seq получены из `wp_options`, значения записаны в `.env` и в `Private/ehdm-config.txt`. Начальный seq = 65.

### Шаг 2. Env для Next.js — настроено

В проекте в `.env` уже добавлен блок E-HDM. На продакшене задать те же переменные в окружении сервера. В git не коммитить `.env`.

```env
# E-HDM (реальный контур)
EHDM_API_URL=https://ecrm.taxservice.am/taxsystem-rs-vcr/api/v1.0
EHDM_CRN=52031720
EHDM_TIN=00505298
EHDM_CERT_PATH=./Private/00505298.crt
EHDM_KEY_PATH=./Private/00505298.key
EHDM_KEY_PASSPHRASE=    # из hkd_tax_service_passphrase, или пусто
# Начальный seq взять из taxServiceSeqNumber и один раз записать в БД (см. шаг 3)
```

На продакшене пути `EHDM_CERT_PATH` и `EHDM_KEY_PATH` должны указывать на реальные пути к файлам на сервере (не обязательно папка `Private`).

### Шаг 3. Хранить seq и чеки в Next.js (Prisma)

Имеет смысл завести:

1. **Таблицу для одного текущего seq по CRN**  
   Либо одна запись «текущий seq», либо хранить в отдельной таблице настроек (key-value). При каждом вызове print/activate/configureDepartments:
   - прочитать текущий seq,
   - отправить запрос с этим seq,
   - при успехе записать seq+1.

2. **Таблицу успешных чеков E-HDM** (аналог `wp_tax_service`):

   - order_id (связь с заказом),
   - crn, sn, tin, taxpayer, address,
   - status (print / refund),
   - fiscal, total, qr, receiptId (если API возвращает),
   - created_at.

3. По желанию — таблицу лога запросов/ответов (аналог `wp_tax_service_requests`) для отладки и разбора ошибок (request_data, response_data, order_id, created_at).

### Шаг 4. Реализация в коде

1. **Сервис E-HDM** (например `src/lib/ehdm/` или `src/lib/tax-service/`):
   - чтение env (URL, CRN, пути к .crt/.key, passphrase);
   - получение следующего seq из БД и сохранение нового значения после успешного вызова;
   - метод **print**: формирование JSON по документации (crn, seq, mode: 2, cashierId, cardAmount/cashAmount, items), отправка POST с клиентским сертификатом (в Node.js — опции `cert` и `key` для `https`), разбор ответа;
   - при успехе — сохранение в таблицу чеков (order_id, crn, sn, tin, taxpayer, address, status, fiscal, total, qr, receiptId, created_at).
2. **Вызов после оплаты:** в месте, где вы фиксируете успешную оплату заказа (webhook банка или обновление статуса), вызвать E-HDM print с данными заказа и сохранить результат в БД.
3. Отдавать клиенту (страница «Заказ оплачен», письмо) ссылку на чек или QR из поля `qr`.

### Шаг 5. Важно: один CRN — одна очередь seq

Если старый WordPress ещё будет пробивать чеки тем же CRN, конфликт по seq неизбежен. Нужно либо:
- полностью перейти на Next.js (на WordPress больше не вызывать print), тогда текущий seq один раз взять из WordPress и дальше использовать только в Next.js,  
либо  
- для нового сайта оформить в ПЕК новый E-HDM (новый CRN и свой сертификат).

---

## Краткий чек-лист

- [x] Файлы .crt и .key в `Private/` есть (00505298.crt, 00505298.key).
- [x] CRN и TIN известны из базы: 52031720, 00505298.
- [x] Passphrase и текущий seq получены из `wp_options`, записаны в `.env` и в `Private/ehdm-config.txt` (начальный seq = 65).
- [x] В `.env` добавлены переменные E-HDM; папка `Private/` и `.env` в `.gitignore`.
- [ ] В Prisma: модель для хранения seq + модель для чеков E-HDM (и по желанию лог запросов).
- [ ] Реализовать сервис вызова API (print с сертификатом, учёт seq, сохранение результата).
- [ ] Подключить вызов print после успешной оплаты заказа.

После этого можно переходить к коду: схемы Prisma и сервис E-HDM.
