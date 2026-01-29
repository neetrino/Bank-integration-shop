'use client'

import { useState } from 'react'
import { useSearchParams } from 'next/navigation'
import { signIn } from 'next-auth/react'
import Link from 'next/link'
import { Eye, EyeOff, Mail, Lock } from 'lucide-react'
import Footer from '@/components/Footer'

function getSafeRedirect(callbackUrl: string | null): string {
  if (!callbackUrl) return '/'
  try {
    if (callbackUrl.startsWith('/') && !callbackUrl.startsWith('//')) {
      return callbackUrl
    }
    const url = new URL(callbackUrl)
    if (typeof window !== 'undefined' && url.origin === window.location.origin) {
      return url.pathname + url.search
    }
    return url.pathname.startsWith('/') ? url.pathname : '/'
  } catch {
    return '/'
  }
}

export default function LoginPage() {
  const searchParams = useSearchParams()
  const callbackUrl = searchParams.get('callbackUrl')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState('')

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsLoading(true)
    setError('')

    try {
      const result = await signIn('credentials', {
        email,
        password,
        redirect: false,
      })

      if (result?.error) {
        setError('Սխալ email կամ գաղտնաբառ')
      } else {
        window.location.href = getSafeRedirect(callbackUrl)
      }
    } catch (error) {
      setError('Մուտք գործելիս սխալ է տեղի ունեցել')
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="min-h-screen" style={{ backgroundColor: '#ffffff' }}>
      
      {/* Mobile Header */}
      <div className="lg:hidden fixed top-0 left-0 right-0 z-50 bg-white border-b border-gray-200">
        <div className="flex items-center justify-between px-4 py-3 h-16">
          <Link 
            href="/"
            className="flex items-center text-gray-600 hover:text-[#f3d98c] transition-colors"
          >
            <svg className="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
            </svg>
            Վերադառնալ
          </Link>
          <h1 className="text-lg font-semibold text-gray-900">Մուտք</h1>
          <div className="w-20"></div> {/* Spacer for centering */}
        </div>
      </div>
      
      {/* Отступ для fixed хедера */}
      <div className="lg:hidden h-20"></div>
      <div className="hidden lg:block h-28"></div>
      
      <div className="max-w-md mx-auto px-4 sm:px-6 lg:px-8 pt-32 pb-16">
        <div className="bg-white rounded-2xl shadow-lg p-8">
          <div className="text-center mb-8">
            <h1 className="text-3xl font-bold text-gray-900 mb-2">Մուտք հաշիվ</h1>
            <p className="text-gray-600">Մուտք գործեք պատվերները կառավարելու համար</p>
          </div>

          {error && (
            <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                <Mail className="inline h-4 w-4 mr-1" />
                Email
              </label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-[#f3d98c] focus:border-[#f3d98c] transition-colors text-gray-800 bg-white"
                placeholder="your@email.com"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                <Lock className="inline h-4 w-4 mr-1" />
                Գաղտնաբառ
              </label>
              <div className="relative">
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="w-full px-4 py-3 pr-12 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-[#f3d98c] focus:border-[#f3d98c] transition-colors text-gray-800 bg-white"
                  placeholder="Մուտքագրեք գաղտնաբառը"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                >
                  {showPassword ? <EyeOff className="h-5 w-5" /> : <Eye className="h-5 w-5" />}
                </button>
              </div>
            </div>

            <button
              type="submit"
              disabled={isLoading}
              className="w-full bg-[#f3d98c] text-gray-900 py-4 rounded-xl font-semibold hover:bg-[#f3d98c] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isLoading ? 'Մուտք գործում ենք...' : 'Մուտք գործել'}
            </button>
          </form>

          <div className="mt-6 text-center">
            <p className="text-gray-600">
              Հաշիվ չունե՞ք?{' '}
              <Link href="/register" className="text-[#f3d98c] hover:text-[#f3d98c] font-semibold">
                Գրանցվել
              </Link>
            </p>
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
