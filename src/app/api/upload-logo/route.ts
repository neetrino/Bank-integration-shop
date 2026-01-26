import { NextRequest, NextResponse } from 'next/server'
import { getServerSession } from 'next-auth/next'
import { authOptions } from '@/lib/auth'
import { uploadFile } from '@/lib/blob'
import { logger } from '@/lib/logger'

export async function POST(request: NextRequest) {
  try {
    // Проверяем авторизацию
    const session = await getServerSession(authOptions)
    if (!session || session.user.role !== 'ADMIN') {
      return NextResponse.json(
        { error: 'Unauthorized' },
        { status: 401 }
      )
    }

    const formData = await request.formData()
    const file = formData.get('file') as File

    if (!file) {
      return NextResponse.json(
        { error: 'No file provided' },
        { status: 400 }
      )
    }

    // Проверяем тип файла
    if (!file.type.startsWith('image/')) {
      return NextResponse.json(
        { error: 'File must be an image' },
        { status: 400 }
      )
    }

    // Проверяем размер файла (максимум 2MB)
    if (file.size > 2 * 1024 * 1024) {
      return NextResponse.json(
        { error: 'File size must be less than 2MB' },
        { status: 400 }
      )
    }

    // Определяем расширение файла
    const extension = file.name.split('.').pop()?.toLowerCase() || 'png'

    // Загружаем логотип в Blob
    const fileName = `logo/logo-${Date.now()}.${extension}`
    const { url, path } = await uploadFile(file, fileName)

    // Возвращаем успешный ответ
    return NextResponse.json({ 
      success: true,
      message: 'Logo updated successfully',
      url: url,
      path: path || url
    })
  } catch (error) {
    logger.error('Logo upload error', error)
    return NextResponse.json(
      { error: 'Failed to update logo' },
      { status: 500 }
    )
  }
}
