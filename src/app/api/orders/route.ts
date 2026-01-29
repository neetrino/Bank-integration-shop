import { NextRequest, NextResponse } from 'next/server'
import { getServerSession } from 'next-auth/next'
import { authOptions } from '@/lib/auth'
import { PaymentStatus } from '@prisma/client'
import { prisma } from '@/lib/prisma'
import { logger } from '@/lib/logger'

export async function GET(request: NextRequest) {
  try {
    const session = await getServerSession(authOptions)

    if (!session) {
      return NextResponse.json(
        { error: 'Unauthorized' },
        { status: 401 }
      )
    }

    const orders = await prisma.order.findMany({
      where: {
        userId: session.user.id
      },
      include: {
        items: {
          include: {
            product: {
              select: {
                name: true,
                image: true
              }
            }
          }
        }
      },
      orderBy: {
        createdAt: 'desc'
      }
    })

    return NextResponse.json(orders)
  } catch (error) {
    logger.error('Orders API error', error)
    return NextResponse.json(
      { error: 'Internal server error' },
      { status: 500 }
    )
  }
}

export async function POST(request: NextRequest) {
  try {
    const session = await getServerSession(authOptions)
    const { name, phone, address, paymentMethod, notes, items, total, deliveryTime } = await request.json()

    // Логируем только метаданные, без персональных данных (PII)
    logger.debug('Creating order:', { 
      hasSession: !!session, 
      userId: session?.user?.id, 
      itemsCount: items?.length,
      total,
      paymentMethod,
      deliveryTime,
      // НЕ логируем: name, phone, address (PII)
      items: items?.map((item: any) => ({
        productId: item.productId,
        quantity: item.quantity,
        price: item.price
      }))
    })

    // Проверяем, что все продукты существуют
    if (items && items.length > 0) {
      const productIds = items.map((item: any) => item.productId)
      const existingProducts = await prisma.product.findMany({
        where: { id: { in: productIds } },
        select: { id: true, name: true }
      })
      
      logger.debug('Existing products:', existingProducts)
      
      const missingProducts = productIds.filter((id: string) => 
        !existingProducts.find(p => p.id === id)
      )
      
      if (missingProducts.length > 0) {
        logger.error('Missing products:', missingProducts)
        return NextResponse.json(
          { error: `Products not found: ${missingProducts.join(', ')}` },
          { status: 400 }
        )
      }
    }

    // Determine payment status based on payment method
    // For online payments (Ameria, IDram), status is PENDING until payment is confirmed
    // For cash/card on delivery, payment status is null (not applicable)
    const paymentStatus: PaymentStatus | null =
      paymentMethod === 'ameriabank' || paymentMethod === 'idram'
        ? PaymentStatus.PENDING
        : null

    // Create order (supports both authenticated and guest users)
    const order = await prisma.order.create({
      data: {
        userId: session?.user?.id || null, // null for guest orders
        name: name || 'Guest Customer',
        status: 'PENDING',
        paymentStatus,
        total,
        address,
        phone,
        notes,
        paymentMethod,
        deliveryTime,
        items: {
          create: items.map((item: { productId: string; quantity: number; price: number }) => ({
            productId: item.productId,
            quantity: item.quantity,
            price: item.price
          }))
        }
      },
      include: {
        items: {
          include: {
            product: {
              select: {
                name: true,
                image: true
              }
            }
          }
        }
      }
    })

    logger.info('Order created successfully:', order.id)
    return NextResponse.json(order, { status: 201 })
  } catch (error) {
    // Логируем полные детали только на сервере
    logger.error('Create order API error', error)
    
    const isDev = process.env.NODE_ENV === 'development'
    if (isDev && error instanceof Error) {
      logger.error('Error details', {
        name: error.name,
        message: error.message,
        stack: error.stack
      })
    }
    
    // В проде не раскрываем детали ошибок клиенту
    return NextResponse.json(
      { 
        error: 'Internal server error',
        ...(isDev && { details: error instanceof Error ? error.message : 'Unknown error' })
      },
      { status: 500 }
    )
  }
}
