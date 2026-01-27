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
import { PaymentStatus } from '@prisma/client'
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
    if (!callbackParams.paymentID) {
      logger.error('Missing paymentID in callback', callbackParams)
      return NextResponse.redirect(
        new URL('/order-success?error=missing_payment_id', request.url)
      )
    }

    // Get payment details from bank
    const ameriaService = new AmeriaBankService()
    let paymentDetails
    
    try {
      paymentDetails = await ameriaService.getPaymentDetails(callbackParams.paymentID)
    } catch (error) {
      logger.error('Failed to get payment details', error)
      // Если не удалось получить детали, используем данные из callback параметров
      const orderId = callbackParams.opaque || ''
      const responseCode = callbackParams.resposneCode || 'unknown'
      
      // Определяем ошибку по responseCode из URL
      let errorMessage = 'Ошибка при обработке платежа'
      if (responseCode === '01') {
        errorMessage = 'Заказ с таким номером уже существует'
      } else if (responseCode === '0-1') {
        errorMessage = 'Таймаут обработки платежа'
      } else if (responseCode && responseCode !== '00') {
        errorMessage = `Платеж не прошел. Код ошибки: ${responseCode}`
      }
      
      return NextResponse.redirect(
        new URL(`/order-success?error=payment_failed&orderId=${orderId}&message=${encodeURIComponent(errorMessage)}&paymentId=${callbackParams.paymentID}&responseCode=${responseCode}`, request.url)
      )
    }

    logger.info('Payment details retrieved', {
      paymentID: callbackParams.paymentID,
      orderID: paymentDetails.OrderID,
      responseCode: paymentDetails.ResponseCode,
      orderStatus: paymentDetails.OrderStatus,
      paymentState: paymentDetails.PaymentState,
    })

    // Parse Opaque data - should be orderId (like WordPress plugin)
    // WordPress plugin passes only order_id in Opaque field
    let orderId: string | null = null

    if (callbackParams.opaque) {
      // Opaque should contain order ID (short string, like WordPress plugin)
      orderId = callbackParams.opaque
    }

    if (!orderId) {
      logger.error('Cannot identify order from callback - no Opaque parameter', {
        callbackParams,
        paymentDetails,
      })
      return NextResponse.redirect(
        new URL('/order-success?error=order_not_found', request.url)
      )
    }

    // Find existing order (order was created before payment initialization, like WordPress)
    const order = await prisma.order.findUnique({
      where: { id: orderId },
    })

    if (!order) {
      logger.error('Order not found', { orderId })
      return NextResponse.redirect(
        new URL('/order-success?error=order_not_found', request.url)
      )
    }

    // Check response code from callback URL first (more reliable for errors)
    const callbackResponseCode = callbackParams.resposneCode
    
    // Если responseCode из URL не "00", это ошибка
    // "00" - единственный код успешного платежа согласно документации
    if (callbackResponseCode && callbackResponseCode !== '00') {
      // Это ошибка платежа
      const responseCode = callbackResponseCode
      let errorMessage = 'Платеж не прошел'
      
      // Определяем ошибку по коду из документации
      if (responseCode === '01') {
        errorMessage = 'Заказ с таким номером уже существует'
      } else if (responseCode === '0-1') {
        errorMessage = 'Таймаут обработки платежа (превышено время ожидания)'
      } else if (responseCode === '0-100') {
        errorMessage = 'Попыток оплаты не было'
      } else if (responseCode === '0-2000') {
        errorMessage = 'Карта в черном списке'
      } else if (responseCode === '0-2001') {
        errorMessage = 'IP адрес в черном списке'
      } else if (responseCode === '0-2002') {
        errorMessage = 'Превышен лимит суммы платежа'
      } else if (responseCode === '0-2004') {
        errorMessage = 'SSL без CVC запрещен'
      } else if (responseCode === '0-2005') {
        errorMessage = 'Ошибка проверки подписи 3DSecure'
      } else if (responseCode === '0-2006') {
        errorMessage = '3DSecure отклонен банком-эмитентом'
      } else if (responseCode === '0-2007') {
        errorMessage = 'Превышено время ожидания ввода данных карты'
      } else if (responseCode === '0-2013') {
        errorMessage = 'Исчерпаны попытки оплаты'
      } else if (responseCode === '0100') {
        errorMessage = 'Банк-эмитент запретил онлайн транзакции'
      } else if (responseCode === '0101') {
        errorMessage = 'Срок действия карты истек'
      } else if (responseCode === '0111') {
        errorMessage = 'Неверный номер карты'
      } else if (responseCode === '0116') {
        errorMessage = 'Недостаточно средств на карте'
      } else if (responseCode === '02001') {
        errorMessage = 'Мошенническая транзакция'
      } else if (responseCode === '02003') {
        errorMessage = 'SSL транзакции запрещены для мерчанта'
      } else if (responseCode.startsWith('0-')) {
        errorMessage = `Ошибка обработки платежа: ${paymentDetails.TrxnDescription || paymentDetails.Description || responseCode}`
      } else if (responseCode !== '00') {
        // Общая ошибка с описанием от банка, если есть
        errorMessage = paymentDetails.TrxnDescription || 
                      paymentDetails.Description || 
                      `Платеж отклонен. Код ошибки: ${responseCode}`
      }
      
      logger.warn('Payment failed based on callback response code', {
        orderId,
        paymentID: callbackParams.paymentID,
        responseCode: callbackResponseCode,
        orderStatus: paymentDetails.OrderStatus,
        paymentState: paymentDetails.PaymentState,
        errorMessage,
        trxnDescription: paymentDetails.TrxnDescription,
      })
      
      // Payment failed - update order status to CANCELLED and payment status to FAILED
      await prisma.order.update({
        where: { id: orderId },
        data: {
          status: 'CANCELLED',
          paymentStatus: PaymentStatus.FAILED,
          updatedAt: new Date(),
        },
      })
      
      return NextResponse.redirect(
        new URL(`/order-success?error=payment_failed&orderId=${orderId}&message=${encodeURIComponent(errorMessage)}&paymentId=${callbackParams.paymentID}&responseCode=${responseCode}`, request.url)
      )
    }
    
    // Payment successful (responseCode = "00")
    // Check payment status
    const isSuccessful = ameriaService.isPaymentSuccessful(paymentDetails)
    const isApproved = ameriaService.isPaymentApproved(paymentDetails)

    // Update existing order status (order was created before payment, like WordPress plugin)
    if (isSuccessful) {
      // Payment completed successfully - update order to CONFIRMED and payment status to SUCCESS
      await prisma.order.update({
        where: { id: orderId },
        data: {
          status: 'CONFIRMED',
          paymentStatus: PaymentStatus.SUCCESS,
          updatedAt: new Date(),
        },
      })

      logger.info('Order payment successful', {
        orderId,
        paymentID: callbackParams.paymentID,
        amount: paymentDetails.Amount,
      })

      return NextResponse.redirect(
        new URL(`/order-success?orderId=${orderId}&paymentId=${callbackParams.paymentID}&clearCart=true`, request.url)
      )
    } else if (isApproved) {
      // Payment approved but not yet deposited (two-stage payment)
      await prisma.order.update({
        where: { id: orderId },
        data: {
          status: 'CONFIRMED', // Or keep PENDING if you want to wait for confirmation
          paymentStatus: PaymentStatus.PENDING, // Preauthorized, waiting for confirmation
          updatedAt: new Date(),
        },
      })

      logger.info('Order payment approved (preauthorized)', {
        orderId,
        paymentID: callbackParams.paymentID,
      })

      return NextResponse.redirect(
        new URL(`/order-success?orderId=${orderId}&paymentId=${callbackParams.paymentID}&status=approved&clearCart=true`, request.url)
      )
    } else {
      // Should not happen if responseCode = "00", but handle it
      logger.warn('Payment responseCode is 00 but status check failed', {
        orderId,
        paymentID: callbackParams.paymentID,
        orderStatus: paymentDetails.OrderStatus,
        paymentState: paymentDetails.PaymentState,
      })
      
      // Keep order as PENDING
      return NextResponse.redirect(
        new URL(`/order-success?orderId=${orderId}&paymentId=${callbackParams.paymentID}&clearCart=true`, request.url)
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
