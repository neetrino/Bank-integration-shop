// Service Worker для кэширования и оптимизации производительности
const CACHE_NAME = 'welcomebaby-v1.0.4'

// Файлы для кэширования (только статические ресурсы)
const STATIC_FILES = [
  '/',
  '/manifest.json',
  '/favicon.ico',
  '/logo.png'
]

// Установка Service Worker
self.addEventListener('install', (event) => {
  console.log('Service Worker: Installing...')
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('Service Worker: Caching static files')
        return cache.addAll(STATIC_FILES)
          .catch((error) => {
            console.warn('Service Worker: Some files failed to cache:', error)
            return Promise.resolve()
          })
      })
      .then(() => {
        console.log('Service Worker: Installation complete')
        return self.skipWaiting()
      })
  )
})

// Активация Service Worker
self.addEventListener('activate', (event) => {
  console.log('Service Worker: Activating...')
  
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (cacheName !== CACHE_NAME) {
              console.log('Service Worker: Deleting old cache:', cacheName)
              return caches.delete(cacheName)
            }
          })
        )
      })
      .then(() => {
        console.log('Service Worker: Activation complete')
        return self.clients.claim()
      })
  )
})

// Перехват запросов - простой подход без дублирования
self.addEventListener('fetch', (event) => {
  const { request } = event
  
  // Обрабатываем только GET запросы
  if (request.method !== 'GET') {
    return
  }

  // Игнорируем запросы от Chrome расширений и других неподдерживаемых схем
  const url = new URL(request.url)
  if (url.protocol === 'chrome-extension:' || 
      url.protocol === 'moz-extension:' || 
      url.protocol === 'safari-extension:' ||
      url.protocol !== 'http:' && url.protocol !== 'https:') {
    // Просто пропускаем эти запросы, не обрабатываем
    return
  }

  // НЕ кэшируем API запросы, статические файлы Next.js и динамические данные
  if (request.url.includes('/api/auth/') || 
      request.url.includes('/api/user/') ||
      request.url.includes('/api/admin/') ||
      request.url.includes('/api/orders') ||
      request.url.includes('/api/products') && request.url.includes('?') ||
      request.url.includes('_next/static/') ||
      request.url.includes('_next/static/chunks/') ||
      request.url.includes('_next/static/css/') ||
      request.url.includes('_next/static/media/') ||
      request.url.includes('_next/static/chunks/app/') ||
      request.url.includes('_next/static/chunks/pages/')) {
    // Для API запросов и всех статических файлов Next.js - всегда идем в сеть, не кэшируем
    event.respondWith(
      fetch(request).catch((error) => {
        console.warn('Service Worker: Network fetch failed for', request.url, error)
        // Возвращаем ошибку, чтобы браузер мог обработать её
        return new Response('Network error', { 
          status: 503, 
          statusText: 'Service Unavailable' 
        })
      })
    )
    return
  }

  event.respondWith(
    (async () => {
      try {
        // Дополнительная проверка на chrome-extension перед кэшированием
        try {
          const url = new URL(request.url)
          if (url.protocol === 'chrome-extension:' || 
              url.protocol === 'moz-extension:' || 
              url.protocol === 'safari-extension:' ||
              (url.protocol !== 'http:' && url.protocol !== 'https:')) {
            // Для неподдерживаемых схем - просто делаем fetch без кэширования
            return fetch(request)
          }
        } catch (urlError) {
          // Если не удалось создать URL, пропускаем этот запрос
          return fetch(request)
        }

        // Сначала проверяем кэш
        const cachedResponse = await caches.match(request)
        if (cachedResponse) {
          console.log('Service Worker: Serving from cache:', request.url)
          return cachedResponse
        }
        
        // Загружаем из сети с обработкой ошибок
        const networkResponse = await fetch(request)
        
        // Кэшируем только успешные ответы и только статические ресурсы (НЕ файлы Next.js)
        // И только если это не chrome-extension запрос
        if (networkResponse.status === 200 && 
            !request.url.includes('/api/') &&
            !request.url.includes('_next/static/') &&
            !request.url.includes('_next/static/chunks/') &&
            !request.url.includes('_next/static/css/') &&
            !request.url.includes('_next/static/media/') &&
            !request.url.startsWith('chrome-extension:') &&
            !request.url.startsWith('moz-extension:') &&
            !request.url.startsWith('safari-extension:')) {
          try {
            const responseClone = networkResponse.clone()
            const cache = await caches.open(CACHE_NAME)
            await cache.put(request, responseClone)
          } catch (cacheError) {
            // Игнорируем ошибки кэширования (например, для chrome-extension)
            console.warn('Service Worker: Cache put failed (ignored):', cacheError)
          }
        }
        
        return networkResponse
      } catch (error) {
        console.warn('Service Worker: Fetch failed for', request.url, error)
        
        // Для статических файлов Next.js - не пытаемся кэшировать, просто пробрасываем ошибку
        if (request.url.includes('_next/static/')) {
          // Возвращаем ошибку сети, чтобы браузер мог обработать её
          return new Response('Network error', { 
            status: 503, 
            statusText: 'Service Unavailable' 
          })
        }
        
        // Для других файлов - пробуем вернуть из кэша
        const fallbackResponse = await caches.match('/')
        return fallbackResponse || new Response('Offline', { 
          status: 503, 
          statusText: 'Service Unavailable' 
        })
      }
    })()
  )
})


// Обработка push уведомлений (для будущего использования)
self.addEventListener('push', (event) => {
  console.log('Service Worker: Push notification received')
  
  const options = {
    body: event.data ? event.data.text() : 'Новое уведомление от WelcomeBaby',
    icon: '/images/logo.png',
    badge: '/images/logo.png',
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    },
    actions: [
      {
        action: 'explore',
        title: 'Посмотреть',
        icon: '/images/logo.png'
      },
      {
        action: 'close',
        title: 'Закрыть',
        icon: '/images/logo.png'
      }
    ]
  }
  
  event.waitUntil(
    self.registration.showNotification('WelcomeBaby', options)
  )
})

// Обработка кликов по уведомлениям
self.addEventListener('notificationclick', (event) => {
  console.log('Service Worker: Notification clicked')
  
  event.notification.close()
  
  if (event.action === 'explore') {
    event.waitUntil(
      clients.openWindow('/')
    )
  }
})

console.log('Service Worker: Loaded successfully')
