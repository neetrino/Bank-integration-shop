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

    // Parse Opaque data - can be either orderId (string) or base64-encoded orderData (JSON)
    let orderId: string | null = null
    let orderData: any = null
    let existingOrder = null

    if (callbackParams.opaque) {
      try {
        // Try to decode as base64 JSON (new format with orderData)
        const decoded = Buffer.from(callbackParams.opaque, 'base64').toString('utf-8')
        const parsed = JSON.parse(decoded)
        
        // If it's an object with orderData structure, use it
        if (parsed && typeof parsed === 'object' && parsed.items) {
          orderData = parsed
          logger.info('Decoded orderData from Opaque', {
            hasItems: !!orderData.items,
            userId: orderData.userId,
          })
        } else {
          // Otherwise treat as orderId (backward compatibility)
          orderId = callbackParams.opaque
        }
      } catch (e) {
        // Not base64 JSON, treat as plain orderId (backward compatibility)
        orderId = callbackParams.opaque
      }
    }

    // If we have orderId, try to find existing order (backward compatibility)
    if (orderId) {
      existingOrder = await prisma.order.findUnique({
        where: { id: orderId },
      })
    }

    // If no orderData and no existing order, we can't proceed
    if (!orderData && !existingOrder) {
      logger.error('Cannot identify order from callback - no orderData and no existing order', {
        callbackParams,
        paymentDetails,
        hasOpaque: !!callbackParams.opaque,
      })
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
        orderId: orderId || 'pending',
        paymentID: callbackParams.paymentID,
        responseCode: callbackResponseCode,
        orderStatus: paymentDetails.OrderStatus,
        paymentState: paymentDetails.PaymentState,
        errorMessage,
        trxnDescription: paymentDetails.TrxnDescription,
        hasOrderData: !!orderData,
      })
      
      // Payment failed - DON'T create order, just show error
      // If order already exists (backward compatibility), don't update it
      const errorOrderId = existingOrder?.id || null
      return NextResponse.redirect(
        new URL(`/order-success?error=payment_failed${errorOrderId ? `&orderId=${errorOrderId}` : ''}&message=${encodeURIComponent(errorMessage)}&paymentId=${callbackParams.paymentID}&responseCode=${responseCode}`, request.url)
      )
    }
    
    // Payment successful (responseCode = "00")
    // Check payment status
    const isSuccessful = ameriaService.isPaymentSuccessful(paymentDetails)
    const isApproved = ameriaService.isPaymentApproved(paymentDetails)

    // Create order if it doesn't exist (new flow)
    let finalOrderId: string
    if (!existingOrder && orderData) {
      // Create order from orderData
      const newOrder = await prisma.order.create({
        data: {
          userId: orderData.userId || null,
          name: orderData.name || 'Guest Customer',
          status: isSuccessful ? 'CONFIRMED' : 'PENDING',
          total: orderData.total,
          address: orderData.address,
          phone: orderData.phone,
          notes: orderData.notes || null,
          paymentMethod: orderData.paymentMethod,
          deliveryTime: orderData.deliveryTime || null,
          items: {
            create: orderData.items.map((item: any) => ({
              productId: item.productId,
              quantity: item.quantity,
              price: item.price,
            })),
          },
        },
        include: {
          items: true,
        },
      })
      
      finalOrderId = newOrder.id
      logger.info('Order created after successful payment', {
        orderId: finalOrderId,
        paymentID: callbackParams.paymentID,
        amount: paymentDetails.Amount,
        status: newOrder.status,
      })
    } else if (existingOrder) {
      // Update existing order (backward compatibility)
      finalOrderId = existingOrder.id
      await prisma.order.update({
        where: { id: finalOrderId },
        data: {
          status: isSuccessful ? 'CONFIRMED' : 'PENDING',
          updatedAt: new Date(),
        },
      })
      
      logger.info('Order updated after successful payment', {
        orderId: finalOrderId,
        paymentID: callbackParams.paymentID,
        amount: paymentDetails.Amount,
      })
    } else {
      // Should not happen, but handle gracefully
      logger.error('Cannot create or update order - no orderData and no existing order', {
        callbackParams,
        paymentDetails,
      })
      return NextResponse.redirect(
        new URL('/order-success?error=order_creation_failed', request.url)
      )
    }

    // Redirect to success page
    if (isSuccessful) {
      return NextResponse.redirect(
        new URL(`/order-success?orderId=${finalOrderId}&paymentId=${callbackParams.paymentID}&clearCart=true`, request.url)
      )
    } else if (isApproved) {
      // Payment approved but not yet deposited (two-stage payment)
      return NextResponse.redirect(
        new URL(`/order-success?orderId=${finalOrderId}&paymentId=${callbackParams.paymentID}&status=approved&clearCart=true`, request.url)
      )
    } else {
      // Should not happen if responseCode = "00", but handle it
      logger.warn('Payment responseCode is 00 but status check failed', {
        orderId: finalOrderId,
        paymentID: callbackParams.paymentID,
        orderStatus: paymentDetails.OrderStatus,
        paymentState: paymentDetails.PaymentState,
      })
      
      return NextResponse.redirect(
        new URL(`/order-success?orderId=${finalOrderId}&paymentId=${callbackParams.paymentID}&clearCart=true`, request.url)
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
