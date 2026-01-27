/**
 * Ameria Bank vPOS 3.1 API Service
 * Implements all API methods according to official documentation
 */

import {
  InitPaymentRequest,
  InitPaymentResponse,
  GetPaymentDetailsRequest,
  GetPaymentDetailsResponse,
  ConfirmPaymentRequest,
  ConfirmPaymentResponse,
  CancelPaymentRequest,
  CancelPaymentResponse,
  RefundPaymentRequest,
  RefundPaymentResponse,
  MakeBindingPaymentRequest,
  MakeBindingPaymentResponse,
  GetBindingsRequest,
  GetBindingsResponse,
  DeactivateBindingRequest,
  DeactivateBindingResponse,
  ActivateBindingRequest,
  ActivateBindingResponse,
  CURRENCY_CODES,
  CurrencyCode,
} from './types'
import { getApiUrl, getAmeriaBankCredentials, Environment } from './config'
import { logger } from '@/lib/logger'

export class AmeriaBankService {
  private apiUrl: string
  private credentials: ReturnType<typeof getAmeriaBankCredentials>
  private environment: Environment

  constructor(environment?: Environment) {
    this.credentials = getAmeriaBankCredentials()
    this.environment = environment || this.credentials.environment
    this.apiUrl = getApiUrl(this.environment)
  }

  /**
   * Initialize payment - Step 1
   * Returns PaymentID which is used to redirect user to payment page
   */
  async initPayment(request: Omit<InitPaymentRequest, 'ClientID' | 'Username' | 'Password'>): Promise<InitPaymentResponse> {
    const fullRequest: InitPaymentRequest = {
      ...request,
      ClientID: this.credentials.clientID,
      Username: this.credentials.username,
      Password: this.credentials.password,
      Currency: request.Currency || CURRENCY_CODES.AMD, // Default to AMD
      Description: request.Description || `Order #${request.OrderID}`, // Ensure description is not empty
    }

    logger.debug('AmeriaBank InitPayment request', {
      OrderID: fullRequest.OrderID,
      Amount: fullRequest.Amount,
      Currency: fullRequest.Currency,
    })

    try {
      const response = await fetch(`${this.apiUrl}/api/VPOS/InitPayment`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(fullRequest),
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data: InitPaymentResponse = await response.json()

      logger.debug('AmeriaBank InitPayment response', {
        PaymentID: data.PaymentID,
        ResponseCode: data.ResponseCode,
        ResponseMessage: data.ResponseMessage,
      })

      if (data.ResponseCode !== 1) {
        throw new Error(`InitPayment failed: ${data.ResponseMessage} (Code: ${data.ResponseCode})`)
      }

      return data
    } catch (error) {
      logger.error('AmeriaBank InitPayment error', error)
      throw error
    }
  }

  /**
   * Get payment details - Step 2 (after callback)
   * Used to verify payment status after user returns from bank
   */
  async getPaymentDetails(paymentID: string): Promise<GetPaymentDetailsResponse> {
    const request: GetPaymentDetailsRequest = {
      PaymentID: paymentID,
      Username: this.credentials.username,
      Password: this.credentials.password,
    }

    logger.debug('AmeriaBank GetPaymentDetails request', { PaymentID: paymentID })

    try {
      const response = await fetch(`${this.apiUrl}/api/VPOS/GetPaymentDetails`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(request),
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data: GetPaymentDetailsResponse = await response.json()

      logger.debug('AmeriaBank GetPaymentDetails response', {
        OrderID: data.OrderID,
        ResponseCode: data.ResponseCode,
        OrderStatus: data.OrderStatus,
        PaymentState: data.PaymentState,
      })

      return data
    } catch (error) {
      logger.error('AmeriaBank GetPaymentDetails error', error)
      throw error
    }
  }

  /**
   * Confirm payment (for two-stage payments)
   * Used to complete preauthorized payment
   */
  async confirmPayment(paymentID: string, amount: number): Promise<ConfirmPaymentResponse> {
    const request: ConfirmPaymentRequest = {
      PaymentID: paymentID,
      Username: this.credentials.username,
      Password: this.credentials.password,
      Amount: amount,
    }

    logger.debug('AmeriaBank ConfirmPayment request', { PaymentID: paymentID, Amount: amount })

    try {
      const response = await fetch(`${this.apiUrl}/api/VPOS/ConfirmPayment`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(request),
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data: ConfirmPaymentResponse = await response.json()

      logger.debug('AmeriaBank ConfirmPayment response', {
        ResponseCode: data.ResponseCode,
        ResponseMessage: data.ResponseMessage,
      })

      return data
    } catch (error) {
      logger.error('AmeriaBank ConfirmPayment error', error)
      throw error
    }
  }

  /**
   * Cancel payment
   * Available within 72 hours from payment initialization
   */
  async cancelPayment(paymentID: string): Promise<CancelPaymentResponse> {
    const request: CancelPaymentRequest = {
      PaymentID: paymentID,
      Username: this.credentials.username,
      Password: this.credentials.password,
    }

    logger.debug('AmeriaBank CancelPayment request', { PaymentID: paymentID })

    try {
      const response = await fetch(`${this.apiUrl}/api/VPOS/CancelPayment`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(request),
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data: CancelPaymentResponse = await response.json()

      logger.debug('AmeriaBank CancelPayment response', {
        ResponseCode: data.ResponseCode,
        ResponseMessage: data.ResponseMessage,
      })

      return data
    } catch (error) {
      logger.error('AmeriaBank CancelPayment error', error)
      throw error
    }
  }

  /**
   * Refund payment (partial or full)
   * Amount must not exceed transaction amount
   */
  async refundPayment(paymentID: string, amount: number): Promise<RefundPaymentResponse> {
    const request: RefundPaymentRequest = {
      PaymentID: paymentID,
      Username: this.credentials.username,
      Password: this.credentials.password,
      Amount: amount,
    }

    logger.debug('AmeriaBank RefundPayment request', { PaymentID: paymentID, Amount: amount })

    try {
      const response = await fetch(`${this.apiUrl}/api/VPOS/RefundPayment`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(request),
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data: RefundPaymentResponse = await response.json()

      logger.debug('AmeriaBank RefundPayment response', {
        ResponseCode: data.ResponseCode,
        ResponseMessage: data.ResponseMessage,
      })

      return data
    } catch (error) {
      logger.error('AmeriaBank RefundPayment error', error)
      throw error
    }
  }

  /**
   * Make binding payment (recurring payment using saved card)
   */
  async makeBindingPayment(
    request: Omit<MakeBindingPaymentRequest, 'ClientID' | 'Username' | 'Password'>
  ): Promise<MakeBindingPaymentResponse> {
    const fullRequest: MakeBindingPaymentRequest = {
      ...request,
      ClientID: this.credentials.clientID,
      Username: this.credentials.username,
      Password: this.credentials.password,
      Currency: request.Currency || CURRENCY_CODES.AMD,
      Description: request.Description || `Order #${request.OrderID}`,
    }

    logger.debug('AmeriaBank MakeBindingPayment request', {
      OrderID: fullRequest.OrderID,
      CardHolderID: fullRequest.CardHolderID,
      Amount: fullRequest.Amount,
    })

    try {
      const response = await fetch(`${this.apiUrl}/api/VPOS/MakeBindingPayment`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(fullRequest),
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data: MakeBindingPaymentResponse = await response.json()

      logger.debug('AmeriaBank MakeBindingPayment response', {
        PaymentID: data.PaymentID,
        ResponseCode: data.ResponseCode,
        OrderStatus: data.OrderStatus,
      })

      return data
    } catch (error) {
      logger.error('AmeriaBank MakeBindingPayment error', error)
      throw error
    }
  }

  /**
   * Get bindings (saved cards) for a user
   */
  async getBindings(paymentType: number): Promise<GetBindingsResponse> {
    const request: GetBindingsRequest = {
      ClientID: this.credentials.clientID,
      Username: this.credentials.username,
      Password: this.credentials.password,
      PaymentType: paymentType,
    }

    logger.debug('AmeriaBank GetBindings request', { PaymentType: paymentType })

    try {
      const response = await fetch(`${this.apiUrl}/api/VPOS/GetBindings`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(request),
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data: GetBindingsResponse = await response.json()

      logger.debug('AmeriaBank GetBindings response', {
        ResponseCode: data.ResponseCode,
        CardsCount: data.CardBindingFileds?.length || 0,
      })

      return data
    } catch (error) {
      logger.error('AmeriaBank GetBindings error', error)
      throw error
    }
  }

  /**
   * Deactivate binding (disable saved card)
   */
  async deactivateBinding(cardHolderID: string, paymentType: number): Promise<DeactivateBindingResponse> {
    const request: DeactivateBindingRequest = {
      ClientID: this.credentials.clientID,
      Username: this.credentials.username,
      Password: this.credentials.password,
      PaymentType: paymentType,
      CardHolderID: cardHolderID,
    }

    logger.debug('AmeriaBank DeactivateBinding request', { CardHolderID: cardHolderID })

    try {
      const response = await fetch(`${this.apiUrl}/api/VPOS/DeactivateBinding`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(request),
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data: DeactivateBindingResponse = await response.json()

      logger.debug('AmeriaBank DeactivateBinding response', {
        ResponseCode: data.ResponseCode,
        ResponseMessage: data.ResponseMessage,
      })

      return data
    } catch (error) {
      logger.error('AmeriaBank DeactivateBinding error', error)
      throw error
    }
  }

  /**
   * Activate binding (enable saved card)
   */
  async activateBinding(cardHolderID: string, paymentType: number): Promise<ActivateBindingResponse> {
    const request: ActivateBindingRequest = {
      ClientID: this.credentials.clientID,
      Username: this.credentials.username,
      Password: this.credentials.password,
      PaymentType: paymentType,
      CardHolderID: cardHolderID,
    }

    logger.debug('AmeriaBank ActivateBinding request', { CardHolderID: cardHolderID })

    try {
      const response = await fetch(`${this.apiUrl}/api/VPOS/ActivateBinding`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(request),
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data: ActivateBindingResponse = await response.json()

      logger.debug('AmeriaBank ActivateBinding response', {
        ResponseCode: data.ResponseCode,
        ResponseMessage: data.ResponseMessage,
      })

      return data
    } catch (error) {
      logger.error('AmeriaBank ActivateBinding error', error)
      throw error
    }
  }

  /**
   * Check if payment is successful
   * Based on ResponseCode and OrderStatus
   */
  isPaymentSuccessful(paymentDetails: GetPaymentDetailsResponse): boolean {
    // ResponseCode "00" means successful
    if (paymentDetails.ResponseCode === '00') {
      // OrderStatus 2 (payment_deposited) means payment is completed
      return paymentDetails.OrderStatus === 2
    }
    return false
  }

  /**
   * Check if payment is approved (preauthorized, waiting for confirmation)
   */
  isPaymentApproved(paymentDetails: GetPaymentDetailsResponse): boolean {
    return paymentDetails.OrderStatus === 1 // payment_approved
  }

  /**
   * Check if payment is declined
   */
  isPaymentDeclined(paymentDetails: GetPaymentDetailsResponse): boolean {
    return paymentDetails.OrderStatus === 6 // payment_declined
  }
}
