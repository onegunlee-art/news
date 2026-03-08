import { clsx } from 'clsx'

export interface MaterialIconProps {
  name: string
  className?: string
  filled?: boolean
  size?: number
  weight?: number
}

/**
 * Google Material Symbols Outlined - ligature 기반 아이콘 컴포넌트.
 * index.html에서 폰트 로드 필요.
 */
export default function MaterialIcon({
  name,
  className = '',
  filled = false,
  size = 24,
  weight = 400,
}: MaterialIconProps) {
  const opsz = Math.min(48, Math.max(20, size))
  return (
    <span
      className={clsx('material-symbols-outlined inline-block select-none', filled && 'filled', className)}
      style={{
        fontVariationSettings: `'FILL' ${filled ? 1 : 0}, 'wght' ${weight}, 'GRAD' 0, 'opsz' ${opsz}`,
        fontSize: size,
        width: size,
        height: size,
      }}
      aria-hidden
    >
      {name}
    </span>
  )
}
