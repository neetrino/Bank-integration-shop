/**
 * API Route: Initialize Ameria Bank Payment
 * POST /api/payments/ameriabank/init
 * 
 * Creates a payment session and returns PaymentID for redirect
 */

import { NextRequest, NextResponse } from 'next/server'
import { getServerSession } from 'next-auth'
import { authOptions } from '@/lib/auth'
import { AmeriaBankService, getPaymentUrl, CURRENCY_CODES } from '@/lib/payments/ameriabank'
import { prisma } from '@/lib/prisma'
import { logger } from '@/lib/logger'

export async function POST(request: NextRequest) {
  try {
    const session = await getServerSession(authOptions)
    const body = await request.json()
    const { orderId, orderData, amount, currency = 'AMD', description, backUrl } = body

    // Validate required fields
    if (!amount) {
      return NextResponse.json(
        { error: 'Missing required field: amount is required' },
        { status: 400 }
      )
    }

    // Validate amount
    if (typeof amount !== 'number' || amount <= 0) {
      return NextResponse.json(
        { error: 'Invalid amount: must be a positive number' },
        { status: 400 }
      )
    }

    // If orderId is provided, validate order exists (for backward compatibility)
    // Otherwise, we expect orderData to create order after payment
    let existingOrder = null
    if (orderId) {
      existingOrder = await prisma.order.findUnique({
        where: { id: orderId },
        include: { items: true },
      })

      if (!existingOrder) {
        return NextResponse.json(
          { error: 'Order not found' },
          { status: 404 }
        )
      }

      // Check if order belongs to user (if authenticated)
      if (session?.user?.id && existingOrder.userId && existingOrder.userId !== session.user.id) {
        return NextResponse.json(
          { error: 'Unauthorized: order does not belong to user' },
          { status: 403 }
        )
      }
    } else if (!orderData) {
      return NextResponse.json(
        { error: 'Missing required field: either orderId or orderData is required' },
        { status: 400 }
      )
    }

    // Get currency code
    const currencyCode = CURRENCY_CODES[currency as keyof typeof CURRENCY_CODES] || CURRENCY_CODES.AMD

    // Generate OrderID for bank (must be integer)
    const isTest = process.env.AMERIA_BANK_ENVIRONMENT === 'test'
    let bankOrderID: number

    if (isTest) {
      // Generate random OrderID in test range
      bankOrderID = Math.floor(Math.random() * (3585000 - 3584001 + 1)) + 3584001
    } else {
      // Generate unique OrderID based on timestamp and random
      const timestamp = Date.now()
      const random = Math.floor(Math.random() * 10000)
      bankOrderID = (timestamp % 10000000) * 10000 + random
    }

    // Create payment description
    const paymentDescription = description || 'Order payment'

    // Get base URL for callback
    const baseUrl = process.env.NEXTAUTH_URL || request.headers.get('origin') || 'http://localhost:3000'
    const callbackUrl = backUrl || `${baseUrl}/api/payments/ameriabank/callback`

    // Prepare opaque data: if order exists, use orderId; otherwise encode orderData
    let opaqueData: string
    if (orderId) {
      // Backward compatibility: use orderId
      opaqueData = orderId
    } else {
      // Encode orderData as base64 JSON to pass through Opaque field
      // Opaque field has limited length, so we'll use a more compact format
      const orderDataWithSession = {
        ...orderData,
        userId: session?.user?.id || null,
      }
      opaqueData = Buffer.from(JSON.stringify(orderDataWithSession)).toString('base64')
    }

    // Initialize payment
    const ameriaService = new AmeriaBankService()
    const initResponse = await ameriaService.initPayment({
      OrderID: bankOrderID,
      Amount: amount,
      Currency: currencyCode,
      Description: paymentDescription,
      BackURL: callbackUrl,
      Opaque: opaqueData, // Store order data in Opaque for callback
    })

    logger.info('Ameria Bank payment initialized', {
      orderId: orderId || 'pending',
      paymentID: initResponse.PaymentID,
      bankOrderID,
      amount,
      hasOrderData: !!orderData,
    })

    // Get payment URL for redirect
    const environment = process.env.AMERIA_BANK_ENVIRONMENT === 'test' ? 'test' : 'production'
    const paymentUrl = getPaymentUrl(environment)

    return NextResponse.json({
      success: true,
      paymentID: initResponse.PaymentID,
      paymentUrl: `${paymentUrl}?id=${initResponse.PaymentID}&lang=en`, // Default to English, can be changed
      bankOrderID,
      message: initResponse.ResponseMessage,
    })
  } catch (error) {
    logger.error('Ameria Bank init payment error', error)

    const isDev = process.env.NODE_ENV === 'development'
    return NextResponse.json(
      {
        error: 'Failed to initialize payment',
        ...(isDev && error instanceof Error && { details: error.message }),
      },
      { status: 500 }
    )
  }
}
