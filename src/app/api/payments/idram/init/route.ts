/**
 * API Route: Initialize IDram Payment
 * POST /api/payments/idram/init
 *
 * Returns formUrl and formData for client to POST to Idram (https://banking.idram.am/Payment/GetPayment).
 * Order must be created before calling this (same flow as Ameria).
 */

import { NextRequest, NextResponse } from 'next/server'
import { getServerSession } from 'next-auth'
import { authOptions } from '@/lib/auth'
import { getIdramCredentials, getIdramPaymentFormUrl } from '@/lib/payments/idram'
import { prisma } from '@/lib/prisma'
import { logger } from '@/lib/logger'

const OK_RESPONSE = (body: object) =>
  NextResponse.json(body, { status: 200 })

export async function POST(request: NextRequest) {
  try {
    const session = await getServerSession(authOptions)
    const body = await request.json()
    const { orderId } = body

    if (!orderId) {
      return NextResponse.json(
        { error: 'Missing required field: orderId' },
        { status: 400 }
      )
    }

    const credentials = getIdramCredentials()
    if (!credentials.recAccount || !credentials.secretKey) {
      logger.error('IDram credentials not configured', {
        hasRecAccount: !!credentials.recAccount,
        hasSecretKey: !!credentials.secretKey,
      })
      return NextResponse.json(
        { error: 'Payment gateway not configured. Please contact support.' },
        { status: 500 }
      )
    }

    const order = await prisma.order.findUnique({
      where: { id: orderId },
      include: { items: true },
    })

    if (!order) {
      return NextResponse.json({ error: 'Order not found' }, { status: 404 })
    }

    if (session?.user?.id && order.userId && order.userId !== session.user.id) {
      return NextResponse.json(
        { error: 'Unauthorized: order does not belong to user' },
        { status: 403 }
      )
    }

    // Idram accepts only AMD; amount format "1900.00" (dot)
    const amount = Number(order.total)
    if (amount <= 0) {
      return NextResponse.json(
        { error: 'Invalid order amount' },
        { status: 400 }
      )
    }
    const amountStr = amount.toFixed(2)

    const formUrl = getIdramPaymentFormUrl()
    const formData: Record<string, string> = {
      EDP_LANGUAGE: 'RU',
      EDP_REC_ACCOUNT: credentials.recAccount,
      EDP_DESCRIPTION: `Order #${orderId}`,
      EDP_AMOUNT: amountStr,
      EDP_BILL_NO: orderId,
    }
    if (credentials.email) {
      formData.EDP_EMAIL = credentials.email
    }

    logger.info('IDram payment init', {
      orderId,
      amount: amountStr,
      recAccount: credentials.recAccount,
    })

    return OK_RESPONSE({
      success: true,
      formUrl,
      formData,
    })
  } catch (error) {
    logger.error('IDram init payment error', error)
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
