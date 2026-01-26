'use client'

import { useState, useEffect, useRef } from 'react'
import { Button } from '@/components/ui/button'
import { 
  Upload, 
  X, 
  Image as ImageIcon,
  Check,
  Loader2
} from 'lucide-react'
import Image from 'next/image'

interface ImageSelectorProps {
  value: string
  onChange: (imagePath: string) => void
  className?: string
}

interface ImageFile {
  name: string
  path: string
  category?: string
}

export default function ImageSelector({ value, onChange, className = '' }: ImageSelectorProps) {
  const [activeTab, setActiveTab] = useState<'gallery' | 'upload' | null>(null)
  const [images, setImages] = useState<ImageFile[]>([])
  const [uploading, setUploading] = useState(false)
  const [loadingGallery, setLoadingGallery] = useState(false)
  const [dragOver, setDragOver] = useState(false)
  const fileInputRef = useRef<HTMLInputElement>(null)

  // Загружаем список существующих изображений только при переходе на вкладку "Галерея"
  const loadImages = async () => {
    if (images.length > 0) return // Уже загружены
    
    setLoadingGallery(true)
    try {
      // Получаем список изображений из API
      const response = await fetch('/api/images')
      if (response.ok) {
        const imageList = await response.json()
        setImages(imageList)
      }
    } catch (error) {
      console.error('Error loading images:', error)
    } finally {
      setLoadingGallery(false)
    }
  }

  // Загружаем изображения при переходе на вкладку "Галерея"
  useEffect(() => {
    if (activeTab === 'gallery') {
      loadImages()
    }
  }, [activeTab])


  // Обработка загрузки файлов
  const handleFileUpload = async (files: FileList) => {
    if (!files.length) return

    setUploading(true)
    
    try {
      for (let i = 0; i < files.length; i++) {
        const file = files[i]
        
        // Проверяем тип файла
        if (!file.type.startsWith('image/')) {
          alert(`Файл ${file.name} не является изображением`)
          continue
        }

        // Проверяем размер файла (максимум 5MB)
        if (file.size > 5 * 1024 * 1024) {
          alert(`Файл ${file.name} слишком большой (максимум 5MB)`)
          continue
        }

        const formData = new FormData()
        formData.append('image', file)

        const response = await fetch('/api/upload-image', {
          method: 'POST',
          body: formData
        })

        if (response.ok) {
          const result = await response.json()
          
          // Используем полный URL от Blob (url) - это важно для работы с Vercel Blob Storage
          const imageUrl = result.url || result.path
          
          // Добавляем новое изображение в список
          setImages(prev => [...prev, {
            name: file.name,
            path: imageUrl
          }])
          
          // Если это первое загруженное изображение, выбираем его
          if (i === 0 && !value) {
            onChange(imageUrl)
          }
        } else {
          const error = await response.json()
          alert(`Ошибка загрузки ${file.name}: ${error.message}`)
        }
      }
    } catch (error) {
      console.error('Upload error:', error)
      alert('Ошибка загрузки изображений')
    } finally {
      setUploading(false)
    }
  }

  // Обработка drag & drop
  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault()
    setDragOver(true)
  }

  const handleDragLeave = (e: React.DragEvent) => {
    e.preventDefault()
    setDragOver(false)
  }

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault()
    setDragOver(false)
    
    const files = e.dataTransfer.files
    handleFileUpload(files)
  }

  // Обработка клика по файлу
  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files
    if (files) {
      handleFileUpload(files)
    }
  }

  // Выбор изображения из галереи
  const selectImage = (imagePath: string) => {
    onChange(imagePath)
  }

  // Удаление выбранного изображения
  const clearImage = () => {
    onChange('')
  }

  return (
    <div className={`${className}`}>
      {/* Основной контейнер с изображением и кнопками */}
      <div className="flex gap-4 items-start">
        {/* Предпросмотр выбранного изображения - слева, квадратное */}
        <div className="relative flex-shrink-0">
          <div className="relative w-36 h-36 bg-gray-100 rounded-lg overflow-hidden border border-gray-200">
            {value && value !== 'no-image' ? (
              <Image
                src={value}
                alt="Предпросмотр"
                fill
                sizes="144px"
                className="object-cover"
                onError={(e) => {
                  // Если изображение не загружается, показываем placeholder
                  const target = e.target as HTMLImageElement
                  target.style.display = 'none'
                }}
              />
            ) : (
              <div className="w-full h-full flex items-center justify-center">
                <ImageIcon className="h-12 w-12 text-gray-400" />
              </div>
            )}
          </div>
          {value && value !== 'no-image' && (
            <Button
              type="button"
              variant="destructive"
              size="sm"
              className="absolute -top-2 -right-2 h-6 w-6 p-0"
              onClick={clearImage}
            >
              <X className="h-3 w-3" />
            </Button>
          )}
        </div>

        {/* Кнопки справа от изображения */}
        <div className="flex flex-col gap-3">
          <Button
            type="button"
            variant={activeTab === 'gallery' ? 'default' : 'outline'}
            size="default"
            onClick={() => setActiveTab(activeTab === 'gallery' ? null : 'gallery')}
            className={`w-32 h-10 ${
              activeTab === 'gallery' 
                ? 'bg-[#f3d98c] hover:bg-[#f3d98c] text-gray-900 border-[#f3d98c]' 
                : 'border-[#f3d98c]/30 text-[#f3d98c] hover:bg-[#f3d98c]/10 hover:border-[#f3d98c]/50'
            }`}
          >
            Галерея
          </Button>
          <Button
            type="button"
            variant={activeTab === 'upload' ? 'default' : 'outline'}
            size="default"
            onClick={() => setActiveTab(activeTab === 'upload' ? null : 'upload')}
            className={`w-32 h-10 ${
              activeTab === 'upload' 
                ? 'bg-orange-500 hover:bg-orange-600 text-white border-orange-500' 
                : 'border-orange-300 text-orange-600 hover:bg-orange-50 hover:border-orange-400'
            }`}
          >
            Загрузить
          </Button>
        </div>
      </div>

      {/* Содержимое вкладок */}
      {activeTab === 'gallery' && (
        <div className="mt-4">
          {/* Галерея изображений */}
          {loadingGallery ? (
            <div className="flex items-center justify-center py-8">
              <Loader2 className="h-8 w-8 animate-spin text-[#f3d98c]" />
              <span className="ml-2 text-gray-600">Загрузка галереи...</span>
            </div>
          ) : (
            <>
              <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 max-h-96 overflow-y-auto">
                {images.map((image) => (
                  <button
                    key={image.path}
                    type="button"
                    className={`relative aspect-square rounded-lg overflow-hidden border-2 transition-all hover:scale-105 ${
                      value === image.path
                        ? 'border-[#f3d98c] ring-2 ring-[#f3d98c]/20'
                        : 'border-gray-200 hover:border-gray-300'
                    }`}
                    onClick={() => selectImage(image.path)}
                  >
                    <Image
                      src={image.path}
                      alt={image.name}
                      fill
                      sizes="(max-width: 640px) 50vw, (max-width: 768px) 33vw, 25vw"
                      className="object-cover"
                    />
                    {value === image.path && (
                      <div className="absolute inset-0 bg-[#f3d98c] bg-opacity-20 flex items-center justify-center">
                        <Check className="h-8 w-8 text-[#f3d98c]" />
                      </div>
                    )}
                  </button>
                ))}
              </div>

              {images.length === 0 && !loadingGallery && (
                <div className="text-center py-8 text-gray-500">
                  <ImageIcon className="h-12 w-12 mx-auto mb-2 opacity-50" />
                  <p>Изображения не найдены</p>
                </div>
              )}
            </>
          )}
        </div>
      )}

      {activeTab === 'upload' && (
        <div className="mt-4 space-y-4">
          {/* Drag & Drop область */}
          <div
            className={`border-2 border-dashed rounded-lg p-8 text-center transition-colors ${
              dragOver
                ? 'border-[#f3d98c] bg-[#f3d98c]/10'
                : 'border-gray-300 hover:border-gray-400'
            }`}
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            onDrop={handleDrop}
          >
            <Upload className="h-12 w-12 mx-auto mb-4 text-gray-400" />
            <p className="text-lg font-medium text-gray-900 mb-2">
              Перетащите изображения сюда
            </p>
            <p className="text-sm text-gray-500 mb-4">
              или нажмите кнопку для выбора файлов
            </p>
            <Button
              type="button"
              onClick={() => fileInputRef.current?.click()}
              disabled={uploading}
              className="flex items-center gap-2"
            >
              {uploading ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <Upload className="h-4 w-4" />
              )}
              {uploading ? 'Загрузка...' : 'Выбрать файлы'}
            </Button>
            <input
              ref={fileInputRef}
              type="file"
              multiple
              accept="image/*"
              onChange={handleFileSelect}
              className="hidden"
            />
          </div>

          <div className="text-xs text-gray-500 text-center">
            Поддерживаемые форматы: JPG, PNG, GIF, WebP. Максимальный размер: 5MB
          </div>
        </div>
      )}
    </div>
  )
}
