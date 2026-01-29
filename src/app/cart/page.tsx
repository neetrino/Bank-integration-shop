'use client'

import { useState } from 'react'
import Link from 'next/link'
import { ArrowLeft, ShoppingCart, Minus, Plus, Trash2, ShoppingBag } from 'lucide-react'
import { useCart } from '@/hooks/useCart'
import Footer from '@/components/Footer'
import { formatPrice } from '@/utils/priceUtils'

export default function CartPage() {
  const { items, updateQuantity, removeItem, getTotalPrice, clearCart } = useCart()
  const [isClearing, setIsClearing] = useState(false)

  const handleQuantityChange = (productId: string, newQuantity: number) => {
    if (newQuantity <= 0) {
      removeItem(productId)
    } else {
      updateQuantity(productId, newQuantity)
    }
  }

  const handleClearCart = () => {
    setIsClearing(true)
    setTimeout(() => {
      clearCart()
      setIsClearing(false)
    }, 500)
  }

  if (items.length === 0) {
    return (
      <div className="min-h-screen relative" style={{ backgroundColor: '#ffffff' }}>
        
        {/* Отступ для fixed хедера */}
        <div className="h-20 lg:h-28"></div>
        
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
          <div className="text-center">
            <div className="w-32 h-32 rounded-full flex items-center justify-center mx-auto mb-8" style={{ backgroundColor: '#f3d98c' }}>
              <ShoppingBag className="h-16 w-16 text-white" />
            </div>
            <h1 className="text-3xl font-bold text-gray-900 mb-4">Զամբյուղը դատարկ է</h1>
            <p className="text-lg text-gray-600 mb-8">
              Ավելացրեք արտադրանք մեր մենյուից, որպեսզի պատվիրեք
            </p>
            <Link 
              href="/products"
              className="inline-flex items-center text-white px-8 py-4 rounded-xl font-semibold transition-colors text-lg"
              style={{ backgroundColor: '#f3d98c' }}
            >
              <ArrowLeft className="h-5 w-5 mr-2" />
              Գնալ մենյու
            </Link>
          </div>
        </div>
        
        {/* Hide Footer on Mobile and Tablet */}
        <div className="hidden lg:block">
          <Footer />
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen relative" style={{ backgroundColor: '#002c45' }}>
      
      {/* Отступ для fixed хедера */}
      <div className="h-20 lg:h-28"></div>
      
      {/* Mobile App Style Container */}
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4 lg:py-8 pb-20 lg:pb-8">
        {/* Mobile Header */}
        <div className="lg:hidden mb-6">
          <div className="flex items-center justify-between mb-4">
            <Link 
              href="/products"
              className="flex items-center text-gray-300 transition-colors"
              style={{ '--hover-color': '#f3d98c' } as React.CSSProperties}
              onMouseEnter={(e) => e.currentTarget.style.color = '#f3d98c'}
              onMouseLeave={(e) => e.currentTarget.style.color = '#f3d98c'}
            >
              <ArrowLeft className="h-6 w-6 mr-2" />
              <span className="text-lg font-medium">մենյու</span>
            </Link>
            <h1 className="text-2xl font-bold text-white">զամբյուղ</h1>
            <button
              onClick={handleClearCart}
              disabled={isClearing}
              className="flex items-center text-red-500 hover:text-red-600 transition-colors disabled:opacity-50"
            >
              <Trash2 className="h-5 w-5" />
            </button>
          </div>
        </div>

        {/* Desktop Header */}
        <div className="hidden lg:flex items-center justify-between mb-8">
          <div className="flex items-center space-x-4">
            <Link 
              href="/products"
              className="flex items-center text-gray-300 transition-colors"
              style={{ '--hover-color': '#f3d98c' } as React.CSSProperties}
              onMouseEnter={(e) => e.currentTarget.style.color = '#f3d98c'}
              onMouseLeave={(e) => e.currentTarget.style.color = '#f3d98c'}
            >
              <ArrowLeft className="h-5 w-5 mr-2" />
              Վերադառնալ մենյու
            </Link>
            <div className="h-8 w-px bg-gray-400"></div>
            <h1 className="text-3xl font-bold text-white">Զամբյուղ</h1>
          </div>
          
          <button
            onClick={handleClearCart}
            disabled={isClearing}
            className="flex items-center text-red-500 hover:text-red-600 transition-colors disabled:opacity-50"
          >
            <Trash2 className="h-5 w-5 mr-2" />
            {isClearing ? 'Մաքրում...' : 'Մաքրել զամբյուղը'}
          </button>
        </div>

        {/* Mobile Layout */}
        <div className="lg:hidden">
          {/* Mobile Cart Items */}
          <div className="mb-6">
            <h2 className="text-lg font-semibold text-white mb-4 px-2">
              Արտադրանք զամբյուղում ({items.length})
            </h2>
            
            <div className="space-y-4">
              {items.map((item) => (
                <div key={item.product.id} className="bg-white rounded-2xl shadow-lg p-4">
                  <div className="flex items-start space-x-4">
                    {/* Product Image */}
                    <div className="w-16 h-16 rounded-xl flex items-center justify-center overflow-hidden flex-shrink-0" style={{ backgroundColor: '#f3d98c' }}>
                      {item.product.image && item.product.image !== 'no-image' ? (
                        <img 
                          src={item.product.image} 
                          alt={item.product.name}
                          className="w-full h-full object-contain"
                          onError={(e) => {
                            e.currentTarget.style.display = 'none';
                            const nextElement = e.currentTarget.nextElementSibling as HTMLElement;
                            if (nextElement) {
                              nextElement.style.display = 'flex';
                            }
                          }}
                        />
                      ) : null}
                      <div 
                        className="w-full h-full flex items-center justify-center text-2xl"
                        style={{ display: (item.product.image && item.product.image !== 'no-image') ? 'none' : 'flex' }}
                      >
                        🥟
                      </div>
                    </div>
                    
                    {/* Product Info */}
                    <div className="flex-1 min-w-0">
                      <h3 className="text-base font-semibold text-gray-900 mb-1 leading-tight">
                        {item.product.name}
                      </h3>
                      <p className="text-sm text-gray-600 mb-2">
                        {item.product.categoryId || 'Առանց կատեգորիայի'}
                      </p>
                      <div className="text-lg font-bold mb-3" style={{ color: '#f3d98c' }}>
                        {formatPrice(item.product.price)} ֏
                      </div>
                      
                      {/* Quantity Controls - Mobile Style */}
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-3">
                          <button
                            onClick={() => handleQuantityChange(item.product.id, item.quantity - 1)}
                            className="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors"
                          >
                            <Minus className="h-4 w-4 text-gray-700" />
                          </button>
                          
                          <span className="w-8 text-center font-semibold text-base text-gray-900">
                            {item.quantity}
                          </span>
                          
                          <button
                            onClick={() => handleQuantityChange(item.product.id, item.quantity + 1)}
                            className="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors"
                          >
                            <Plus className="h-4 w-4 text-gray-700" />
                          </button>
                        </div>
                        
                        {/* Total Price and Delete */}
                        <div className="text-right">
                          <div className="text-lg font-bold text-gray-900 mb-1">
                            {formatPrice(item.product.price * item.quantity)} ֏
                          </div>
                          <button
                            onClick={() => removeItem(item.product.id)}
                            className="text-red-500 hover:text-red-600 text-sm"
                          >
                            Ջնջել
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Desktop Layout */}
        <div className="hidden lg:grid grid-cols-1 xl:grid-cols-3 gap-8">
          {/* Cart Items */}
          <div className="lg:col-span-2">
            <div className="bg-white rounded-2xl shadow-lg overflow-hidden">
              <div className="p-6 border-b border-gray-300">
                <h2 className="text-xl font-semibold text-white">
                  Արտադրանք զամբյուղում ({items.length})
                </h2>
              </div>
              
              <div className="divide-y divide-gray-200">
                {items.map((item) => (
                  <div key={item.product.id} className="p-6">
                    <div className="flex items-center space-x-4">
                      {/* Product Image */}
                      <div className="w-20 h-20 rounded-xl flex items-center justify-center overflow-hidden flex-shrink-0" style={{ backgroundColor: '#f3d98c' }}>
                        {item.product.image && item.product.image !== 'no-image' ? (
                          <img 
                            src={item.product.image} 
                            alt={item.product.name}
                            className="w-full h-full object-contain"
                            onError={(e) => {
                              e.currentTarget.style.display = 'none';
                              const nextElement = e.currentTarget.nextElementSibling as HTMLElement;
                              if (nextElement) {
                                nextElement.style.display = 'flex';
                              }
                            }}
                          />
                        ) : null}
                        <div 
                          className="w-full h-full flex items-center justify-center text-3xl"
                          style={{ display: (item.product.image && item.product.image !== 'no-image') ? 'none' : 'flex' }}
                        >
                          🥟
                        </div>
                      </div>
                      
                      {/* Product Info */}
                      <div className="flex-1 min-w-0">
                        <h3 className="text-lg font-semibold text-gray-900 mb-1">
                          {item.product.name}
                        </h3>
                        <p className="text-sm text-gray-600 mb-2">
                          {item.product.categoryId || 'Առանց կատեգորիայի'}
                        </p>
                        <div className="text-xl font-bold" style={{ color: '#f3d98c' }}>
                          {item.product.salePrice ? (
                            <div className="flex flex-col">
                              <div className="flex items-center gap-2">
                                <span className="text-green-600 font-bold">{formatPrice(item.product.salePrice)} ֏</span>
                                <span className="bg-green-500 text-white text-xs px-2 py-1 rounded-full font-bold">
                                  ԶԵՂՉ
                                </span>
                              </div>
                              <span className="text-sm text-gray-400 line-through">{formatPrice(item.product.price)} ֏</span>
                            </div>
                          ) : (
                            <span>{formatPrice(item.product.price)} ֏</span>
                          )}
                        </div>
                      </div>
                      
                      {/* Quantity Controls */}
                      <div className="flex items-center space-x-3">
                        <button
                          onClick={() => handleQuantityChange(item.product.id, item.quantity - 1)}
                          className="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors"
                        >
                          <Minus className="h-4 w-4 text-gray-700" />
                        </button>
                        
                        <span className="w-12 text-center font-semibold text-lg text-gray-900">
                          {item.quantity}
                        </span>
                        
                        <button
                          onClick={() => handleQuantityChange(item.product.id, item.quantity + 1)}
                          className="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors"
                        >
                          <Plus className="h-4 w-4 text-gray-700" />
                        </button>
                      </div>
                      
                      {/* Total Price */}
                      <div className="text-right">
                        <div className="text-xl font-bold text-gray-900">
                          {(() => {
                            const price = item.product.salePrice || item.product.price
                            return formatPrice(price * item.quantity)
                          })()} ֏
                        </div>
                        <button
                          onClick={() => removeItem(item.product.id)}
                          className="text-red-500 hover:text-red-600 text-sm mt-1"
                        >
                          Удалить
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
          
          {/* Order Summary */}
          <div className="lg:col-span-1">
            <div className="bg-white rounded-2xl shadow-lg p-6 sticky top-8">
              <h2 className="text-xl font-semibold text-white mb-6">Ընդամենը</h2>
              
              <div className="space-y-4 mb-6">
                <div className="flex justify-between text-gray-600">
                  <span>Արտադրանք ({items.reduce((total, item) => total + item.quantity, 0)} հատ)</span>
                  <span>{formatPrice(getTotalPrice())} ֏</span>
                </div>
                <div className="border-t border-gray-300 pt-4">
                  <div className="flex justify-between text-xl font-bold text-gray-900">
                    <span>Վճարման</span>
                    <span>{formatPrice(getTotalPrice())} ֏</span>
                  </div>
                </div>
              </div>
              
              <Link
                href="/checkout"
                className="w-full text-white py-4 rounded-xl font-semibold transition-colors text-center block text-lg"
                style={{ backgroundColor: '#002c45' }}
              >
                Պատվիրել
              </Link>
              
              <div className="mt-4 text-center">
                <Link 
                  href="/products"
                  className="text-gray-900 transition-colors text-sm"
                  style={{ '--hover-color': '#002c45' } as React.CSSProperties}
                  onMouseEnter={(e) => e.currentTarget.style.color = '#002c45'}
                  onMouseLeave={(e) => e.currentTarget.style.color = '#000000'}
                >
                  Շարունակել գնումները
                </Link>
              </div>
            </div>
          </div>
        </div>

        {/* Mobile Order Summary */}
        <div className="lg:hidden">
          <div className="bg-white rounded-2xl shadow-lg p-6 mb-6">
            <h2 className="text-lg font-semibold text-white mb-4">Ընդամենը</h2>
            
            <div className="space-y-3 mb-6">
              <div className="flex justify-between text-gray-600">
                <span>Արտադրանք ({items.reduce((total, item) => total + item.quantity, 0)} հատ)</span>
                <span>{getTotalPrice()} ֏</span>
              </div>
              <div className="border-t border-gray-300 pt-3">
                <div className="flex justify-between text-lg font-bold text-gray-900">
                  <span>Վճարման</span>
                  <span>{formatPrice(getTotalPrice())} ֏</span>
                </div>
              </div>
            </div>
            
            <Link
              href="/checkout"
              className="w-full text-white py-4 rounded-xl font-semibold transition-colors text-center block text-lg"
              style={{ backgroundColor: '#002c45' }}
            >
              Оформить заказ
            </Link>
            
            <div className="mt-4 text-center">
              <Link 
                href="/products"
                className="text-gray-900 transition-colors text-sm"
                style={{ '--hover-color': '#002c45' } as React.CSSProperties}
                onMouseEnter={(e) => e.currentTarget.style.color = '#002c45'}
                onMouseLeave={(e) => e.currentTarget.style.color = '#000000'}
              >
                Շարունակել գնումները
              </Link>
            </div>
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
