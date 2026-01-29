/**
 * IDram Payment API Types
 * Based on Idram Merchant API documentation
 */

/** Form fields for payment initiation (POST to GetPayment) */
export interface IdramPaymentFormParams {
  EDP_LANGUAGE: 'RU' | 'EN' | 'AM'
  EDP_REC_ACCOUNT: string
  EDP_DESCRIPTION: string
  EDP_AMOUNT: string // "1900.00" - dot as decimal separator
  EDP_BILL_NO: string // Order ID in merchant system
  EDP_EMAIL?: string
  /** Additional fields (no EDP_ prefix) are echoed back in callbacks */
  [key: string]: string | undefined
}

/** First callback: precheck (EDP_PRECHECK=YES) */
export interface IdramPrecheckParams {
  EDP_PRECHECK: 'YES'
  EDP_BILL_NO: string
  EDP_REC_ACCOUNT: string
  EDP_AMOUNT: string
}

/** Second callback: payment confirmation */
export interface IdramPaymentConfirmParams {
  EDP_BILL_NO: string
  EDP_REC_ACCOUNT: string
  EDP_PAYER_ACCOUNT: string
  EDP_AMOUNT: string // format 0.00
  EDP_TRANS_ID: string // 14 chars
  EDP_TRANS_DATE: string // dd/mm/yyyy
  EDP_CHECKSUM: string
}
