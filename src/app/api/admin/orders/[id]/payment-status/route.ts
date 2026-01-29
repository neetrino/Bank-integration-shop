import { NextRequest, NextResponse } from 'next/server'
import { prisma } from '@/lib/prisma'
import { getServerSession } from 'next-auth/next'
import { authOptions } from '@/lib/auth'
import { AmeriaBankService } from '@/lib/payments/ameriabank'
import { PaymentStatus } from '@prisma/client'

const VALID_PAYMENT_STATUSES = ['PENDING', 'SUCCESS', 'FAILED', 'REFUNDED', 'CANCELLED'] as const

/**
 * PATCH /api/admin/orders/[id]/payment-status
 * Изменить статус оплаты вручную (Ожидает / Оплачено / Ошибка / Возврат / Отменено).
 * При выборе "Возврат" или "Отмена" для Ameria — отправляется запрос в банк, затем обновляется статус.
 * Ручная смена на "Оплачено" — когда клиент оплатил наличными, переводом и т.д.
 */
export async function PATCH(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const session = await getServerSession(authOptions)
    if (!session?.user) {
      return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
    }
    if (session.user.role !== 'ADMIN') {
      return NextResponse.json(
        { error: 'Forbidden - Admin access required' },
        { status: 403 }
      )
    }

    const { id } = await params
    const body = await request.json().catch(() => ({}))
    const paymentStatus = body.paymentStatus as string | undefined

    if (!paymentStatus || !VALID_PAYMENT_STATUSES.includes(paymentStatus as typeof VALID_PAYMENT_STATUSES[number])) {
      return NextResponse.json(
        { error: 'Invalid paymentStatus. Must be one of: ' + VALID_PAYMENT_STATUSES.join(', ') },
        { status: 400 }
      )
    }

    const order = await prisma.order.findUnique({ where: { id } })
    if (!order) {
      return NextResponse.json({ error: 'Order not found' }, { status: 404 })
    }

    const method = order.paymentMethod?.toLowerCase()

    // Возврат или отмена — для Ameria вызываем API банка; для IDram — только подсказка
    if (paymentStatus === 'REFUNDED') {
      if (order.paymentStatus !== 'SUCCESS') {
        return NextResponse.json(
          { error: 'Возврат возможен только для заказов со статусом оплаты «Оплачено»' },
          { status: 400 }
        )
      }
      if (!order.paymentTransactionId) {
        return NextResponse.json(
          { error: 'Нет ID транзакции — возврат через этот интерфейс невозможен' },
          { status: 400 }
        )
      }
      if (method === 'ameriabank') {
        const ameria = new AmeriaBankService()
        const refundResult = await ameria.refundPayment(order.paymentTransactionId, order.total)
        if (refundResult.ResponseCode !== '00') {
          return NextResponse.json(
            { error: refundResult.ResponseMessage || `Банк: ${refundResult.ResponseCode}` },
            { status: 502 }
          )
        }
      } else if (method === 'idram') {
        return NextResponse.json(
          {
            error:
              'Возврат по IDram делается через поддержку IDram. Укажите ID транзакции: ' +
              order.paymentTransactionId,
          },
          { status: 400 }
        )
      }
    }

    if (paymentStatus === 'CANCELLED') {
      if (!order.paymentTransactionId) {
        return NextResponse.json(
          { error: 'Нет ID транзакции — отмена платежа через этот интерфейс невозможна' },
          { status: 400 }
        )
      }
      if (method === 'ameriabank') {
        const ameria = new AmeriaBankService()
        const cancelResult = await ameria.cancelPayment(order.paymentTransactionId)
        if (cancelResult.ResponseCode !== '00') {
          return NextResponse.json(
            { error: cancelResult.ResponseMessage || `Банк: ${cancelResult.ResponseCode}` },
            { status: 502 }
          )
        }
      } else if (method === 'idram') {
        return NextResponse.json(
          {
            error:
              'Отмена платежа по IDram — через поддержку IDram. ID транзакции: ' +
              order.paymentTransactionId,
          },
          { status: 400 }
        )
      }
    }

    const updatedOrder = await prisma.order.update({
      where: { id },
      data: { paymentStatus: paymentStatus as PaymentStatus },
      include: {
        user: {
          select: { id: true, name: true, email: true, phone: true },
        },
        items: {
          include: {
            product: {
              select: { id: true, name: true, price: true, image: true },
            },
          },
        },
      },
    })

    const totalAmount = updatedOrder.items.reduce(
      (sum, item) => sum + item.product.price * item.quantity,
      0
    )

    return NextResponse.json({
      ...updatedOrder,
      totalAmount,
    })
  } catch (error) {
    console.error('Error updating payment status:', error)
    return NextResponse.json(
      {
        error:
          error instanceof Error ? error.message : 'Failed to update payment status',
      },
      { status: 500 }
    )
  }
}
