import { NextRequest, NextResponse } from 'next/server'
import { prisma } from '@/lib/prisma'
import { getServerSession } from 'next-auth/next'
import { authOptions } from '@/lib/auth'
import { AmeriaBankService } from '@/lib/payments/ameriabank'
import { PaymentStatus } from '@prisma/client'

/**
 * POST /api/admin/orders/[id]/cancel
 * Cancel payment (Ameria Bank only). Sends cancel/void request to bank and updates order paymentStatus to CANCELLED.
 * Ameria: available within 72 hours from payment initialization.
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

    if (!order.paymentTransactionId) {
      return NextResponse.json(
        { error: 'Order has no payment transaction ID (cannot cancel payment)' },
        { status: 400 }
      )
    }

    // Only Ameria Bank supports cancel via API
    if (order.paymentMethod?.toLowerCase() !== 'ameriabank') {
      return NextResponse.json(
        {
          error:
            'Cancel payment via this action is only supported for Ameria Bank. For IDram, contact Idram support with transaction ID: ' +
            (order.paymentTransactionId || 'N/A'),
        },
        { status: 400 }
      )
    }

    // Allow cancel for PENDING (not yet paid) or SUCCESS (void)
    if (
      order.paymentStatus !== 'SUCCESS' &&
      order.paymentStatus !== 'PENDING'
    ) {
      return NextResponse.json(
        {
          error:
            'Cancel is only allowed for orders with payment status PENDING or SUCCESS',
        },
        { status: 400 }
      )
    }

    const ameria = new AmeriaBankService()
    const cancelResult = await ameria.cancelPayment(order.paymentTransactionId)

    if (cancelResult.ResponseCode !== '00') {
      return NextResponse.json(
        {
          error:
            cancelResult.ResponseMessage ||
            `Bank returned code: ${cancelResult.ResponseCode}`,
        },
        { status: 502 }
      )
    }

    await prisma.order.update({
      where: { id },
      data: { paymentStatus: PaymentStatus.CANCELLED },
    })

    return NextResponse.json({
      success: true,
      message: 'Payment cancelled successfully',
      paymentStatus: 'CANCELLED',
    })
  } catch (error) {
    console.error('Admin cancel payment error:', error)
    return NextResponse.json(
      {
        error:
          error instanceof Error
            ? error.message
            : 'Failed to cancel payment',
      },
      { status: 500 }
    )
  }
}
