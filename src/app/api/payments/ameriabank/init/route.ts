/**
 * API Route: Initialize Ameria Bank Payment
 * POST /api/payments/ameriabank/init
 * 
 * Creates a payment session and returns PaymentID for redirect
 */

import { NextRequest, NextResponse } from 'next/server'
import { getServerSession } from 'next-auth'
import { authOptions } from '@/lib/auth'
import { AmeriaBankService, getPaymentUrl, CURRENCY_CODES, getAmeriaBankCredentials } from '@/lib/payments/ameriabank'
import { prisma } from '@/lib/prisma'
import { logger } from '@/lib/logger'

export async function POST(request: NextRequest) {
  try {
    const session = await getServerSession(authOptions)
    const body = await request.json()
    const { orderId, amount, currency = 'AMD', description, backUrl } = body

    // Validate required fields
    if (!orderId || !amount) {
      return NextResponse.json(
        { error: 'Missing required fields: orderId and amount are required' },
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

    // Validate order exists (order was created before payment initialization, like WordPress)
    const order = await prisma.order.findUnique({
      where: { id: orderId },
      include: { items: true },
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

    // Validate order amount matches
    if (Math.abs(order.total - amount) > 0.01) {
      logger.warn('Order amount mismatch', {
        orderId,
        orderTotal: order.total,
        requestedAmount: amount,
      })
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

    // Validate credentials are set
    const credentials = getAmeriaBankCredentials()
    if (!credentials.clientID || !credentials.username || !credentials.password) {
      logger.error('Ameria Bank credentials not configured', {
        hasClientID: !!credentials.clientID,
        hasUsername: !!credentials.username,
        hasPassword: !!credentials.password,
      })
      return NextResponse.json(
        { error: 'Payment gateway not configured. Please contact support.' },
        { status: 500 }
      )
    }

    // Initialize payment
    // Pass only orderId in Opaque (like WordPress plugin does - short string, won't be truncated)
    const ameriaService = new AmeriaBankService()
    const initResponse = await ameriaService.initPayment({
      OrderID: bankOrderID,
      Amount: amount,
      Currency: currencyCode,
      Description: paymentDescription,
      BackURL: callbackUrl,
      Opaque: orderId, // Store only order ID (short string, like WordPress plugin)
    })

    logger.info('Ameria Bank payment initialized', {
      orderId,
      paymentID: initResponse.PaymentID,
      bankOrderID,
      amount,
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
    
    // Log detailed error information
    if (error instanceof Error) {
      logger.error('Error details', {
        name: error.name,
        message: error.message,
        stack: error.stack,
      })
    }

    return NextResponse.json(
      {
        error: 'Failed to initialize payment',
        ...(isDev && error instanceof Error && { 
          details: error.message,
          stack: error.stack 
        }),
      },
      { status: 500 }
    )
  }
}
