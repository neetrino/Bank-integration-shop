import { NextRequest, NextResponse } from 'next/server'
import { prisma } from '@/lib/prisma'

// GET /api/search/instant - instant search с ограниченным количеством результатов
export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url)
    const query = searchParams.get('q')
    const limit = parseInt(searchParams.get('limit') || '8')

    // Валидация (проверка на минимальную длину убрана для search-as-you-type)
    if (!query || query.trim().length < 1) {
      return NextResponse.json({ results: [] })
    }

    // Поиск товаров напрямую в базе данных (используем OR условия для поиска)
    const searchQuery = query.toLowerCase().trim()
    
    // Поиск с использованием Prisma OR условий для точного поиска в базе данных
    const products = await prisma.product.findMany({
      where: {
        isAvailable: true,
        OR: [
          { name: { contains: searchQuery, mode: 'insensitive' } },
          { description: { contains: searchQuery, mode: 'insensitive' } },
          { ingredients: { contains: searchQuery, mode: 'insensitive' } }
        ]
      },
      select: {
        id: true,
        name: true,
        description: true,
        price: true,
        salePrice: true,
        image: true,
        ingredients: true,
        category: {
          select: {
            name: true
          }
        }
      },
      take: limit, // Берем только нужное количество
      orderBy: [
        // Приоритет: товары, где название содержит запрос
        {
          name: 'asc'
        }
      ]
    })

    // Сортируем результаты для лучшего соответствия (название > ингредиенты > описание)
    const filteredProducts = products.sort((a, b) => {
      const queryLower = searchQuery
      const aNameMatch = a.name.toLowerCase().includes(queryLower)
      const bNameMatch = b.name.toLowerCase().includes(queryLower)
      const aIngredientsMatch = a.ingredients?.toLowerCase().includes(queryLower) || false
      const bIngredientsMatch = b.ingredients?.toLowerCase().includes(queryLower) || false
      
      // Приоритет: название > ингредиенты > описание
      if (aNameMatch && !bNameMatch) return -1
      if (!aNameMatch && bNameMatch) return 1
      if (aIngredientsMatch && !bIngredientsMatch) return -1
      if (!aIngredientsMatch && bIngredientsMatch) return 1
      return 0
    })

    // Форматируем результаты для instant search
    const results = filteredProducts.map(product => ({
      id: product.id,
      name: product.name,
      description: product.description,
      price: product.price,
      salePrice: product.salePrice,
      image: (product.image && product.image.trim() !== '') 
        ? product.image 
        : '/images/nophoto.jpg',
      ingredients: product.ingredients,
      category: product.category?.name || 'Без категории',
      type: 'product' as const
    }))

    // Логируем для отладки
    console.log(`[Instant Search] Query: "${query}", Found: ${results.length} results`)

    // Не кэшируем результаты для instant search - новые товары должны появляться сразу
    // Используем no-store для актуальности данных при добавлении новых товаров через админку
    const response = NextResponse.json({ results })
    response.headers.set('Cache-Control', 'no-store, must-revalidate')
    
    return response
  } catch (error) {
    const queryParam = new URL(request.url).searchParams.get('q')
    console.error('Error in instant search:', error)
    console.error('Query:', queryParam)
    console.error('Error details:', {
      message: error instanceof Error ? error.message : 'Unknown error',
      stack: error instanceof Error ? error.stack : undefined
    })
    return NextResponse.json(
      { error: 'Search failed', results: [], details: error instanceof Error ? error.message : 'Unknown error' },
      { status: 500 }
    )
  }
}
