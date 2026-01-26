/**
 * Скрипт для очистки изображений у всех товаров
 * Устанавливает image в пустую строку, чтобы потом можно было добавить через админку
 */

import { PrismaClient } from '@prisma/client'

const prisma = new PrismaClient()

async function clearProductImages() {
  try {
    console.log('🔄 Начинаю очистку изображений товаров...')

    // Получаем все товары
    const products = await prisma.product.findMany({
      select: {
        id: true,
        name: true,
        image: true,
        images: true,
      },
    })

    console.log(`📦 Найдено товаров: ${products.length}`)

    // Обновляем все товары, устанавливая image в пустую строку
    const result = await prisma.product.updateMany({
      data: {
        image: '', // Пустая строка - будет обработано как отсутствие изображения
        images: null, // Очищаем дополнительные изображения
      },
    })

    console.log(`✅ Обновлено товаров: ${result.count}`)
    console.log('✨ Все изображения товаров очищены!')
    console.log('📝 Теперь вы можете добавить фотографии через админ-панель.')

    // Показываем статистику
    const productsWithImages = products.filter(p => p.image && p.image.trim() !== '')
    const productsWithoutImages = products.filter(p => !p.image || p.image.trim() === '')

    console.log('\n📊 Статистика:')
    console.log(`   - Товаров с изображениями (было): ${productsWithImages.length}`)
    console.log(`   - Товаров без изображений (теперь): ${products.length}`)
  } catch (error) {
    console.error('❌ Ошибка при очистке изображений:', error)
    throw error
  } finally {
    await prisma.$disconnect()
  }
}

// Запускаем скрипт
clearProductImages()
  .then(() => {
    console.log('\n✅ Скрипт выполнен успешно!')
    process.exit(0)
  })
  .catch((error) => {
    console.error('\n❌ Ошибка выполнения скрипта:', error)
    process.exit(1)
  })
