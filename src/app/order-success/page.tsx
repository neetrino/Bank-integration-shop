'use client'

import { useEffect, useState } from 'react'
import { useSearchParams } from 'next/navigation'
import Link from 'next/link'
import { CheckCircle, Clock, Phone, ArrowRight, XCircle, AlertCircle, Package, Truck } from 'lucide-react'
import Footer from '@/components/Footer'
import { useCart } from '@/hooks/useCart'
import { useSession } from 'next-auth/react'

interface Order {
  id: string
  status: string
  total: number
  createdAt: string
}

export default function OrderSuccessPage() {
  const searchParams = useSearchParams()
  const { clearCart } = useCart()
  const { data: session } = useSession()
  const [mounted, setMounted] = useState(false)
  const [order, setOrder] = useState<Order | null>(null)
  const [loadingOrder, setLoadingOrder] = useState(false)
  const [cartCleared, setCartCleared] = useState(false)
  
  useEffect(() => {
    setMounted(true)
  }, [])
  
  // Load order details if orderId is provided
  useEffect(() => {
    const orderId = searchParams.get('orderId')
    if (mounted && orderId && session) {
      loadOrder(orderId)
    }
  }, [mounted, searchParams, session])
  
  const loadOrder = async (orderId: string) => {
    try {
      setLoadingOrder(true)
      const response = await fetch('/api/orders')
      if (response.ok) {
        const orders = await response.json()
        const foundOrder = orders.find((o: Order) => o.id === orderId)
        if (foundOrder) {
          setOrder(foundOrder)
        }
      }
    } catch (error) {
      console.error('Error loading order:', error)
    } finally {
      setLoadingOrder(false)
    }
  }
  
  // Clear cart only after successful payment (when clearCart=true and no error)
  // Use cartCleared flag to prevent infinite loop
  useEffect(() => {
    if (mounted && !cartCleared) {
      const error = searchParams.get('error')
      const clearCartParam = searchParams.get('clearCart')
      
      // Clear cart only if payment was successful (no error and clearCart=true)
      if (!error && clearCartParam === 'true') {
        clearCart()
        setCartCleared(true) // Mark as cleared to prevent re-execution
      }
    }
  }, [mounted, searchParams, cartCleared]) // Remove clearCart from dependencies
  
  if (!mounted) {
    return null
  }
  
  const error = searchParams.get('error')
  // orderId from URL; Idram redirects with EDP_BILL_NO
  const orderId = searchParams.get('orderId') || searchParams.get('EDP_BILL_NO')
  const paymentId = searchParams.get('paymentId')
  const message = searchParams.get('message')
  const responseCode = searchParams.get('responseCode')
  
  const hasError = !!error
  const errorMessage = message ? decodeURIComponent(message) : 'Произошла ошибка при обработке платежа'
  
  // Get status info (same as in profile page)
  const getStatusInfo = (status: string) => {
    switch (status) {
      case 'PENDING':
        return { text: 'Սպասում է հաստատման', color: 'text-yellow-600', bg: 'bg-yellow-100' }
      case 'CONFIRMED':
        return { text: 'Հաստատված', color: 'text-blue-600', bg: 'bg-blue-100' }
      case 'PREPARING':
        return { text: 'Պատրաստվում է', color: 'text-orange-600', bg: 'bg-orange-100' }
      case 'READY':
        return { text: 'Պատրաստ է հանձնման', color: 'text-purple-600', bg: 'bg-purple-100' }
      case 'DELIVERED':
        return { text: 'Առաքված', color: 'text-green-600', bg: 'bg-green-100' }
      case 'CANCELLED':
        return { text: 'Չեղարկված', color: 'text-red-600', bg: 'bg-red-100' }
      default:
        return { text: status, color: 'text-gray-600', bg: 'bg-gray-100' }
    }
  }
  
  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'PENDING':
        return <Clock className="h-5 w-5" />
      case 'CONFIRMED':
      case 'PREPARING':
      case 'READY':
        return <Package className="h-5 w-5" />
      case 'DELIVERED':
        return <Truck className="h-5 w-5" />
      case 'CANCELLED':
        return <XCircle className="h-5 w-5" />
      default:
        return <Clock className="h-5 w-5" />
    }
  }
  
  return (
    <div className="min-h-screen" style={{ backgroundColor: '#ffffff' }}>
      
      {/* Отступ для fixed хедера */}
      <div className="h-20 lg:h-28"></div>
      
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div className="text-center">
          {/* Icon - Success or Error */}
          {hasError ? (
            <div className="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-8">
              <XCircle className="h-12 w-12 text-red-500" />
            </div>
          ) : (
            <div className="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-8">
              <CheckCircle className="h-12 w-12 text-green-500" />
            </div>
          )}
          
          {/* Title and Message */}
          {hasError ? (
            <>
              <h1 className="text-4xl font-bold text-gray-900 mb-4">
                Ошибка при оплате
              </h1>
              <div className="bg-red-50 border-2 border-red-200 rounded-xl p-6 mb-8 max-w-2xl mx-auto">
                <div className="flex items-start space-x-3">
                  <AlertCircle className="h-6 w-6 text-red-500 flex-shrink-0 mt-0.5" />
                  <div className="text-left">
                    <p className="text-lg font-semibold text-red-900 mb-2">
                      {errorMessage}
                    </p>
                    {responseCode && (
                      <p className="text-sm text-red-700">
                        Код ошибки: <span className="font-mono">{responseCode}</span>
                      </p>
                    )}
                    {orderId && (
                      <p className="text-sm text-red-700 mt-2">
                        Номер заказа: <span className="font-mono">{orderId}</span>
                      </p>
                    )}
                  </div>
                </div>
              </div>
              
              <div className="bg-white rounded-2xl shadow-lg p-8 mb-8 max-w-2xl mx-auto">
                <h2 className="text-2xl font-semibold text-gray-900 mb-4">Что делать дальше?</h2>
                <div className="space-y-4 text-left">
                  <p className="text-gray-700">
                    {orderId ? (
                      <>Ваш заказ создан, но оплата не прошла. Вы можете:</>
                    ) : (
                      <>Оплата не прошла. Заказ не был создан. Вы можете:</>
                    )}
                  </p>
                  <ul className="list-disc list-inside space-y-2 text-gray-600 ml-4">
                    <li>Попробовать оформить заказ снова, выбрав способ оплаты "Онлайн оплата"</li>
                    <li>Выбрать другой способ оплаты (наличные или карта курьеру)</li>
                    <li>Связаться с нами для помощи</li>
                  </ul>
                </div>
              </div>
            </>
          ) : (
            <>
              <h1 className="text-4xl font-bold text-gray-900 mb-4">
                Заказ успешно оформлен!
              </h1>
              <p className="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
                Спасибо за ваш заказ! Мы получили вашу заявку и скоро свяжемся с вами для подтверждения.
              </p>
              
              {/* Order Status Info */}
              {order && (
                <div className="bg-white rounded-2xl shadow-lg p-6 mb-8 max-w-2xl mx-auto">
                  <div className="flex items-center justify-between mb-4">
                    <div>
                      <p className="text-sm text-gray-600 mb-1">Номер заказа</p>
                      <p className="text-lg font-semibold text-gray-900">#{order.id.slice(-8)}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-sm text-gray-600 mb-1">Статус заказа</p>
                      {(() => {
                        const statusInfo = getStatusInfo(order.status)
                        return (
                          <span className={`inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium ${statusInfo.bg} ${statusInfo.color}`}>
                            {getStatusIcon(order.status)}
                            <span className="ml-2">{statusInfo.text}</span>
                          </span>
                        )
                      })()}
                    </div>
                  </div>
                  <div className="pt-4 border-t border-gray-200">
                    <p className="text-sm text-gray-600 mb-1">Сумма заказа</p>
                    <p className="text-2xl font-bold text-orange-600">{order.total.toLocaleString()} ֏</p>
                  </div>
                  {session && (
                    <div className="mt-4 pt-4 border-t border-gray-200">
                      <Link 
                        href="/profile"
                        className="inline-flex items-center text-orange-600 hover:text-orange-700 font-medium text-sm"
                      >
                        Посмотреть все заказы в профиле
                        <ArrowRight className="h-4 w-4 ml-1" />
                      </Link>
                    </div>
                  )}
                </div>
              )}
              
              {/* Order ID if no order loaded but orderId in URL */}
              {!order && orderId && (
                <div className="bg-white rounded-2xl shadow-lg p-6 mb-8 max-w-2xl mx-auto">
                  <div className="text-center">
                    <p className="text-sm text-gray-600 mb-2">Номер заказа</p>
                    <p className="text-xl font-semibold text-gray-900">#{orderId.slice(-8)}</p>
                    {session && (
                      <Link 
                        href="/profile"
                        className="inline-flex items-center text-orange-600 hover:text-orange-700 font-medium text-sm mt-4"
                      >
                        Посмотреть заказ в профиле
                        <ArrowRight className="h-4 w-4 ml-1" />
                      </Link>
                    )}
                  </div>
                </div>
              )}
            </>
          )}
          
          {/* Order Info - только для успешных заказов */}
          {!hasError && (
            <div className="bg-white rounded-2xl shadow-lg p-8 mb-8 max-w-2xl mx-auto">
              <h2 className="text-2xl font-semibold text-gray-900 mb-6">Что дальше?</h2>
              
              <div className="space-y-6">
                <div className="flex items-start space-x-4">
                  <div className="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <Clock className="h-4 w-4 text-orange-500" />
                  </div>
                  <div className="text-left">
                    <h3 className="font-semibold text-gray-900 mb-1">Подтверждение заказа</h3>
                    <p className="text-gray-600">Мы позвоним вам в течение 5 минут для подтверждения деталей заказа</p>
                  </div>
                </div>
                
                <div className="flex items-start space-x-4">
                  <div className="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <CheckCircle className="h-4 w-4 text-orange-500" />
                  </div>
                  <div className="text-left">
                    <h3 className="font-semibold text-gray-900 mb-1">Приготовление</h3>
                    <p className="text-gray-600">Ваш заказ будет готов через 15-20 минут</p>
                  </div>
                </div>
                
                <div className="flex items-start space-x-4">
                  <div className="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <Phone className="h-4 w-4 text-orange-500" />
                  </div>
                  <div className="text-left">
                    <h3 className="font-semibold text-gray-900 mb-1">Доставка</h3>
                    <p className="text-gray-600">Курьер доставит заказ по указанному адресу в выбранное время</p>
                  </div>
                </div>
              </div>
            </div>
          )}
          
          {/* Contact Info */}
          <div className="bg-orange-50 rounded-2xl p-6 mb-8 max-w-2xl mx-auto">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Нужна помощь?</h3>
            <p className="text-gray-600 mb-4">
              Если у вас есть вопросы по заказу, звоните нам:
            </p>
            <a 
              href="tel:+37495044888"
              className="inline-flex items-center text-orange-600 hover:text-orange-700 font-semibold text-lg"
            >
              <Phone className="h-5 w-5 mr-2" />
              +374 95-044-888
            </a>
          </div>
          
          {/* Action Buttons */}
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            {hasError ? (
              <Link 
                href="/checkout"
                className="inline-flex items-center bg-orange-500 text-white px-8 py-4 rounded-xl font-semibold hover:bg-orange-600 transition-colors text-lg"
              >
                Попробовать снова
                <ArrowRight className="h-5 w-5 ml-2" />
              </Link>
            ) : (
              <>
                <Link 
                  href="/products"
                  className="inline-flex items-center bg-orange-500 text-white px-8 py-4 rounded-xl font-semibold hover:bg-orange-600 transition-colors text-lg"
                >
                  Заказать еще
                  <ArrowRight className="h-5 w-5 ml-2" />
                </Link>
                {session && (
                  <Link 
                    href="/profile"
                    className="inline-flex items-center border-2 border-orange-500 text-orange-500 px-8 py-4 rounded-xl font-semibold hover:bg-orange-500 hover:text-white transition-colors text-lg"
                  >
                    Мои заказы
                  </Link>
                )}
              </>
            )}
            
            <Link 
              href="/"
              className="inline-flex items-center border-2 border-orange-500 text-orange-500 px-8 py-4 rounded-xl font-semibold hover:bg-orange-500 hover:text-white transition-colors text-lg"
            >
              На главную
            </Link>
          </div>
        </div>
      </div>
      
      {/* Hide Footer on Mobile and Tablet */}
      <div className="hidden lg:block">
        <Footer />
      </div>
    </div>
  )
}
