/**
 * Ameria Bank vPOS 3.1 API Types
 * Based on official documentation
 */

// Currency codes (ISO 4217 numeric codes)
export const CURRENCY_CODES = {
  AMD: '051',
  EUR: '978',
  USD: '840',
  RUB: '643',
} as const

export type CurrencyCode = typeof CURRENCY_CODES[keyof typeof CURRENCY_CODES]

// Payment Type Enum
export enum PaymentType {
  MainRest = 5, // Arca
  Binding = 6,
  PayPal = 7,
}

// Order Status Codes (Table 2)
export enum OrderStatus {
  PaymentStarted = 0, // Order is registered but not paid
  PaymentApproved = 1, // Amount of the order was preauthorized
  PaymentDeposited = 2, // Amount successfully authorized
  PaymentVoid = 3, // Authorization cancelled
  PaymentRefunded = 4, // Amount of the transaction was refunded
  PaymentAutoAuthorized = 5, // Authorization via ACS of the issuer bank
  PaymentDeclined = 6, // Authorization declined
}

// Payment State Values
export type PaymentState =
  | 'payment_started'
  | 'payment_approved'
  | 'payment_deposited'
  | 'payment_void'
  | 'payment_refunded'
  | 'payment_autoauthorized'
  | 'payment_declined'
  | 'Successful' // Used by some plugins (non-official)

// InitPayment Request
export interface InitPaymentRequest {
  ClientID: string
  Username: string
  Password: string
  Currency?: CurrencyCode
  Description: string
  OrderID: number // Must be integer
  Amount: number
  BackURL?: string
  Opaque?: string
  CardHolderID?: string // For binding transactions
  Timeout?: number // Max 1200 seconds, default 1200
}

// InitPayment Response
export interface InitPaymentResponse {
  PaymentID: string
  ResponseCode: number // 1 = successful
  ResponseMessage: string
}

// GetPaymentDetails Request
export interface GetPaymentDetailsRequest {
  PaymentID: string
  Username: string
  Password: string
}

// GetPaymentDetails Response
export interface GetPaymentDetailsResponse {
  Amount: number
  ApprovedAmount: number
  ApprovalCode?: string
  CardNumber?: string
  ClientName?: string
  ClientEmail?: string
  Currency: string
  DateTime: string
  DepositedAmount: number
  Description: string
  MerchantId: string
  Opaque?: string
  OrderID: number
  PaymentState: PaymentState
  PaymentType: PaymentType
  ResponseCode: string // "00" = successful
  rrn?: string
  TerminalId?: string
  TrxnDescription?: string
  OrderStatus: OrderStatus
  RefundedAmount?: number
  CardHolderID?: string
  MDOrderID?: string
  PrimaryRC?: string
  ExpDate?: string
  ProcessingIP?: string
  BindingID?: string
  ActionCode?: string
  ExchangeRate?: number
}

// ConfirmPayment Request (for two-stage payments)
export interface ConfirmPaymentRequest {
  PaymentID: string
  Username: string
  Password: string
  Amount: number
}

// ConfirmPayment Response
export interface ConfirmPaymentResponse {
  ResponseCode: string // "00" = successful
  ResponseMessage: string
  Opaque?: string
}

// CancelPayment Request
export interface CancelPaymentRequest {
  PaymentID: string
  Username: string
  Password: string
}

// CancelPayment Response
export interface CancelPaymentResponse {
  ResponseCode: string // "00" = successful
  ResponseMessage: string
  Opaque?: string
}

// RefundPayment Request
export interface RefundPaymentRequest {
  PaymentID: string
  Username: string
  Password: string
  Amount: number // Must not exceed transaction amount
}

// RefundPayment Response
export interface RefundPaymentResponse {
  ResponseCode: string // "00" = successful
  ResponseMessage: string
  Opaque?: string
}

// Callback parameters from bank (BackURL)
export interface PaymentCallbackParams {
  orderID: string // lowercase
  resposneCode: string // lowercase, typo in API!
  paymentID: string // lowercase
  opaque?: string // lowercase
  currency?: string // lowercase
}

// MakeBindingPayment Request
export interface MakeBindingPaymentRequest {
  ClientID: string
  Username: string
  Password: string
  Currency?: CurrencyCode
  Description: string
  OrderID: number
  Amount: number
  Opaque?: string
  CardHolderID: string
  BackURL: string
  PaymentType: PaymentType
}

// MakeBindingPayment Response
export interface MakeBindingPaymentResponse {
  PaymentID: string
  ResponseCode: string
  Amount: number
  ApprovedAmount: number
  ApprovalCode?: string
  CardNumber?: string
  ClientName?: string
  Currency: string
  DateTime: string
  DepositedAmount: number
  Description: string
  MDOrderID?: string
  MerchantId: string
  Opaque?: string
  OrderID: number
  PaymentState: PaymentState
  PaymentType: PaymentType
  PrimaryRC?: string
  ProcessingIP?: string
  rrn?: string
  TerminalId?: string
  TrxnDescription?: string
  OrderStatus: OrderStatus
  RefundedAmount?: number
  CardHolderID?: string
  BindingID?: string
  ActionCode?: string
  ExpDate?: string
}

// GetBindings Request
export interface GetBindingsRequest {
  ClientID: string
  Username: string
  Password: string
  PaymentType: PaymentType
}

// Binding Card Info
export interface CardBindingField {
  CardHolderID: string
  CardPan: string
  ExpDate: string
  IsAvtive: boolean
}

// GetBindings Response
export interface GetBindingsResponse {
  ResponseCode: string
  ResponseMessage: string
  CardBindingFileds: CardBindingField[]
}

// DeactivateBinding Request
export interface DeactivateBindingRequest {
  ClientID: string
  Username: string
  Password: string
  PaymentType: PaymentType
  CardHolderID: string
}

// DeactivateBinding Response
export interface DeactivateBindingResponse {
  ResponseCode: string
  ResponseMessage: string
  CardHolderID: string
}

// ActivateBinding Request
export interface ActivateBindingRequest {
  ClientID: string
  Username: string
  Password: string
  PaymentType: PaymentType
  CardHolderID: string
}

// ActivateBinding Response
export interface ActivateBindingResponse {
  ResponseCode: string
  ResponseMessage: string
  CardHolderID: string
}
