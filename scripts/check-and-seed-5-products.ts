/**
 * Подключение к БД: проверка состояния и добавление 5 товаров по 10 штук.
 * Запуск: npx tsx scripts/check-and-seed-5-products.ts
 */

import { PrismaClient } from '@prisma/client'
import { readFileSync } from 'fs'
import { resolve } from 'path'

function loadEnv() {
  try {
    const envPath = resolve(process.cwd(), '.env')
    const envFile = readFileSync(envPath, 'utf-8')
    for (const line of envFile.split('\n')) {
      const t = line.trim()
      if (t && !t.startsWith('#')) {
        const eq = t.indexOf('=')
        if (eq > 0) {
          const key = t.slice(0, eq).trim()
          const value = t.slice(eq + 1).replace(/^["']|["']$/g, '').trim()
          process.env[key] = value
        }
      }
    }
    if (!process.env.DATABASE_URL) {
      console.error('❌ DATABASE_URL не найден в .env')
      process.exit(1)
    }
  } catch (e) {
    console.error('❌ Ошибка загрузки .env:', e)
    process.exit(1)
  }
}

loadEnv()

const prisma = new PrismaClient()

const FIVE_PRODUCTS = [
  { name: 'Товар 1 — Динамический', description: 'Первый тестовый товар из скрипта.', price: 1990, image: '/images/placeholder.svg' },
  { name: 'Товар 2 — Динамический', description: 'Второй тестовый товар из скрипта.', price: 2990, image: '/images/placeholder.svg' },
  { name: 'Товар 3 — Динамический', description: 'Третий тестовый товар из скрипта.', price: 3990, image: '/images/placeholder.svg' },
  { name: 'Товар 4 — Динамический', description: 'Четвёртый тестовый товар из скрипта.', price: 4990, image: '/images/placeholder.svg' },
  { name: 'Товар 5 — Динамический', description: 'Пятый тестовый товар из скрипта.', price: 5990, image: '/images/placeholder.svg' },
]

const STOCK_PER_PRODUCT = 10

async function main() {
  console.log('🔌 Подключаюсь к базе данных...\n')

  try {
    await prisma.$connect()
    console.log('✅ Подключение к БД успешно.\n')

    const categoriesCount = await prisma.category.count()
    const productsCount = await prisma.product.count()
    console.log('📊 Текущее состояние БД:')
    console.log(`   Категории: ${categoriesCount}`)
    console.log(`   Товары: ${productsCount}\n`)

    let categoryId: string
    const firstCategory = await prisma.category.findFirst({ orderBy: { sortOrder: 'asc' } })
    if (firstCategory) {
      categoryId = firstCategory.id
      console.log(`📁 Использую категорию: "${firstCategory.name}" (id: ${firstCategory.id})\n`)
    } else {
      const created = await prisma.category.create({
        data: {
          name: 'Тестовые товары',
          description: 'Категория для динамических тестовых товаров',
          sortOrder: 0,
          isActive: true,
        },
      })
      categoryId = created.id
      console.log(`📁 Создана категория: "${created.name}" (id: ${created.id})\n`)
    }

    console.log(`➕ Добавляю 5 товаров по ${STOCK_PER_PRODUCT} штук каждый...\n`)
    for (const p of FIVE_PRODUCTS) {
      const product = await prisma.product.create({
        data: {
          name: p.name,
          description: p.description,
          price: p.price,
          image: p.image,
          categoryId,
          ingredients: '—',
          isAvailable: true,
          stock: STOCK_PER_PRODUCT,
          status: 'REGULAR',
        },
      })
      console.log(`   ✓ ${product.name} — цена ${product.price}, остаток ${product.stock}`)
    }

    const newTotal = await prisma.product.count()
    console.log('\n✅ Готово. Всего товаров в БД:', newTotal)
  } catch (e) {
    console.error('❌ Ошибка:', e)
    throw e
  } finally {
    await prisma.$disconnect()
  }
}

main()
  .then(() => process.exit(0))
  .catch(() => process.exit(1))
