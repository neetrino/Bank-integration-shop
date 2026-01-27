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

    // Validate order exists
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
    // In test mode, use range 3584001-3585000 as per bank instructions
    // In production, use order ID converted to integer
    const isTest = process.env.AMERIA_BANK_ENVIRONMENT === 'test'
    let bankOrderID: number

    if (isTest) {
      // Generate random OrderID in test range
      bankOrderID = Math.floor(Math.random() * (3585000 - 3584001 + 1)) + 3584001
    } else {
      // Convert order ID to integer (use hash or numeric part)
      // For now, use a hash of the order ID to get a consistent integer
      const hash = orderId.split('').reduce((acc, char) => {
        const charCode = char.charCodeAt(0)
        return ((acc << 5) - acc) + charCode
      }, 0)
      bankOrderID = Math.abs(hash) % 10000000 // Ensure it's a reasonable size
    }

    // Create payment description
    const paymentDescription = description || `Order #${orderId}`

    // Get base URL for callback
    const baseUrl = process.env.NEXTAUTH_URL || request.headers.get('origin') || 'http://localhost:3000'
    const callbackUrl = backUrl || `${baseUrl}/api/payments/ameriabank/callback`

    // Initialize payment
    const ameriaService = new AmeriaBankService()
    const initResponse = await ameriaService.initPayment({
      OrderID: bankOrderID,
      Amount: amount,
      Currency: currencyCode,
      Description: paymentDescription,
      BackURL: callbackUrl,
      Opaque: orderId, // Store our order ID in Opaque for callback identification
    })

    // Store payment information in database (you may want to create a Payment model)
    // For now, we'll store it in order metadata or create a separate payment record
    // This is a simplified version - you might want to create a proper Payment model

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
    return NextResponse.json(
      {
        error: 'Failed to initialize payment',
        ...(isDev && error instanceof Error && { details: error.message }),
      },
      { status: 500 }
    )
  }
}
