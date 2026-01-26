'use client'

import Link from 'next/link'
import Image from 'next/image'
import { Search, Menu, X } from 'lucide-react'
import { useState, useRef, useEffect } from 'react'
import { useInstantSearch } from '@/hooks/useInstantSearch'
import { SearchDropdown } from '@/components/SearchDropdown'
import { useSettings } from '@/hooks/useSettings'
import { usePathname } from 'next/navigation'
import { useHydration } from '@/hooks/useHydration'
import { createPortal } from 'react-dom'

export default function MobileHeader() {
  const [isSearchOpen, setIsSearchOpen] = useState(false)
  const [isMenuOpen, setIsMenuOpen] = useState(false)
  const pathname = usePathname()
  const isHydrated = useHydration()
  const { settings } = useSettings()
  
  // Instant search hook (search-as-you-type)
  const {
    query,
    setQuery,
    results,
    loading,
    error,
    isOpen,
    setIsOpen,
    selectedIndex,
    setSelectedIndex,
    handleKeyDown,
    clearSearch
  } = useInstantSearch({
    debounceMs: 200,
    minQueryLength: 1, // Поиск работает с первого символа
    maxResults: 4 // Меньше результатов для мобильного
  })

  const searchRef = useRef<HTMLDivElement>(null)

  // Закрытие dropdown при клике вне его
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (searchRef.current && !searchRef.current.contains(event.target as Node)) {
        setIsOpen(false)
      }
    }

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside)
      return () => document.removeEventListener('mousedown', handleClickOutside)
    }
  }, [isOpen, setIsOpen])

  // Блокировка скролла когда меню открыто
  useEffect(() => {
    if (isMenuOpen) {
      document.body.style.overflow = 'hidden'
    } else {
      document.body.style.overflow = ''
    }
    
    return () => {
      document.body.style.overflow = ''
    }
  }, [isMenuOpen])

  const isActive = (path: string) => {
    if (path === '/') {
      return pathname === '/'
    }
    return pathname.startsWith(path)
  }

  // Ссылки для меню
  const menuLinks = [
    { href: '/', label: 'Գլխավոր' },
    { href: '/products', label: 'Կատալոգ' },
    { href: '/about', label: 'Մեր մասին' },
    { href: '/contact', label: 'Կապ' },
  ]

  // Обработка клика по результату поиска
  const handleResultClick = (result: any) => {
    window.location.href = `/products/${result.id}`
    setIsOpen(false)
    setIsSearchOpen(false)
    clearSearch()
  }

  return (
    <header className="backdrop-blur-xl shadow-lg fixed top-0 left-0 right-0 z-[100] border-b border-white/20" style={{ position: 'fixed', backgroundColor: '#002c45' }}>
      <div className="px-4 py-1.5">
        <div className="relative flex justify-between items-center">
          {/* Burger Menu Button */}
          <button
            onClick={() => setIsMenuOpen(!isMenuOpen)}
            className="p-3 text-white/90 hover:text-white hover:bg-white/10 rounded-xl transition-all duration-300 active:scale-95"
            aria-label="Открыть меню"
          >
            {isMenuOpen ? (
              <X className="h-5 w-5" />
            ) : (
              <Menu className="h-5 w-5" />
            )}
          </button>

          {/* Mobile Logo - Absolutely Centered */}
          <div className="absolute left-1/2 transform -translate-x-1/2">
            <Link href="/" className="hover:opacity-80 transition-all duration-300 hover:scale-105">
              <Image 
                src={settings.logo || "/images/logo.png"} 
                alt={settings.siteName || "WelcomeBaby Logo"} 
                width={120} 
                height={36}
                className="h-[32px] w-auto"
                style={{ width: "auto", height: "auto" }}
                priority
              />
            </Link>
          </div>

          {/* Mobile Search Button */}
          <button
            onClick={() => setIsSearchOpen(!isSearchOpen)}
            className="p-3 text-white/90 hover:text-white hover:bg-white/10 rounded-xl transition-all duration-300 active:scale-95 ml-auto"
            aria-label="Поиск"
          >
            <Search className="h-5 w-5" />
          </button>
        </div>

        {/* Mobile Search Bar with Instant Search */}
        {isSearchOpen && (
          <div className="absolute top-full left-0 right-0 bg-white/95 backdrop-blur-xl shadow-2xl border-t border-gray-200 z-[100]">
            <div className="p-4">
               <div className="flex gap-3 relative" ref={searchRef}>
                 <div className="relative flex-1">
                  <Search className="absolute left-4 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-500" />
                  <input
                    type="search"
                    placeholder="Поиск по каталогу..."
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={handleKeyDown}
                    onFocus={() => {
                      // Открываем dropdown если есть результаты поиска (search-as-you-type)
                      if (query.length >= 1 && results.length > 0) {
                        setIsOpen(true)
                      }
                    }}
                    // Search as you type - поиск при вводе
                    onInput={(e) => {
                      // Автоматически открываем dropdown при вводе
                      if (query.length >= 1) {
                        setIsOpen(true)
                      }
                    }}
                    className="w-full pl-12 pr-4 py-4 border-2 border-gray-200 rounded-2xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-base text-gray-900 placeholder-gray-500 bg-gray-50 transition-all duration-300 shadow-sm hover:shadow-md focus:bg-white"
                    autoFocus
                    aria-controls="search-results-mobile"
                    aria-expanded={isOpen}
                    aria-autocomplete="list"
                  />
                  
                  {/* Clear button */}
                  {query && (
                    <button
                      onClick={clearSearch}
                      className="absolute right-4 top-1/2 transform -translate-y-1/2 p-1 hover:bg-gray-100 rounded transition-colors"
                    >
                      <svg className="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  )}
                </div>
                <button 
                  onClick={() => {
                    if (query.trim()) {
                      // Перенаправляем на страницу продуктов с поисковым запросом
                      window.location.href = `/products?search=${encodeURIComponent(query)}`
                      setIsSearchOpen(false)
                      clearSearch()
                    }
                  }}
                  className="w-16 h-16 rounded-2xl flex items-center justify-center transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105 active:scale-95"
                  style={{ backgroundColor: '#ffdd84' }}
                >
                  <Search className="w-6 h-6 text-white" />
                </button>
              </div>

              {/* Mobile Search Dropdown */}
               <div className="mt-2">
                 <SearchDropdown
                   results={results}
                   loading={loading}
                   error={error}
                   isOpen={isOpen}
                   selectedIndex={selectedIndex}
                   onResultClick={handleResultClick}
                   onClose={() => setIsOpen(false)}
                   className="relative shadow-none border-0 rounded-xl w-full max-w-none"
                 />
               </div>
            </div>
          </div>
        )}

      </div>

      {/* Burger Menu Overlay */}
      {isMenuOpen && isHydrated && createPortal(
        <div 
          className="fixed inset-0 bg-white z-[9999] animate-menu-fade-in"
          onClick={() => setIsMenuOpen(false)}
          style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0 }}
        >
          {/* Backdrop with blur effect */}
          <div className="absolute inset-0 bg-white"></div>
          
          {/* Menu Content Container */}
          <div className="relative z-10 h-full flex flex-col animate-menu-slide-in">
            {/* Menu Header */}
            <div className="bg-[#f3d98c] text-gray-900 p-6 flex items-center justify-between shadow-lg">
              <div className="flex items-center space-x-3">
                <div className="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                  <Menu className="w-5 h-5" />
                </div>
                <h2 className="text-2xl font-bold">Նավիգացիա</h2>
              </div>
              <button
                onClick={(e) => {
                  e.stopPropagation()
                  setIsMenuOpen(false)
                }}
                className="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center hover:bg-white/30 transition-all duration-300 active:scale-95"
              >
                <X className="w-5 h-5 text-white" />
              </button>
            </div>

            {/* Menu Items - Centered */}
            <div className="flex-1 flex flex-col justify-center px-6 py-8">
              <div className="space-y-3">
                {menuLinks.map((link) => (
                  <Link
                    key={link.href}
                    href={link.href}
                    className={`
                      group block px-8 py-6 rounded-2xl text-gray-700 hover:bg-[#f3d98c] hover:text-gray-900 transition-all duration-300 font-semibold text-xl text-center shadow-lg hover:shadow-xl transform hover:scale-105 active:scale-95 whitespace-nowrap
                      ${isActive(link.href) 
                        ? 'bg-[#f3d98c] text-gray-900 shadow-xl scale-105' 
                        : 'bg-white/80 backdrop-blur-sm border border-gray-200'
                      }
                    `}
                    onClick={() => setIsMenuOpen(false)}
                  >
                    <span className="block whitespace-nowrap">{link.label}</span>
                    {isActive(link.href) && (
                      <div className="mt-2 w-8 h-1 bg-white/30 rounded-full mx-auto"></div>
                    )}
                  </Link>
                ))}
              </div>
            </div>

            {/* Bottom Info */}
            <div className="p-6 bg-gray-50 border-t border-gray-200">
              {/* Legal Links */}
              <div className="flex justify-center space-x-4 mb-4">
                <Link 
                  href="/privacy-policy" 
                  className="text-xs text-gray-400 hover:text-[#f3d98c] transition-colors duration-200 underline decoration-dotted underline-offset-2"
                  onClick={() => setIsMenuOpen(false)}
                >
                  Գաղտնիության քաղաքականություն
                </Link>
                <span className="text-xs text-gray-300">•</span>
                <Link 
                  href="/terms-and-conditions" 
                  className="text-xs text-gray-400 hover:text-[#f3d98c] transition-colors duration-200 underline decoration-dotted underline-offset-2"
                  onClick={() => setIsMenuOpen(false)}
                >
                  Օգտագործման պայմաններ
                </Link>
              </div>
              
              <div className="text-center text-gray-600">
                <p className="text-xs text-gray-500 mb-2">
                  Copyright © 2025. All Rights Reserved.
                </p>
                <p className="text-xs text-gray-500">
                  Created by{' '}
                  <a 
                    href="https://neetrino.com" 
                    target="_blank" 
                    rel="noopener noreferrer"
                    className="text-[#f3d98c] hover:text-[#f3d98c] font-semibold transition-colors duration-200"
                  >
                    Neetrino
                  </a>
                </p>
              </div>
            </div>
          </div>
        </div>,
        document.body
      )}
    </header>
  )
}
