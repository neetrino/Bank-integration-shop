import { NextRequest, NextResponse } from 'next/server'
import { prisma } from '@/lib/prisma'
import { getServerSession } from 'next-auth'
import { authOptions } from '@/lib/auth'

// GET /api/delivery-types/[id] - получить конкретный тип доставки
export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const { id } = await params
    const deliveryType = await prisma.deliveryType.findUnique({
      where: { id }
    })

    if (!deliveryType) {
      return NextResponse.json(
        { success: false, error: 'Delivery type not found' },
        { status: 404 }
      )
    }

    return NextResponse.json({
      success: true,
      data: deliveryType
    })
  } catch (error) {
    console.error('Error fetching delivery type:', error)
    return NextResponse.json(
      { success: false, error: 'Failed to fetch delivery type' },
      { status: 500 }
    )
  }
}

// PUT /api/delivery-types/[id] - обновить тип доставки
export async function PUT(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const session = await getServerSession(authOptions)
    
    if (!session || session.user?.role !== 'ADMIN') {
      return NextResponse.json(
        { success: false, error: 'Unauthorized' },
        { status: 401 }
      )
    }

    const { id } = await params
    const body = await request.json()
    const { name, deliveryTime, description, price, isActive } = body

    // Проверяем существование
    const existingDeliveryType = await prisma.deliveryType.findUnique({
      where: { id }
    })

    if (!existingDeliveryType) {
      return NextResponse.json(
        { success: false, error: 'Delivery type not found' },
        { status: 404 }
      )
    }

    // Валидация
    if (price !== undefined && (typeof price !== 'number' || price < 0)) {
      return NextResponse.json(
        { success: false, error: 'Price must be a positive number' },
        { status: 400 }
      )
    }

    const updateData: Record<string, unknown> = {}
    if (name !== undefined) updateData.name = name
    if (deliveryTime !== undefined) updateData.deliveryTime = deliveryTime
    if (description !== undefined) updateData.description = description
    if (price !== undefined) updateData.price = parseFloat(price.toFixed(2))
    if (isActive !== undefined) updateData.isActive = isActive

    const deliveryType = await prisma.deliveryType.update({
      where: { id },
      data: updateData
    })

    return NextResponse.json({
      success: true,
      data: deliveryType
    })
  } catch (error) {
    console.error('Error updating delivery type:', error)
    
    if (error instanceof Error && error.message.includes('Unique constraint')) {
      return NextResponse.json(
        { success: false, error: 'Delivery type with this name already exists' },
        { status: 409 }
      )
    }

    return NextResponse.json(
      { success: false, error: 'Failed to update delivery type' },
      { status: 500 }
    )
  }
}

// DELETE /api/delivery-types/[id] - удалить тип доставки
export async function DELETE(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const session = await getServerSession(authOptions)
    
    if (!session || session.user?.role !== 'ADMIN') {
      return NextResponse.json(
        { success: false, error: 'Unauthorized' },
        { status: 401 }
      )
    }

    const { id } = await params
    // Проверяем существование
    const existingDeliveryType = await prisma.deliveryType.findUnique({
      where: { id }
    })

    if (!existingDeliveryType) {
      return NextResponse.json(
        { success: false, error: 'Delivery type not found' },
        { status: 404 }
      )
    }

    await prisma.deliveryType.delete({
      where: { id }
    })

    return NextResponse.json({
      success: true,
      message: 'Delivery type deleted successfully'
    })
  } catch (error) {
    console.error('Error deleting delivery type:', error)
    return NextResponse.json(
      { success: false, error: 'Failed to delete delivery type' },
      { status: 500 }
    )
  }
}
