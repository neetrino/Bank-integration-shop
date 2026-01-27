'use client'

import { useEffect, useState } from 'react'
import { useSearchParams } from 'next/navigation'
import Link from 'next/link'
import { CheckCircle, Clock, Phone, ArrowRight, XCircle, AlertCircle } from 'lucide-react'
import Footer from '@/components/Footer'
import { useCart } from '@/hooks/useCart'

export default function OrderSuccessPage() {
  const searchParams = useSearchParams()
  const { clearCart } = useCart()
  const [mounted, setMounted] = useState(false)
  
  useEffect(() => {
    setMounted(true)
  }, [])
  
  // Clear cart only after successful payment (when clearCart=true and no error)
  useEffect(() => {
    if (mounted) {
      const error = searchParams.get('error')
      const clearCartParam = searchParams.get('clearCart')
      
      // Clear cart only if payment was successful (no error and clearCart=true)
      if (!error && clearCartParam === 'true') {
        clearCart()
      }
    }
  }, [mounted, searchParams, clearCart])
  
  if (!mounted) {
    return null
  }
  
  const error = searchParams.get('error')
  const orderId = searchParams.get('orderId')
  const paymentId = searchParams.get('paymentId')
  const message = searchParams.get('message')
  const responseCode = searchParams.get('responseCode')
  
  const hasError = !!error
  const errorMessage = message ? decodeURIComponent(message) : 'Произошла ошибка при обработке платежа'
  
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
              <Link 
                href="/products"
                className="inline-flex items-center bg-orange-500 text-white px-8 py-4 rounded-xl font-semibold hover:bg-orange-600 transition-colors text-lg"
              >
                Заказать еще
                <ArrowRight className="h-5 w-5 ml-2" />
              </Link>
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
