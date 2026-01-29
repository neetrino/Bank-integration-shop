/**
 * API Route: IDram RESULT_URL callback
 * POST /api/payments/idram/callback
 *
 * Handles two POST requests from Idram:
 * 1) Precheck (EDP_PRECHECK=YES) — validate order, return "OK"
 * 2) Payment confirmation — verify EDP_CHECKSUM, update order, return "OK"
 * Response must be exactly "OK", HTTP 200, Content-Type: text/plain.
 */

import { NextRequest } from 'next/server'
import { prisma } from '@/lib/prisma'
import { logger } from '@/lib/logger'
import { getIdramCredentials, verifyIdramChecksum } from '@/lib/payments/idram'
import { PaymentStatus } from '@prisma/client'

const PLAIN_OK = () =>
  new Response('OK', {
    status: 200,
    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
  })

const PLAIN_ERROR = (message: string, status: number) =>
  new Response(message, {
    status,
    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
  })

export async function POST(request: NextRequest) {
  try {
    const formData = await request.formData()
    const data = Object.fromEntries(formData.entries()) as Record<string, string>

    logger.info('IDram callback received', {
      keys: Object.keys(data),
      hasPrecheck: formData.get('EDP_PRECHECK') === 'YES',
      hasChecksum: formData.has('EDP_CHECKSUM'),
    })

    const credentials = getIdramCredentials()
    if (!credentials.recAccount || !credentials.secretKey) {
      logger.error('IDram credentials not configured in callback')
      return PLAIN_ERROR('Invalid configuration', 500)
    }

    const recAccount = (formData.get('EDP_REC_ACCOUNT') as string) || ''
    if (recAccount !== credentials.recAccount) {
      logger.error('Invalid EDP_REC_ACCOUNT', { recAccount, expected: credentials.recAccount })
      return PLAIN_ERROR('Invalid merchant account', 400)
    }

    // ——— Precheck (EDP_PRECHECK=YES) ———
    if (formData.get('EDP_PRECHECK') === 'YES') {
      const billNo = (formData.get('EDP_BILL_NO') as string) || ''
      const amountStr = (formData.get('EDP_AMOUNT') as string) || ''
      const amount = parseFloat(amountStr)

      if (!billNo) {
        return PLAIN_ERROR('Missing EDP_BILL_NO', 400)
      }

      const order = await prisma.order.findUnique({ where: { id: billNo } })
      if (!order) {
        logger.error('IDram precheck: order not found', { billNo })
        return PLAIN_ERROR('Order not found', 400)
      }

      const orderAmount = parseFloat(order.total.toFixed(2))
      if (Number.isNaN(amount) || Math.abs(amount - orderAmount) > 0.01) {
        logger.error('IDram precheck: amount mismatch', { amount, orderAmount, billNo })
        return PLAIN_ERROR('Amount mismatch', 400)
      }

      logger.info('IDram precheck OK', { billNo, amount: orderAmount })
      return PLAIN_OK()
    }

    // ——— Payment confirmation ———
    if (
      !formData.has('EDP_PAYER_ACCOUNT') ||
      !formData.has('EDP_CHECKSUM') ||
      !formData.has('EDP_TRANS_ID')
    ) {
      logger.error('IDram callback: missing payment confirmation fields', data)
      return PLAIN_ERROR('Invalid request', 400)
    }

    const billNo = (formData.get('EDP_BILL_NO') as string) || ''
    const payerAccount = (formData.get('EDP_PAYER_ACCOUNT') as string) || ''
    const amountStr = (formData.get('EDP_AMOUNT') as string) || ''
    const transId = (formData.get('EDP_TRANS_ID') as string) || ''
    const transDate = (formData.get('EDP_TRANS_DATE') as string) || ''
    const checksum = (formData.get('EDP_CHECKSUM') as string) || ''

    if (!billNo) {
      return PLAIN_ERROR('Missing EDP_BILL_NO', 400)
    }

    const order = await prisma.order.findUnique({ where: { id: billNo } })
    if (!order) {
      logger.error('IDram confirm: order not found', { billNo })
      return PLAIN_ERROR('Order not found', 400)
    }

    const orderAmountStr = order.total.toFixed(2)
    const paymentAmount = parseFloat(amountStr)
    const orderAmount = parseFloat(orderAmountStr)
    if (Number.isNaN(paymentAmount) || Math.abs(paymentAmount - orderAmount) > 0.01) {
      logger.error('IDram confirm: amount mismatch', { paymentAmount, orderAmount, billNo })
      return PLAIN_ERROR('Amount mismatch', 400)
    }

    const isValid = verifyIdramChecksum({
      recAccount,
      amountFromOrder: orderAmountStr,
      secretKey: credentials.secretKey,
      billNo,
      payerAccount,
      transId,
      transDate,
      receivedChecksum: checksum,
    })

    if (!isValid) {
      logger.error('IDram confirm: invalid checksum', { billNo, transId })
      await prisma.order.update({
        where: { id: billNo },
        data: { paymentStatus: PaymentStatus.FAILED, updatedAt: new Date() },
      })
      return PLAIN_ERROR('Invalid checksum', 400)
    }

    // Idempotency: already paid
    if (order.paymentStatus === PaymentStatus.SUCCESS) {
      logger.info('IDram confirm: order already paid', { billNo })
      return PLAIN_OK()
    }

    await prisma.order.update({
      where: { id: billNo },
      data: {
        status: 'CONFIRMED',
        paymentStatus: PaymentStatus.SUCCESS,
        paymentTransactionId: transId, // IDram EDP_TRANS_ID (for reference; refund via Idram support)
        updatedAt: new Date(),
      },
    })

    logger.info('IDram payment confirmed', { billNo, transId, amount: orderAmountStr })
    return PLAIN_OK()
  } catch (error) {
    logger.error('IDram callback error', error)
    return PLAIN_ERROR('Internal server error', 500)
  }
}
