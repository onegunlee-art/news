import { useState, useCallback } from 'react'
import { getPlaceholderImageUrl, ArticleForImage } from '../../utils/imagePolicy'

interface OptimizedImageProps {
  src?: string | null
  alt: string
  article?: ArticleForImage
  width?: number
  height?: number
  className?: string
  loading?: 'lazy' | 'eager'
}

/**
 * WebP 지원 최적화 이미지 컴포넌트
 * - WebP 포맷 우선 시도
 * - 로드 실패 시 placeholder로 fallback
 * - lazy loading 기본 적용
 */
export default function OptimizedImage({
  src,
  alt,
  article,
  width = 400,
  height = 400,
  className = '',
  loading = 'lazy',
}: OptimizedImageProps) {
  const [hasError, setHasError] = useState(false)
  const [currentSrc, setCurrentSrc] = useState(src)

  const placeholderUrl = article
    ? getPlaceholderImageUrl(article, width, height)
    : `https://picsum.photos/${width}/${height}`

  const handleError = useCallback(() => {
    if (!hasError && article) {
      setHasError(true)
      setCurrentSrc(placeholderUrl)
    }
  }, [hasError, article, placeholderUrl])

  const imageSrc = hasError ? placeholderUrl : (currentSrc || placeholderUrl)

  // WebP 버전 URL 생성 (picsum.photos는 .webp 지원)
  const webpSrc = imageSrc.includes('picsum.photos')
    ? imageSrc + '.webp'
    : imageSrc

  // 외부 이미지 (DALL-E, 원본 기사)는 picture 태그 없이 직접 렌더링
  const isExternalImage = !imageSrc.includes('picsum.photos')

  if (isExternalImage) {
    return (
      <img
        src={imageSrc}
        alt={alt}
        className={className}
        loading={loading}
        onError={handleError}
        decoding="async"
      />
    )
  }

  return (
    <picture>
      <source srcSet={webpSrc} type="image/webp" />
      <img
        src={imageSrc}
        alt={alt}
        className={className}
        loading={loading}
        onError={handleError}
        decoding="async"
      />
    </picture>
  )
}
