# ПОЛНАЯ ИНСТРУКЦИЯ ПО ДЕПЛОЮ НА VERCEL

**Дата:** 2026-01-26  
**Проект:** welcomebaby.am  
**Платформа:** Vercel

---

## ✅ ПРОВЕРКА ПРОЕКТА

### Это Next.js проект?
**ДА!** ✅ Полностью совместим с Vercel
- Next.js 15.5.9
- App Router
- TypeScript
- Prisma ORM
- PostgreSQL (Neon)

---

## 📋 АНАЛИЗ .ENV ФАЙЛА

### Текущий .env (локальный):
```env
NODE_ENV=development
DATABASE_URL="postgresql://..."
NEXTAUTH_URL=http://localhost:3000
NEXTAUTH_SECRET=dev-secret-key-please-change-in-production
NEXT_PUBLIC_API_URL=http://localhost:3000/api
NEXT_PUBLIC_SITE_URL=http://localhost:3000
PORT=3000
```

### Что ЛИШНЕЕ для Vercel:
- ❌ **PORT=3000** - Vercel сам определяет порт (не нужен)
- ❌ **NEXT_PUBLIC_API_URL** - не используется (используется относительный путь `/api`)
- ❌ **NEXT_PUBLIC_SITE_URL** - не нужен (Vercel автоматически определяет)

### Что НУЖНО для Vercel:
- ✅ **DATABASE_URL** - уже настроен (Neon PostgreSQL) ✅
- ✅ **NEXTAUTH_URL** - нужно изменить на production URL
- ✅ **NEXTAUTH_SECRET** - нужно изменить на сложный секрет
- ✅ **BLOB_READ_WRITE_TOKEN** - нужно добавить (для Vercel Blob)

---

## 🚀 ПОШАГОВАЯ ИНСТРУКЦИЯ

### Шаг 1: Подготовка проекта ✅

**Уже выполнено:**
- ✅ Установлен `@vercel/blob`
- ✅ Создан `src/lib/blob.ts` для работы с Blob
- ✅ Обновлены upload routes для использования Blob
- ✅ Создан `vercel.json`
- ✅ Обновлен `.env.example`

**Проверка:**
```bash
npm run build  # Должен собираться без ошибок
```

---

### Шаг 2: Создать аккаунт на Vercel

1. Зайти на https://vercel.com
2. Войти через GitHub
3. Подключить репозиторий `neetrino-com/welcomebaby.am`

---

### Шаг 3: Настроить Vercel Blob Storage

1. **В Vercel Dashboard:**
   - Перейти в **Storage**
   - Нажать **Create** → **Blob**
   - Создать store с именем: `welcomebaby-files`

2. **Создать токен:**
   - В настройках Blob Store → **Tokens**
   - Нажать **Create Token**
   - Имя: `welcomebaby-read-write`
   - Права: `read-write`
   - Скопировать токен

---

### Шаг 4: Настроить Environment Variables в Vercel

В настройках проекта → **Environment Variables** добавить:

```env
# Database (уже есть)
DATABASE_URL=postgresql://neondb_owner:npg_79qxjgetEKAG@ep-divine-lab-ag5dnvod-pooler.c-2.eu-central-1.aws.neon.tech/neondb?sslmode=require

# NextAuth
NEXTAUTH_URL=https://your-project.vercel.app
NEXTAUTH_SECRET=sl/VCG8KdAKTsCtG7eyozpCtM+g4zcPrPQeGyv9PU64=

# Vercel Blob
BLOB_READ_WRITE_TOKEN=vercel_blob_rw_xxxxxxxxxxxxx

# Environment
NODE_ENV=production
```

**Важно:**
- `NEXTAUTH_URL` - заменить на реальный URL после деплоя
- `NEXTAUTH_SECRET` - использовать сгенерированный секрет (см. ниже)
- `BLOB_READ_WRITE_TOKEN` - токен из Vercel Blob

---

### Шаг 5: Генерация NEXTAUTH_SECRET

**Уже сгенерирован:**
```
sl/VCG8KdAKTsCtG7eyozpCtM+g4zcPrPQeGyv9PU64=
```

Или сгенерировать новый:
```bash
openssl rand -base64 32
```

---

### Шаг 6: Деплой на Vercel

1. **Автоматический деплой:**
   - После подключения репозитория Vercel автоматически задеплоит
   - Первый деплой может занять 3-5 минут

2. **Проверка деплоя:**
   - После деплоя получить URL: `https://your-project.vercel.app`
   - Обновить `NEXTAUTH_URL` в Environment Variables
   - Передеплоить проект

---

### Шаг 7: Применить Prisma миграции

**Вариант 1: Через Vercel (рекомендуется):**
```bash
# В Vercel Dashboard → Deployments → Functions
# Добавить Build Command:
npm run db:generate && npm run build
```

**Вариант 2: Вручную на Neon:**
```bash
# Локально применить миграции
npx prisma migrate deploy
```

---

## 📝 ОБНОВЛЕННЫЙ .ENV ДЛЯ ЛОКАЛЬНОЙ РАЗРАБОТКИ

**Оставить в .env (локально):**
```env
NODE_ENV=development
DATABASE_URL="postgresql://..."
NEXTAUTH_URL=http://localhost:3000
NEXTAUTH_SECRET=dev-secret-key-please-change-in-production

# Для локальной разработки Blob не нужен
# BLOB_READ_WRITE_TOKEN можно не указывать
```

**Удалить из .env:**
- ❌ `PORT=3000`
- ❌ `NEXT_PUBLIC_API_URL`
- ❌ `NEXT_PUBLIC_SITE_URL`

---

## 🔧 ЧТО ИЗМЕНИЛОСЬ В КОДЕ

### 1. Установлен Vercel Blob:
```bash
npm install @vercel/blob
```

### 2. Создан `src/lib/blob.ts`:
- Утилита для работы с Vercel Blob Storage
- Автоматически использует Blob если есть токен

### 3. Обновлены upload routes:
- `src/app/api/upload-image/route.ts` - использует Blob
- `src/app/api/upload/route.ts` - использует Blob
- `src/app/api/upload-logo/route.ts` - использует Blob

### 4. Создан `vercel.json`:
- Конфигурация для Vercel

---

## ✅ CHECKLIST ПЕРЕД ДЕПЛОЕМ

- [x] Проект собирается (`npm run build`)
- [x] Vercel Blob установлен
- [x] Upload routes обновлены
- [ ] Создан Blob Store в Vercel
- [ ] Получен BLOB_READ_WRITE_TOKEN
- [ ] Environment Variables настроены в Vercel
- [ ] NEXTAUTH_SECRET сгенерирован
- [ ] Репозиторий подключен к Vercel
- [ ] Первый деплой выполнен
- [ ] NEXTAUTH_URL обновлен на production URL

---

## 🚨 ВАЖНЫЕ ЗАМЕЧАНИЯ

1. **Файлы на Vercel:**
   - Файловая система read-only
   - Все загрузки идут в Vercel Blob
   - Статические файлы остаются в `public/`

2. **Prisma:**
   - Vercel автоматически запустит `prisma generate`
   - Миграции нужно применить вручную

3. **База данных:**
   - Neon PostgreSQL уже настроен ✅
   - Работает из коробки на Vercel

4. **NEXTAUTH_URL:**
   - После первого деплоя обновить на реальный URL
   - Передеплоить проект

---

## 📊 СТРУКТУРА ПРОЕКТА ДЛЯ VERCEL

```
welcomebaby.am/
├── src/
│   ├── app/              # Next.js App Router
│   ├── lib/
│   │   ├── blob.ts      # ✨ НОВОЕ: Vercel Blob утилита
│   │   └── ...
│   └── ...
├── prisma/               # БД схема
├── public/               # Статические файлы
├── vercel.json           # ✨ НОВОЕ: Конфигурация Vercel
└── .env.example          # ✨ ОБНОВЛЕНО: Пример для Vercel
```

---

## 🎯 ГОТОВО К ДЕПЛОЮ!

**Проект полностью готов к деплою на Vercel!**

**Следующие шаги:**
1. Создать Blob Store в Vercel
2. Добавить Environment Variables
3. Подключить репозиторий
4. Задеплоить!

---

**Вопросы?** Все готово! ✅
