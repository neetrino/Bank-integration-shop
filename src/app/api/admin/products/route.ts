import { NextRequest, NextResponse } from 'next/server'
import { prisma } from '@/lib/prisma'
import { getServerSession } from 'next-auth/next'
import { authOptions } from '@/lib/auth'

// GET /api/admin/products - получить товары с пагинацией (только для админов)
export async function GET(request: NextRequest) {
  try {
    // Проверяем аутентификацию
    const session = await getServerSession(authOptions)
    
    if (!session?.user) {
      return NextResponse.json(
        { error: 'Unauthorized' },
        { status: 401 }
      )
    }

    // Проверяем права администратора
    if (session.user.role !== 'ADMIN') {
      return NextResponse.json(
        { error: 'Forbidden - Admin access required' },
        { status: 403 }
      )
    }

    // Получаем параметры пагинации и фильтров
    const { searchParams } = new URL(request.url)
    const page = parseInt(searchParams.get('page') || '1', 10)
    const limit = parseInt(searchParams.get('limit') || '20', 10)
    const search = searchParams.get('search') || ''
    const category = searchParams.get('category') || ''
    const status = searchParams.get('status') || ''

    // Валидация параметров
    const pageNum = Math.max(1, page)
    const limitNum = Math.min(Math.max(1, limit), 100) // Максимум 100 товаров на страницу
    const skip = (pageNum - 1) * limitNum

    // Формируем условия фильтрации
    const whereClause: any = {}

    // Поиск по названию или описанию
    if (search) {
      whereClause.OR = [
        { name: { contains: search, mode: 'insensitive' } },
        { description: { contains: search, mode: 'insensitive' } }
      ]
    }

    // Фильтр по категории
    if (category) {
      whereClause.categoryId = category
    }

    // Фильтр по статусу
    if (status === 'special') {
      whereClause.status = {
        in: ['HIT', 'NEW', 'CLASSIC', 'BANNER']
      }
    } else if (status) {
      whereClause.status = status
    }

    // Получаем общее количество товаров (для пагинации)
    const total = await prisma.product.count({
      where: whereClause
    })

    // Получаем товары с пагинацией
    const products = await prisma.product.findMany({
      where: whereClause,
      skip,
      take: limitNum,
      orderBy: { createdAt: 'desc' },
      include: {
        category: {
          select: {
            id: true,
            name: true,
            isActive: true
          }
        }
      }
    })

    // Нормализуем изображения
    const normalizedProducts = products.map(product => ({
      ...product,
      image: (product.image && product.image.trim() !== '') 
        ? product.image 
        : '/images/nophoto.jpg'
    }))

    const totalPages = Math.ceil(total / limitNum)

    return NextResponse.json({
      products: normalizedProducts,
      pagination: {
        currentPage: pageNum,
        totalPages,
        totalItems: total,
        itemsPerPage: limitNum,
        hasNextPage: pageNum < totalPages,
        hasPrevPage: pageNum > 1
      }
    })
  } catch (error) {
    console.error('Error fetching admin products:', error)
    return NextResponse.json(
      { error: 'Failed to fetch products' },
      { status: 500 }
    )
  }
}

// POST /api/admin/products - создать новый товар (только для админов)
export async function POST(request: NextRequest) {
  try {
    // Проверяем аутентификацию
    const session = await getServerSession(authOptions)
    
    if (!session?.user) {
      return NextResponse.json(
        { error: 'Unauthorized' },
        { status: 401 }
      )
    }

    // Проверяем права администратора
    if (session.user.role !== 'ADMIN') {
      return NextResponse.json(
        { error: 'Forbidden - Admin access required' },
        { status: 403 }
      )
    }

    // Получаем данные из запроса
    const body = await request.json()
    const { name, description, price, salePrice, categoryId, image, images, ingredients, isAvailable = true, status = 'REGULAR', stock = 10 } = body

    // Валидация обязательных полей
    if (!name || !description || !price || !categoryId) {
      return NextResponse.json(
        { error: 'Missing required fields: name, description, price, category' },
        { status: 400 }
      )
    }

    // Валидация цены
    if (typeof price !== 'number' || price <= 0) {
      return NextResponse.json(
        { error: 'Price must be a positive number' },
        { status: 400 }
      )
    }

    // Валидация скидочной цены
    if (salePrice !== undefined && salePrice !== null) {
      if (typeof salePrice !== 'number' || salePrice <= 0) {
        return NextResponse.json(
          { error: 'Sale price must be a positive number' },
          { status: 400 }
        )
      }
      if (salePrice >= price) {
        return NextResponse.json(
          { error: 'Sale price must be less than regular price' },
          { status: 400 }
        )
      }
    }

    // Проверяем, что категория существует
    const category = await prisma.category.findUnique({
      where: { id: categoryId }
    })

    if (!category) {
      return NextResponse.json(
        { error: 'Category not found' },
        { status: 400 }
      )
    }

    // Валидация статуса (пустая строка означает REGULAR)
    const validStatuses = ['', 'REGULAR', 'HIT', 'NEW', 'CLASSIC', 'BANNER']
    if (status && !validStatuses.includes(status)) {
      return NextResponse.json(
        { error: 'Invalid status. Must be one of: ' + validStatuses.filter(s => s).join(', ') },
        { status: 400 }
      )
    }

    // Создаем товар
    const product = await prisma.product.create({
      data: {
        name,
        description,
        price,
        salePrice: salePrice === null || salePrice === '' ? null : salePrice,
        categoryId,
        image: image && image.trim() !== '' ? image : '/images/nophoto.jpg', // Используем nophoto.jpg по умолчанию
        images: images || null, // Дополнительные изображения (JSON строка)
        ingredients: Array.isArray(ingredients) ? JSON.stringify(ingredients) : (ingredients || ''), // Преобразуем массив в строку JSON или используем строку
        isAvailable,
        stock: typeof stock === 'number' ? stock : 10, // Устанавливаем stock, по умолчанию 10
        status: status || 'REGULAR' // Если статус не выбран, то REGULAR
      },
      include: {
        category: {
          select: {
            id: true,
            name: true,
            isActive: true
          }
        }
      }
    })

    // Нормализуем изображение - товар без изображения получит nophoto.jpg
    const normalizedProduct = {
      ...product,
      image: (product.image && product.image.trim() !== '') 
        ? product.image 
        : '/images/nophoto.jpg'
    }

    return NextResponse.json(normalizedProduct, { status: 201 })
  } catch (error) {
    // Логируем полные детали только на сервере
    console.error('Error creating product:', error)
    if (process.env.NODE_ENV === 'development') {
      console.error('Error details:', {
        message: error instanceof Error ? error.message : 'Unknown error',
        stack: error instanceof Error ? error.stack : undefined
      })
    }
    
    // В проде не раскрываем детали ошибок клиенту
    const isDev = process.env.NODE_ENV === 'development'
    return NextResponse.json(
      { 
        error: 'Failed to create product',
        ...(isDev && { details: error instanceof Error ? error.message : 'Unknown error' })
      },
      { status: 500 }
    )
  }
}
