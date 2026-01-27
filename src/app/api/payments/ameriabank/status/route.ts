/**
 * API Route: Check Ameria Bank Payment Status
 * POST /api/payments/ameriabank/status
 * 
 * Allows checking payment status by PaymentID
 * Useful for polling or manual status checks
 */

import { NextRequest, NextResponse } from 'next/server'
import { getServerSession } from 'next-auth'
import { authOptions } from '@/lib/auth'
import { AmeriaBankService } from '@/lib/payments/ameriabank'
import { prisma } from '@/lib/prisma'
import { logger } from '@/lib/logger'

export async function POST(request: NextRequest) {
  try {
    const session = await getServerSession(authOptions)
    const body = await request.json()
    const { paymentID, orderId } = body

    if (!paymentID) {
      return NextResponse.json(
        { error: 'Missing required field: paymentID' },
        { status: 400 }
      )
    }

    // If orderId is provided, verify it exists and user has access
    if (orderId) {
      const order = await prisma.order.findUnique({
        where: { id: orderId },
      })

      if (!order) {
        return NextResponse.json(
          { error: 'Order not found' },
          { status: 404 }
        )
      }

      // Check if order belongs to user (if authenticated)
      if (session?.user?.id && order.userId && order.userId !== session.user.id) {
        return NextResponse.json(
          { error: 'Unauthorized: order does not belong to user' },
          { status: 403 }
        )
      }
    }

    // Get payment details from bank
    const ameriaService = new AmeriaBankService()
    const paymentDetails = await ameriaService.getPaymentDetails(paymentID)

    // Determine payment status
    const isSuccessful = ameriaService.isPaymentSuccessful(paymentDetails)
    const isApproved = ameriaService.isPaymentApproved(paymentDetails)
    const isDeclined = ameriaService.isPaymentDeclined(paymentDetails)

    return NextResponse.json({
      success: true,
      paymentID,
      status: {
        isSuccessful,
        isApproved,
        isDeclined,
        orderStatus: paymentDetails.OrderStatus,
        paymentState: paymentDetails.PaymentState,
        responseCode: paymentDetails.ResponseCode,
      },
      details: {
        amount: paymentDetails.Amount,
        currency: paymentDetails.Currency,
        orderID: paymentDetails.OrderID,
        cardNumber: paymentDetails.CardNumber,
        clientName: paymentDetails.ClientName,
        dateTime: paymentDetails.DateTime,
        description: paymentDetails.Description,
      },
    })
  } catch (error) {
    logger.error('Ameria Bank status check error', error)

    const isDev = process.env.NODE_ENV === 'development'
    return NextResponse.json(
      {
        error: 'Failed to check payment status',
        ...(isDev && error instanceof Error && { details: error.message }),
      },
      { status: 500 }
    )
  }
}
