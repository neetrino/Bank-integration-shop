'use client'

import { useState, useEffect } from 'react'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import { ArrowLeft, MapPin, Clock, CreditCard, Phone, User } from 'lucide-react'
import { useCart } from '@/hooks/useCart'
import { useSession } from 'next-auth/react'
import Footer from '@/components/Footer'
import { formatPrice } from '@/utils/priceUtils'

interface UserProfile {
  id: string
  name: string | null
  email: string
  phone: string | null
  address: string | null
}

export default function CheckoutPage() {
  const router = useRouter()
  const { items, getTotalPrice, clearCart, validateCart } = useCart()
  const { data: session, status } = useSession()
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [userProfile, setUserProfile] = useState<UserProfile | null>(null)
  
  // Form state
  const [formData, setFormData] = useState({
    name: '',
    phone: '',
    address: '',
    deliveryTime: 'asap', // Дефолтное значение "Как можно скорее"
    paymentMethod: 'cash',
    notes: ''
  })

  const [errors, setErrors] = useState<Record<string, string>>({})

  // Функция для расчета стоимости доставки
  const getDeliveryPrice = () => {
    const total = getTotalPrice()
    if (total >= 10000) {
      return 0 // Бесплатная доставка от 10000 ֏
    }
    return 1000 // Стандартная стоимость доставки
  }

  // Redirect if cart is empty and validate cart
  useEffect(() => {
    if (items.length === 0) {
      console.log('Cart is empty, redirecting to cart page')
      router.push('/cart')
    } else {
      // Валидируем корзину при загрузке страницы
      validateCart()
    }
  }, [items, router, validateCart])

  // Load user profile and auto-fill form (only for authenticated users)
  useEffect(() => {
    const loadUserProfile = async () => {
      if (status === 'loading') return
      
      if (!session) {
        console.log('Guest user - no profile to load')
        return
      }

      try {
        console.log('Loading user profile for authenticated user')
        const response = await fetch('/api/user/profile')
        
        if (response.ok) {
          const profile = await response.json()
          setUserProfile(profile)
          
          // Автоматически заполняем форму данными пользователя
          setFormData(prev => ({
            ...prev,
            name: profile.name || '',
            phone: profile.phone || '',
            address: profile.address || ''
          }))
          console.log('User profile loaded and form auto-filled:', profile)
        } else if (response.status === 401) {
          console.log('User session expired, continuing as guest')
        } else {
          console.log('Profile not found, continuing as guest')
        }
      } catch (error) {
        console.log('Error loading profile, continuing as guest:', error)
      }
    }

    loadUserProfile()
  }, [session, status])

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target
    setFormData(prev => ({
      ...prev,
      [name]: value
    }))
    
    // Clear error when user starts typing
    if (errors[name]) {
      setErrors(prev => ({
        ...prev,
        [name]: ''
      }))
    }
  }

  const validateForm = () => {
    const newErrors: Record<string, string> = {}

    if (!formData.name.trim()) {
      newErrors.name = 'Имя обязательно'
    }

    if (!formData.phone.trim()) {
      newErrors.phone = 'Телефон обязателен'
    } else if (!/^\+?[0-9\s\-\(\)]{10,}$/.test(formData.phone)) {
      newErrors.phone = 'Неверный формат телефона'
    }

    if (!formData.address.trim()) {
      newErrors.address = 'Адрес обязателен'
    }

    // Время доставки уже имеет дефолтное значение, валидация не нужна

    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!validateForm()) {
      return
    }

    // Валидируем корзину перед отправкой
    await validateCart()
    
    // Проверяем, что корзина не пуста после валидации
    if (items.length === 0) {
      alert('Զամբյուղում հասանելի արտադրանք չկա: Խնդրում ենք ավելացնել արտադրանք զամբյուղում:')
      router.push('/products')
      return
    }

    setIsSubmitting(true)

    try {
      const total = getTotalPrice()
      
      // Send order to API
      const orderData = {
        ...formData,
        items: items.map(item => ({
          productId: item.product.id,
          quantity: item.quantity,
          price: item.product.salePrice || item.product.price
        })),
        total
      }

      const response = await fetch('/api/orders', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(orderData),
      })

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}))
        console.error('Order creation failed:', {
          status: response.status,
          statusText: response.statusText,
          error: errorData
        })
        throw new Error(`Failed to create order: ${errorData.error || response.statusText}`)
      }
      
      const order = await response.json()
      console.log('Order created successfully:', order.id)
      
      // Handle payment based on payment method
      if (formData.paymentMethod === 'ameriabank') {
        // Initialize Ameria Bank payment
        try {
          const paymentResponse = await fetch('/api/payments/ameriabank/init', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              orderId: order.id,
              amount: total,
              currency: 'AMD',
              description: `Order #${order.id}`,
            }),
          })

          if (!paymentResponse.ok) {
            const errorData = await paymentResponse.json().catch(() => ({}))
            throw new Error(`Failed to initialize payment: ${errorData.error || paymentResponse.statusText}`)
          }

          const paymentData = await paymentResponse.json()
          
          // Clear cart before redirecting
          clearCart()
          
          // Redirect to bank payment page
          window.location.href = paymentData.paymentUrl
          return
        } catch (paymentError) {
          console.error('Payment initialization error:', paymentError)
          alert('Ошибка при инициализации платежа. Попробуйте еще раз или выберите другой способ оплаты.')
          setIsSubmitting(false)
          return
        }
      }
      
      // For cash/card payments, just redirect to success page
      console.log('Order created successfully, redirecting to order-success page')
      
      // Clear cart first
      clearCart()
      // Force redirect to success page using window.location
      window.location.href = '/order-success'
    } catch (error) {
      console.error('Error submitting order:', error)
      alert('Произошла ошибка при оформлении заказа. Попробуйте еще раз.')
    } finally {
      setIsSubmitting(false)
    }
  }

  if (items.length === 0) {
    return null // Will redirect
  }

  return (
    <div className="min-h-screen" style={{ backgroundColor: '#ffffff' }}>
      
      {/* Отступ для fixed хедера */}
      <div className="lg:hidden h-20"></div>
      <div className="hidden lg:block h-28"></div>
      
      {/* Mobile App Style Container */}
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4 lg:py-8 pb-20 lg:pb-8">
        {/* Mobile Header */}
        <div className="lg:hidden mb-6">
          <div className="flex items-center space-x-4 mb-4">
            <Link 
              href="/cart"
              className="flex items-center text-gray-600 hover:text-orange-500 transition-colors"
            >
              <ArrowLeft className="h-6 w-6 mr-2" />
              <span className="text-lg font-medium">к корзине</span>
            </Link>
            <h1 className="text-2xl font-bold text-gray-900">оформление</h1>
          </div>
        </div>

        {/* Desktop Header */}
        <div className="hidden lg:flex items-center space-x-4 mb-8">
          <Link 
            href="/cart"
            className="flex items-center text-gray-600 hover:text-orange-500 transition-colors"
          >
            <ArrowLeft className="h-5 w-5 mr-2" />
            Назад к корзине
          </Link>
          <div className="h-8 w-px bg-gray-300"></div>
          <h1 className="text-3xl font-bold text-gray-900">Оформление заказа</h1>
        </div>

        <form onSubmit={handleSubmit}>
          {/* Mobile Layout */}
          <div className="lg:hidden space-y-6">
            {/* Mobile Order Form */}
            <div className="bg-white rounded-2xl shadow-lg p-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="text-lg font-semibold text-gray-900">Данные для доставки</h2>
                {!session && (
                  <span className="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full">
                    Гостевой заказ
                  </span>
                )}
              </div>
                
              <div className="space-y-4">
                {/* Name */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    <User className="inline h-4 w-4 mr-1" />
                    Имя *
                  </label>
                  <input
                    type="text"
                    name="name"
                    value={formData.name}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-3 border-2 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors text-gray-900 ${
                      errors.name ? 'border-red-500' : 'border-gray-300'
                    }`}
                    placeholder="Введите ваше имя"
                  />
                  {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name}</p>}
                </div>

                {/* Phone */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    <Phone className="inline h-4 w-4 mr-1" />
                    Телефон *
                  </label>
                  <input
                    type="tel"
                    name="phone"
                    value={formData.phone}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-3 border-2 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors text-gray-900 ${
                      errors.phone ? 'border-red-500' : 'border-gray-300'
                    }`}
                    placeholder="+374 99 123 456"
                  />
                  {errors.phone && <p className="text-red-500 text-sm mt-1">{errors.phone}</p>}
                </div>

                {/* Address */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    <MapPin className="inline h-4 w-4 mr-1" />
                    Адрес доставки *
                  </label>
                  <textarea
                    name="address"
                    value={formData.address}
                    onChange={handleInputChange}
                    rows={3}
                    className={`w-full px-4 py-3 border-2 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors resize-none text-gray-900 ${
                      errors.address ? 'border-red-500' : 'border-gray-300'
                    }`}
                    placeholder="Укажите полный адрес доставки"
                  />
                  {errors.address && <p className="text-red-500 text-sm mt-1">{errors.address}</p>}
                </div>

                {/* Delivery Time */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    <Clock className="inline h-4 w-4 mr-1" />
                    Время доставки *
                  </label>
                  <select
                    name="deliveryTime"
                    value={formData.deliveryTime}
                    onChange={handleInputChange}
                    className="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors text-gray-900"
                  >
                    <option value="asap">Как можно скорее (20-30 мин)</option>
                    <option value="11:00-12:00">11:00 - 12:00</option>
                    <option value="12:00-13:00">12:00 - 13:00</option>
                    <option value="13:00-14:00">13:00 - 14:00</option>
                    <option value="14:00-15:00">14:00 - 15:00</option>
                    <option value="15:00-16:00">15:00 - 16:00</option>
                    <option value="16:00-17:00">16:00 - 17:00</option>
                    <option value="17:00-18:00">17:00 - 18:00</option>
                    <option value="18:00-19:00">18:00 - 19:00</option>
                    <option value="19:00-20:00">19:00 - 20:00</option>
                    <option value="20:00-21:00">20:00 - 21:00</option>
                  </select>
                  {errors.deliveryTime && <p className="text-red-500 text-sm mt-1">{errors.deliveryTime}</p>}
                </div>
              </div>
            </div>

            {/* Mobile Payment Method */}
            <div className="bg-white rounded-2xl shadow-lg p-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Способ оплаты</h2>
              <div className="space-y-4">
                <label className={`relative p-4 border-2 rounded-xl cursor-pointer transition-all duration-300 ${
                  formData.paymentMethod === 'cash' 
                    ? 'border-orange-500 bg-orange-50' 
                    : 'border-gray-300'
                }`}>
                  <input
                    type="radio"
                    name="paymentMethod"
                    value="cash"
                    checked={formData.paymentMethod === 'cash'}
                    onChange={handleInputChange}
                    className="sr-only"
                  />
                  <div className="flex items-center space-x-3">
                    <div className="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                      <svg className="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                      </svg>
                    </div>
                    <div className="flex-1">
                      <h3 className="text-base font-semibold text-gray-900">Наличные</h3>
                      <p className="text-sm text-gray-600">Оплата курьеру наличными</p>
                    </div>
                    {formData.paymentMethod === 'cash' && (
                      <div className="w-6 h-6 bg-orange-500 rounded-full flex items-center justify-center">
                        <svg className="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                          <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                        </svg>
                      </div>
                    )}
                  </div>
                </label>
                
                <label className={`relative p-4 border-2 rounded-xl cursor-pointer transition-all duration-300 ${
                  formData.paymentMethod === 'card' 
                    ? 'border-orange-500 bg-orange-50' 
                    : 'border-gray-300'
                }`}>
                  <input
                    type="radio"
                    name="paymentMethod"
                    value="card"
                    checked={formData.paymentMethod === 'card'}
                    onChange={handleInputChange}
                    className="sr-only"
                  />
                  <div className="flex items-center space-x-3">
                    <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                      <CreditCard className="h-6 w-6 text-blue-600" />
                    </div>
                    <div className="flex-1">
                      <h3 className="text-base font-semibold text-gray-900">Карта</h3>
                      <p className="text-sm text-gray-600">Оплата картой через терминал</p>
                    </div>
                    {formData.paymentMethod === 'card' && (
                      <div className="w-6 h-6 bg-orange-500 rounded-full flex items-center justify-center">
                        <svg className="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                          <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                        </svg>
                      </div>
                    )}
                  </div>
                </label>

                <label className={`relative p-4 border-2 rounded-xl cursor-pointer transition-all duration-300 ${
                  formData.paymentMethod === 'ameriabank' 
                    ? 'border-orange-500 bg-orange-50' 
                    : 'border-gray-300'
                }`}>
                  <input
                    type="radio"
                    name="paymentMethod"
                    value="ameriabank"
                    checked={formData.paymentMethod === 'ameriabank'}
                    onChange={handleInputChange}
                    className="sr-only"
                  />
                  <div className="flex items-center space-x-3">
                    <div className="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                      <CreditCard className="h-6 w-6 text-purple-600" />
                    </div>
                    <div className="flex-1">
                      <h3 className="text-base font-semibold text-gray-900">Онлайн оплата</h3>
                      <p className="text-sm text-gray-600">Оплата картой через Ameria Bank</p>
                    </div>
                    {formData.paymentMethod === 'ameriabank' && (
                      <div className="w-6 h-6 bg-orange-500 rounded-full flex items-center justify-center">
                        <svg className="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                          <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                        </svg>
                      </div>
                    )}
                  </div>
                </label>
              </div>
            </div>

            {/* Mobile Notes */}
            <div className="bg-white rounded-2xl shadow-lg p-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Комментарий</h2>
              <textarea
                name="notes"
                value={formData.notes}
                onChange={handleInputChange}
                rows={3}
                className="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors resize-none text-gray-900"
                placeholder="Дополнительные пожелания к заказу..."
              />
            </div>

            {/* Mobile Order Summary */}
            <div className="bg-white rounded-2xl shadow-lg p-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Ваш заказ</h2>
              
              <div className="space-y-3 mb-6">
                {items.map((item) => (
                  <div key={item.product.id} className="flex justify-between items-center py-2">
                    <div className="flex-1">
                      <div className="font-medium text-gray-900 text-sm">{item.product.name}</div>
                      <div className="text-xs text-gray-600">{item.quantity} шт.</div>
                    </div>
                    <div className="font-semibold text-gray-900 text-sm">
                      {formatPrice(item.product.price * item.quantity)} ֏
                    </div>
                  </div>
                ))}
                
                <div className="border-t border-gray-300 pt-3">
                  <div className="space-y-2">
                    <div className="flex justify-between text-gray-600">
                      <span>Товары</span>
                      <span>{formatPrice(getTotalPrice())} ֏</span>
                    </div>
                    <div className="flex justify-between text-gray-600">
                      <span>Доставка</span>
                      <span>{getDeliveryPrice()} ֏</span>
                    </div>
                    <div className="flex justify-between text-lg font-bold text-gray-900 border-t border-gray-300 pt-2">
                      <span>Итого</span>
                      <span>{formatPrice(getTotalPrice())} ֏</span>
                    </div>
                  </div>
                </div>
              </div>
              
              <button
                type="submit"
                disabled={isSubmitting}
                className="w-full bg-orange-500 text-white py-4 rounded-xl font-semibold hover:bg-orange-600 transition-colors text-center text-lg disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {isSubmitting ? 'Оформляем заказ...' : 'Подтвердить заказ'}
              </button>
              
              <p className="text-xs text-gray-500 mt-4 text-center">
                Нажимая "Подтвердить заказ", вы соглашаетесь с условиями доставки
              </p>
              {!session && (
                <p className="text-xs text-blue-600 mt-2 text-center">
                  💡 После заказа вы сможете создать аккаунт для быстрого оформления в будущем
                </p>
              )}
            </div>
          </div>

          {/* Desktop Layout */}
          <div className="hidden lg:grid grid-cols-1 xl:grid-cols-3 gap-8">
            {/* Order Form */}
            <div className="lg:col-span-2">
              <div className="bg-white rounded-2xl shadow-lg p-8">
                <div className="flex items-center justify-between mb-6">
                  <h2 className="text-2xl font-semibold text-gray-900">Данные для доставки</h2>
                  {!session && (
                    <span className="text-sm bg-blue-100 text-blue-700 px-3 py-1 rounded-full">
                      Гостевой заказ
                    </span>
                  )}
                </div>
                
                <div className="space-y-6">
                  {/* Name */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      <User className="inline h-4 w-4 mr-1" />
                      Имя *
                    </label>
                    <input
                      type="text"
                      name="name"
                      value={formData.name}
                      onChange={handleInputChange}
                      className={`w-full px-4 py-3 border-2 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors text-gray-900 ${
                        errors.name ? 'border-red-500' : 'border-gray-300'
                      }`}
                      placeholder="Введите ваше имя"
                    />
                    {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name}</p>}
                  </div>

                  {/* Phone */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      <Phone className="inline h-4 w-4 mr-1" />
                      Телефон *
                    </label>
                    <input
                      type="tel"
                      name="phone"
                      value={formData.phone}
                      onChange={handleInputChange}
                      className={`w-full px-4 py-3 border-2 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors text-gray-900 ${
                        errors.phone ? 'border-red-500' : 'border-gray-300'
                      }`}
                      placeholder="+374 99 123 456"
                    />
                    {errors.phone && <p className="text-red-500 text-sm mt-1">{errors.phone}</p>}
                  </div>

                  {/* Address */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      <MapPin className="inline h-4 w-4 mr-1" />
                      Адрес доставки *
                    </label>
                    <textarea
                      name="address"
                      value={formData.address}
                      onChange={handleInputChange}
                      rows={3}
                      className={`w-full px-4 py-3 border-2 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors resize-none text-gray-900 ${
                        errors.address ? 'border-red-500' : 'border-gray-300'
                      }`}
                      placeholder="Укажите полный адрес доставки"
                    />
                    {errors.address && <p className="text-red-500 text-sm mt-1">{errors.address}</p>}
                  </div>

                  {/* Delivery Time */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      <Clock className="inline h-4 w-4 mr-1" />
                      Время доставки *
                    </label>
                    <select
                      name="deliveryTime"
                      value={formData.deliveryTime}
                      onChange={handleInputChange}
                      className="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors text-gray-900"
                    >
                      <option value="asap">Как можно скорее (20-30 мин)</option>
                      <option value="11:00-12:00">11:00 - 12:00</option>
                      <option value="12:00-13:00">12:00 - 13:00</option>
                      <option value="13:00-14:00">13:00 - 14:00</option>
                      <option value="14:00-15:00">14:00 - 15:00</option>
                      <option value="15:00-16:00">15:00 - 16:00</option>
                      <option value="16:00-17:00">16:00 - 17:00</option>
                      <option value="17:00-18:00">17:00 - 18:00</option>
                      <option value="18:00-19:00">18:00 - 19:00</option>
                      <option value="19:00-20:00">19:00 - 20:00</option>
                      <option value="20:00-21:00">20:00 - 21:00</option>
                    </select>
                    {errors.deliveryTime && <p className="text-red-500 text-sm mt-1">{errors.deliveryTime}</p>}
                  </div>

                  {/* Payment Method */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-4">
                      <CreditCard className="inline h-4 w-4 mr-1" />
                      Способ оплаты *
                    </label>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <label className={`relative p-6 border-2 rounded-xl cursor-pointer transition-all duration-300 hover:shadow-lg ${
                        formData.paymentMethod === 'cash' 
                          ? 'border-orange-500 bg-orange-50 shadow-md' 
                          : 'border-gray-300 hover:border-orange-300'
                      }`}>
                        <input
                          type="radio"
                          name="paymentMethod"
                          value="cash"
                          checked={formData.paymentMethod === 'cash'}
                          onChange={handleInputChange}
                          className="sr-only"
                        />
                        <div className="text-center">
                          <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg className="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                          </div>
                          <h3 className="text-lg font-semibold text-gray-900 mb-2">Наличные</h3>
                          <p className="text-sm text-gray-600">Оплата курьеру наличными при доставке</p>
                          {formData.paymentMethod === 'cash' && (
                            <div className="absolute top-4 right-4">
                              <div className="w-6 h-6 bg-orange-500 rounded-full flex items-center justify-center">
                                <svg className="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                  <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                </svg>
                              </div>
                            </div>
                          )}
                        </div>
                      </label>
                      
                      <label className={`relative p-6 border-2 rounded-xl cursor-pointer transition-all duration-300 hover:shadow-lg ${
                        formData.paymentMethod === 'card' 
                          ? 'border-orange-500 bg-orange-50 shadow-md' 
                          : 'border-gray-300 hover:border-orange-300'
                      }`}>
                        <input
                          type="radio"
                          name="paymentMethod"
                          value="card"
                          checked={formData.paymentMethod === 'card'}
                          onChange={handleInputChange}
                          className="sr-only"
                        />
                        <div className="text-center">
                          <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <CreditCard className="h-8 w-8 text-blue-600" />
                          </div>
                          <h3 className="text-lg font-semibold text-gray-900 mb-2">Карта</h3>
                          <p className="text-sm text-gray-600">Оплата картой через терминал курьера</p>
                          {formData.paymentMethod === 'card' && (
                            <div className="absolute top-4 right-4">
                              <div className="w-6 h-6 bg-orange-500 rounded-full flex items-center justify-center">
                                <svg className="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                  <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                </svg>
                              </div>
                            </div>
                          )}
                        </div>
                      </label>
                    </div>
                  </div>

                  {/* Notes */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Комментарий к заказу
                    </label>
                    <textarea
                      name="notes"
                      value={formData.notes}
                      onChange={handleInputChange}
                      rows={3}
                      className="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors resize-none text-gray-900"
                      placeholder="Дополнительные пожелания к заказу..."
                    />
                  </div>
                </div>
              </div>
            </div>
            
            {/* Order Summary */}
            <div className="lg:col-span-1">
              <div className="bg-white rounded-2xl shadow-lg p-6 sticky top-8">
                <h2 className="text-xl font-semibold text-gray-900 mb-6">Ваш заказ</h2>
                
                <div className="space-y-4 mb-6">
                  {items.map((item) => (
                    <div key={item.product.id} className="flex justify-between items-center py-2">
                      <div className="flex-1">
                        <div className="font-medium text-gray-900">{item.product.name}</div>
                        <div className="text-sm text-gray-600">{item.quantity} шт.</div>
                      </div>
                      <div className="font-semibold text-gray-900">
                        {formatPrice(item.product.price * item.quantity)} ֏
                      </div>
                    </div>
                  ))}
                  
                  <div className="border-t border-gray-300 pt-4">
                    <div className="space-y-2">
                      <div className="flex justify-between text-gray-600">
                        <span>Товары</span>
                        <span>{formatPrice(getTotalPrice())} ֏</span>
                      </div>
                      <div className="flex justify-between text-gray-600">
                        <span>Доставка</span>
                        <span>{getDeliveryPrice()} ֏</span>
                      </div>
                      <div className="flex justify-between text-lg font-bold text-gray-900 border-t border-gray-300 pt-2">
                        <span>Итого</span>
                        <span>{formatPrice(getTotalPrice())} ֏</span>
                      </div>
                    </div>
                  </div>
                </div>
                
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="w-full bg-orange-500 text-white py-4 rounded-xl font-semibold hover:bg-orange-600 transition-colors text-center text-lg disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isSubmitting ? 'Оформляем заказ...' : 'Подтвердить заказ'}
                </button>
                
                <p className="text-xs text-gray-500 mt-4 text-center">
                  Нажимая "Подтвердить заказ", вы соглашаетесь с условиями доставки
                </p>
                {!session && (
                  <p className="text-xs text-blue-600 mt-2 text-center">
                    💡 После заказа вы сможете создать аккаунт для быстрого оформления в будущем
                  </p>
                )}
              </div>
            </div>
          </div>
        </form>
      </div>
      
      {/* Hide Footer on Mobile and Tablet */}
      <div className="hidden lg:block">
        <Footer />
      </div>
    </div>
  )
}
