import { NextRequest, NextResponse } from 'next/server'
import { prisma } from '@/lib/prisma'
import { getServerSession } from 'next-auth/next'
import { authOptions } from '@/lib/auth'
import { AmeriaBankService } from '@/lib/payments/ameriabank'
import { PaymentStatus } from '@prisma/client'

/**
 * POST /api/admin/orders/[id]/refund
 * Refund payment (Ameria Bank only). Sends refund request to bank and updates order paymentStatus to REFUNDED.
 */
export async function POST(
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
    const order = await prisma.order.findUnique({
      where: { id },
    })

    if (!order) {
      return NextResponse.json({ error: 'Order not found' }, { status: 404 })
    }

    if (order.paymentStatus !== 'SUCCESS') {
      return NextResponse.json(
        { error: 'Refund is only allowed for orders with successful payment' },
        { status: 400 }
      )
    }

    if (!order.paymentTransactionId) {
      return NextResponse.json(
        { error: 'Order has no payment transaction ID (cannot refund)' },
        { status: 400 }
      )
    }

    // Only Ameria Bank supports refund via API
    if (order.paymentMethod?.toLowerCase() !== 'ameriabank') {
      return NextResponse.json(
        {
          error:
            'Refund via this action is only supported for Ameria Bank. For IDram, contact Idram support with transaction ID: ' +
            (order.paymentTransactionId || 'N/A'),
        },
        { status: 400 }
      )
    }

    const body = await request.json().catch(() => ({}))
    const amount = typeof body.amount === 'number' ? body.amount : order.total
    if (amount <= 0 || amount > order.total) {
      return NextResponse.json(
        { error: 'Refund amount must be positive and not exceed order total' },
        { status: 400 }
      )
    }

    const ameria = new AmeriaBankService()
    const refundResult = await ameria.refundPayment(
      order.paymentTransactionId,
      amount
    )

    if (refundResult.ResponseCode !== '00') {
      return NextResponse.json(
        {
          error:
            refundResult.ResponseMessage ||
            `Bank returned code: ${refundResult.ResponseCode}`,
        },
        { status: 502 }
      )
    }

    await prisma.order.update({
      where: { id },
      data: { paymentStatus: PaymentStatus.REFUNDED },
    })

    return NextResponse.json({
      success: true,
      message: 'Refund processed successfully',
      paymentStatus: 'REFUNDED',
    })
  } catch (error) {
    console.error('Admin refund error:', error)
    return NextResponse.json(
      {
        error:
          error instanceof Error ? error.message : 'Failed to process refund',
      },
      { status: 500 }
    )
  }
}
