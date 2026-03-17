import { Link } from 'react-router-dom'

const LOBSTER_STYLE: React.CSSProperties = { fontFamily: "'Lobster', cursive", fontWeight: 400 }

export type GistLogoSize = 'default' | 'header' | 'inline'

type GistLogoProps = {
  /** default: 푸터 스타일(text-3xl), header: 헤더용(약 20% 축소), inline: 문장 내 삽입 */
  size?: GistLogoSize
  /** true면 Link to="/" 로 감쌈. 기본 true */
  link?: boolean
  /** 시맨틱 태그. link 사용 시 권장: 헤더 h1, 푸터 h2 */
  as?: 'h1' | 'h2' | 'span'
  className?: string
}

const sizeClasses: Record<GistLogoSize, string> = {
  default: 'text-3xl text-page',
  header: 'text-2xl md:text-5xl font-normal text-page tracking-tight whitespace-nowrap',
  inline: 'text-page',
}

export default function GistLogo({ size = 'default', link = true, as: Tag = 'span', className = '' }: GistLogoProps) {
  const hoverClass = link && size === 'default' ? 'group-hover:opacity-90 transition-opacity duration-200' : ''
  const content = (
    <Tag
      className={`${sizeClasses[size]} ${hoverClass} ${className}`.trim()}
      style={LOBSTER_STYLE}
    >
      the gist.
    </Tag>
  )

  if (link) {
    const linkClass = size === 'default' ? 'inline-block group' : 'inline-block'
    return (
      <Link to="/" className={linkClass} aria-label="the gist. 홈">
        {content}
      </Link>
    )
  }

  return content
}
