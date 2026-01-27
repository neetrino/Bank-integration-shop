/**
 * API Route: Ameria Bank Payment Callback
 * GET /api/payments/ameriabank/callback
 * 
 * Handles callback from Ameria Bank after payment
 * Bank redirects here with payment results
 */

import { NextRequest, NextResponse } from 'next/server'
import { AmeriaBankService, PaymentCallbackParams, OrderStatus } from '@/lib/payments/ameriabank'
import { prisma } from '@/lib/prisma'
import { logger } from '@/lib/logger'
import { redirect } from 'next/navigation'

export async function GET(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams

    // Bank sends parameters in lowercase (with typo: resposneCode instead of responseCode)
    const callbackParams: PaymentCallbackParams = {
      orderID: searchParams.get('orderID') || searchParams.get('orderid') || '',
      resposneCode: searchParams.get('resposneCode') || searchParams.get('resposnecode') || '',
      paymentID: searchParams.get('paymentID') || searchParams.get('paymentid') || '',
      opaque: searchParams.get('opaque') || searchParams.get('opaque') || undefined,
      currency: searchParams.get('currency') || undefined,
    }

    logger.info('Ameria Bank callback received', {
      paymentID: callbackParams.paymentID,
      orderID: callbackParams.orderID,
      responseCode: callbackParams.resposneCode,
      opaque: callbackParams.opaque,
    })

    // Validate required parameters
    if (!callbackParams.paymentID || !callbackParams.resposneCode) {
      logger.error('Missing required callback parameters', callbackParams)
      return NextResponse.redirect(
        new URL('/order-success?error=missing_parameters', request.url)
      )
    }

    // Get payment details from bank
    const ameriaService = new AmeriaBankService()
    const paymentDetails = await ameriaService.getPaymentDetails(callbackParams.paymentID)

    logger.info('Payment details retrieved', {
      paymentID: callbackParams.paymentID,
      orderID: paymentDetails.OrderID,
      responseCode: paymentDetails.ResponseCode,
      orderStatus: paymentDetails.OrderStatus,
      paymentState: paymentDetails.PaymentState,
    })

    // Identify order using Opaque (our order ID) or bank OrderID
    let orderId: string | null = null

    if (callbackParams.opaque) {
      // Use Opaque field which contains our order ID
      orderId = callbackParams.opaque
    } else {
      // Fallback: try to find order by bank OrderID (if we stored it)
      // This is less reliable, so prefer using Opaque
      logger.warn('No Opaque parameter, trying to find order by bank OrderID', {
        bankOrderID: paymentDetails.OrderID,
      })
    }

    if (!orderId) {
      logger.error('Cannot identify order from callback', {
        callbackParams,
        paymentDetails,
      })
      return NextResponse.redirect(
        new URL('/order-success?error=order_not_found', request.url)
      )
    }

    // Find order
    const order = await prisma.order.findUnique({
      where: { id: orderId },
    })

    if (!order) {
      logger.error('Order not found', { orderId })
      return NextResponse.redirect(
        new URL('/order-success?error=order_not_found', request.url)
      )
    }

    // Check payment status
    const isSuccessful = ameriaService.isPaymentSuccessful(paymentDetails)
    const isApproved = ameriaService.isPaymentApproved(paymentDetails)
    const isDeclined = ameriaService.isPaymentDeclined(paymentDetails)

    // Update order status based on payment result
    if (isSuccessful) {
      // Payment completed successfully
      await prisma.order.update({
        where: { id: orderId },
        data: {
          status: 'CONFIRMED',
          updatedAt: new Date(),
        },
      })

      logger.info('Order payment successful', {
        orderId,
        paymentID: callbackParams.paymentID,
        amount: paymentDetails.Amount,
      })

      // Redirect to success page
      return NextResponse.redirect(
        new URL(`/order-success?orderId=${orderId}&paymentId=${callbackParams.paymentID}`, request.url)
      )
    } else if (isApproved) {
      // Payment approved but not yet deposited (two-stage payment)
      // You might want to handle this differently
      await prisma.order.update({
        where: { id: orderId },
        data: {
          status: 'CONFIRMED', // Or create a new status like 'PREAUTHORIZED'
          updatedAt: new Date(),
        },
      })

      logger.info('Order payment approved (preauthorized)', {
        orderId,
        paymentID: callbackParams.paymentID,
      })

      return NextResponse.redirect(
        new URL(`/order-success?orderId=${orderId}&paymentId=${callbackParams.paymentID}&status=approved`, request.url)
      )
    } else if (isDeclined) {
      // Payment declined
      await prisma.order.update({
        where: { id: orderId },
        data: {
          status: 'CANCELLED',
          updatedAt: new Date(),
        },
      })

      logger.warn('Order payment declined', {
        orderId,
        paymentID: callbackParams.paymentID,
        responseCode: paymentDetails.ResponseCode,
        responseMessage: paymentDetails.TrxnDescription,
      })

      return NextResponse.redirect(
        new URL(`/order-success?error=payment_declined&orderId=${orderId}&message=${encodeURIComponent(paymentDetails.TrxnDescription || 'Payment declined')}`, request.url)
      )
    } else {
      // Unknown status
      logger.warn('Unknown payment status', {
        orderId,
        paymentID: callbackParams.paymentID,
        orderStatus: paymentDetails.OrderStatus,
        paymentState: paymentDetails.PaymentState,
        responseCode: paymentDetails.ResponseCode,
      })

      // Keep order as PENDING
      return NextResponse.redirect(
        new URL(`/order-success?error=unknown_status&orderId=${orderId}`, request.url)
      )
    }
  } catch (error) {
    logger.error('Ameria Bank callback error', error)

    // Redirect to error page
    return NextResponse.redirect(
      new URL('/order-success?error=callback_error', request.url)
    )
  }
}
