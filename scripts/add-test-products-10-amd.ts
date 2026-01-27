/**
 * Скрипт для добавления тестовых товаров по 10 драмов в каждую категорию
 * 
 * Запуск: npx tsx scripts/add-test-products-10-amd.ts
 */

import { PrismaClient } from '@prisma/client'
import { readFileSync } from 'fs'
import { resolve } from 'path'

// Загружаем переменные окружения из .env файла вручную
function loadEnv() {
  try {
    const envPath = resolve(process.cwd(), '.env')
    const envFile = readFileSync(envPath, 'utf-8')
    const lines = envFile.split('\n')
    
    for (const line of lines) {
      const trimmed = line.trim()
      if (trimmed && !trimmed.startsWith('#')) {
        const [key, ...valueParts] = trimmed.split('=')
        if (key && valueParts.length > 0) {
          const value = valueParts.join('=').replace(/^["']|["']$/g, '')
          process.env[key.trim()] = value.trim()
        }
      }
    }
    
    if (!process.env.DATABASE_URL) {
      console.error('❌ DATABASE_URL не найден в .env файле!')
      process.exit(1)
    }
  } catch (error) {
    console.error('❌ Ошибка при загрузке .env файла:', error)
    throw error
  }
}

// Загружаем переменные окружения
loadEnv()

const prisma = new PrismaClient()

// Цена в драмах
const TEST_PRICE = 10

// Тестовые названия товаров (будут использоваться с номером)
const testProductNames = [
  'Тестовый товар',
  'Тестовый продукт',
  'Тестовое изделие',
  'Тестовый образец',
  'Тестовый экземпляр'
]

async function addTestProducts() {
  try {
    console.log('🔄 Начинаю добавление тестовых товаров по 10 драмов...')
    
    // Получаем все активные категории
    const categories = await prisma.category.findMany({
      where: {
        isActive: true
      }
    })
    
    if (categories.length === 0) {
      console.error('❌ Не найдено активных категорий! Создайте категории сначала.')
      return
    }
    
    console.log(`📊 Найдено ${categories.length} активных категорий`)
    
    let totalAdded = 0
    
    // Добавляем по 2-3 тестовых товара в каждую категорию
    for (const category of categories) {
      const productsPerCategory = 3
      
      for (let i = 0; i < productsPerCategory; i++) {
        const productName = `${testProductNames[i % testProductNames.length]} ${i + 1}`
        const productNumber = totalAdded + 1
        
        try {
          const product = await prisma.product.create({
            data: {
              name: `${productName} (${category.name})`,
              description: `Тестовый товар для категории "${category.name}". Цена: ${TEST_PRICE} драмов.`,
              price: TEST_PRICE,
              image: '/images/placeholder.jpg', // Заглушка для изображения
              categoryId: category.id,
              ingredients: 'Тестовые ингредиенты',
              isAvailable: true,
              stock: 100,
              status: 'REGULAR'
            }
          })
          console.log(`   ✓ [${productNumber}] Добавлен товар: "${product.name}" - ${TEST_PRICE} AMD (${category.name})`)
          totalAdded++
        } catch (error: any) {
          console.error(`   ❌ Ошибка при добавлении товара в категорию "${category.name}":`, error.message)
        }
      }
    }
    
    console.log(`\n✅ Успешно добавлено ${totalAdded} тестовых товаров по ${TEST_PRICE} драмов!`)
    console.log(`📦 Товары распределены по ${categories.length} категориям`)
    
  } catch (error) {
    console.error('❌ Ошибка при добавлении товаров:', error)
    throw error
  } finally {
    await prisma.$disconnect()
  }
}

// Запуск скрипта
addTestProducts()
  .then(() => {
    console.log('\n🎉 Скрипт выполнен успешно!')
    process.exit(0)
  })
  .catch((error) => {
    console.error('\n💥 Критическая ошибка:', error)
    process.exit(1)
  })
