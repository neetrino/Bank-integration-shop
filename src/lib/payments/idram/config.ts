/**
 * IDram Payment Configuration
 * Based on Idram Merchant API documentation
 */

export const IDRAM_CONFIG = {
  /** Form POST URL for payment initiation */
  paymentFormUrl: 'https://banking.idram.am/Payment/GetPayment',
  /** Test form URL (same as prod for Idram) */
  testPaymentFormUrl: 'https://banking.idram.am/Payment/GetPayment',
} as const

export type IdramEnvironment = 'test' | 'production'

export interface IdramCredentials {
  /** Merchant IdramID (EDP_REC_ACCOUNT) */
  recAccount: string
  /** Secret key for checksum verification */
  secretKey: string
  /** Email for payment confirmations (optional) */
  email?: string
  environment: IdramEnvironment
}

/**
 * Get IDram credentials from environment variables
 */
export function getIdramCredentials(): IdramCredentials {
  const isTest = process.env.IDRAM_TEST_MODE === 'true' || !process.env.IDRAM_REC_ACCOUNT

  if (isTest) {
    return {
      recAccount: process.env.IDRAM_TEST_REC_ACCOUNT || '',
      secretKey: process.env.IDRAM_TEST_SECRET_KEY || '',
      email: process.env.IDRAM_EMAIL,
      environment: 'test',
    }
  }

  return {
    recAccount: process.env.IDRAM_REC_ACCOUNT || '',
    secretKey: process.env.IDRAM_SECRET_KEY || '',
    email: process.env.IDRAM_EMAIL,
    environment: 'production',
  }
}

/**
 * Get payment form URL (same for test and production)
 */
export function getIdramPaymentFormUrl(): string {
  return IDRAM_CONFIG.paymentFormUrl
}
