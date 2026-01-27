/**
 * Ameria Bank vPOS Configuration
 */

export const AMERIA_BANK_CONFIG = {
  // Test environment URLs
  test: {
    apiUrl: 'https://servicestest.ameriabank.am/VPOS',
    paymentUrl: 'https://servicestest.ameriabank.am/VPOS/Payments/Pay',
    soapUrl: 'https://testpayments.ameriabank.am/Admin/webservice/TransactionsInformationService.svc?wsdl',
  },
  // Production environment URLs
  production: {
    apiUrl: 'https://services.ameriabank.am/VPOS',
    paymentUrl: 'https://services.ameriabank.am/VPOS/Payments/Pay',
    soapUrl: 'https://payments.ameriabank.am/Admin/webservice/TransactionsInformationService.svc?wsdl',
  },
} as const

export type Environment = 'test' | 'production'

export interface AmeriaBankCredentials {
  clientID: string
  username: string
  password: string
  environment: Environment
}

/**
 * Get Ameria Bank credentials from environment variables
 */
export function getAmeriaBankCredentials(): AmeriaBankCredentials {
  const isTest = process.env.AMERIA_BANK_ENVIRONMENT === 'test' || !process.env.AMERIA_BANK_CLIENT_ID

  return {
    clientID: process.env.AMERIA_BANK_CLIENT_ID || '',
    username: process.env.AMERIA_BANK_USERNAME || '',
    password: process.env.AMERIA_BANK_PASSWORD || '',
    environment: isTest ? 'test' : 'production',
  }
}

/**
 * Get API URL based on environment
 */
export function getApiUrl(environment: Environment): string {
  return AMERIA_BANK_CONFIG[environment].apiUrl
}

/**
 * Get payment redirect URL
 */
export function getPaymentUrl(environment: Environment): string {
  return AMERIA_BANK_CONFIG[environment].paymentUrl
}
