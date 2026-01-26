/**
 * Утилиты для работы с изображениями
 */

/**
 * Проверяет, является ли путь к изображению валидным
 * @param imagePath - путь к изображению (может быть локальным путем или URL от Blob)
 * @returns true если изображение валидно, false если нет
 */
export function isValidImagePath(imagePath: string | null | undefined): boolean {
  if (!imagePath) return false;
  
  // Разрешаем локальные пути /images/...
  if (imagePath.startsWith('/images/')) {
    const validExtensions = ['.jpg', '.jpeg', '.png', '.webp', '.avif', '.gif'];
    return validExtensions.some(ext => imagePath.toLowerCase().endsWith(ext));
  }
  
  // Разрешаем полные URL от Vercel Blob Storage
  if (imagePath.startsWith('https://') && imagePath.includes('public.blob.vercel-storage.com')) {
    const validExtensions = ['.jpg', '.jpeg', '.png', '.webp', '.avif', '.gif'];
    return validExtensions.some(ext => imagePath.toLowerCase().endsWith(ext));
  }
  
  return false;
}

/**
 * Возвращает fallback изображение для товаров без картинки
 * @returns путь к fallback изображению
 */
export function getFallbackImage(): string {
  return '/images/nophoto.jpg';
}

/**
 * Проверяет, является ли изображение placeholder'ом
 * @param imagePath - путь к изображению
 * @returns true если это placeholder
 */
export function isPlaceholderImage(imagePath: string | null | undefined): boolean {
  if (!imagePath) return true;
  
  const placeholderPaths = [
    'no-image',
    '/images/nophoto.jpg',
    '/images/placeholder-product.png',
    '/images/placeholder-product.svg',
  ];
  
  return placeholderPaths.includes(imagePath);
}
