/**
 * IDram checksum (EDP_CHECKSUM) verification
 * Document: EDP_CHECKSUM = MD5(EDP_REC_ACCOUNT:EDP_AMOUNT:SECRET_KEY:EDP_BILL_NO:EDP_PAYER_ACCOUNT:EDP_TRANS_ID:EDP_TRANS_DATE)
 * Use ORDER amount from DB, not from request (for security)
 */

import { createHash } from 'crypto'

/**
 * Compute MD5 hex string (for EDP_CHECKSUM)
 */
export function md5(text: string): string {
  return createHash('md5').update(text, 'utf8').digest('hex')
}

/**
 * Verify IDram payment confirmation checksum.
 * Amount must be from merchant DB (order total), not from request.
 */
export function verifyIdramChecksum(params: {
  recAccount: string
  amountFromOrder: string // e.g. order.total.toFixed(2)
  secretKey: string
  billNo: string
  payerAccount: string
  transId: string
  transDate: string
  receivedChecksum: string
}): boolean {
  const str =
    `${params.recAccount}:${params.amountFromOrder}:${params.secretKey}:${params.billNo}:${params.payerAccount}:${params.transId}:${params.transDate}`
  const calculated = md5(str).toUpperCase()
  const received = (params.receivedChecksum || '').toUpperCase()
  return calculated === received
}
