import { EDU_BRAND } from '../../constants/eduBrand'
import { isEssayNarrationMode } from './essayUtils'

export interface EssaySection {
  heading: string
  paragraphs: string[]
}

export interface EssayArtifact {
  title?: string | null
  subtitle?: string | null
  sections?: EssaySection[]
  body_paragraphs?: string[]
  narration_mode?: boolean
  conclusion_heading?: string
  conclusion_paragraphs?: string[]
  full_text?: string
  hero_sentence?: string | null
  feedback?: string | null
}

interface EssayRevealCardProps {
  essay: EssayArtifact
  /** article: 완성 글 잡지형 타이포 */
  variant?: 'default' | 'article'
  authorName?: string | null
}

export default function EssayRevealCard({
  essay,
  variant = 'default',
  authorName,
}: EssayRevealCardProps) {
  const isArticle = variant === 'article'
  const narration = isEssayNarrationMode(essay)
  const hasStructure =
    !narration &&
    ((essay.sections?.length ?? 0) > 0 ||
      Boolean(essay.title) ||
      Boolean(essay.subtitle))

  const titleClass = isArticle
    ? 'text-2xl sm:text-3xl font-bold leading-tight tracking-tight'
    : 'text-lg font-bold leading-snug'
  const subtitleClass = isArticle
    ? 'text-base leading-relaxed'
    : 'text-sm text-[#666] leading-relaxed'
  const bodyClass = isArticle
    ? 'text-base leading-[1.75] text-[#333]'
    : 'text-sm leading-relaxed text-[#333]'
  const headingClass = isArticle
    ? 'text-sm font-bold tracking-wide'
    : 'text-sm font-bold border-l-2 pl-2'

  if (narration || (!hasStructure && essay.full_text)) {
    const paragraphs = essay.body_paragraphs?.length
      ? essay.body_paragraphs
      : (essay.full_text ?? '').split(/\n{2,}/).filter(Boolean)
    return (
      <div className="space-y-4">
        {essay.title && <h2 className={titleClass}>{essay.title}</h2>}
        {essay.subtitle && (
          <p className={subtitleClass} style={isArticle ? { color: EDU_BRAND.muted } : undefined}>
            {essay.subtitle}
          </p>
        )}
        <div className={isArticle ? 'space-y-5' : 'space-y-3'}>
          {paragraphs.map((p, i) => (
            <p key={i} className={`${bodyClass} whitespace-pre-wrap`}>
              {p}
            </p>
          ))}
        </div>
        {essay.hero_sentence && isArticle && (
          <blockquote
            className="text-lg leading-snug italic py-4 px-5 rounded-xl"
            style={{
              color: EDU_BRAND.ink,
              backgroundColor: EDU_BRAND.accentBg,
              borderLeft: `4px solid ${EDU_BRAND.accent}`,
            }}
          >
            {essay.hero_sentence}
          </blockquote>
        )}
        {isArticle && authorName && <ArticleByline name={authorName} />}
      </div>
    )
  }

  if (!hasStructure && essay.full_text) {
    return (
      <div className="space-y-4">
        <p className={`${bodyClass} whitespace-pre-wrap`}>{essay.full_text}</p>
        {isArticle && authorName && <ArticleByline name={authorName} />}
      </div>
    )
  }

  return (
    <article className={isArticle ? 'space-y-8' : 'space-y-5'}>
      {essay.title && <h2 className={titleClass}>{essay.title}</h2>}
      {essay.subtitle && (
        <p className={subtitleClass} style={isArticle ? { color: EDU_BRAND.muted } : undefined}>
          {essay.subtitle}
        </p>
      )}

      {(essay.sections ?? []).map((sec, i) => (
        <section key={`${sec.heading}-${i}`} className={isArticle ? 'space-y-4' : 'space-y-2'}>
          {sec.heading && (
            <h3
              className={headingClass}
              style={
                isArticle
                  ? { color: EDU_BRAND.accent, borderLeft: `3px solid ${EDU_BRAND.accent}`, paddingLeft: '0.75rem' }
                  : { borderColor: EDU_BRAND.ink }
              }
            >
              {sec.heading}
            </h3>
          )}
          {(sec.paragraphs ?? []).map((p, j) => (
            <p key={`${sec.heading}-${j}`} className={bodyClass}>
              {p}
            </p>
          ))}
        </section>
      ))}

      {(essay.conclusion_paragraphs?.length ?? 0) > 0 && (
        <section
          className={isArticle ? 'space-y-4 pt-2' : 'space-y-2 border-t border-[#eee] pt-4'}
          style={isArticle ? { borderTop: `1px solid ${EDU_BRAND.border}`, paddingTop: '1.5rem' } : undefined}
        >
          <h3 className={isArticle ? 'text-base font-bold' : 'text-sm font-bold'}>
            {essay.conclusion_heading ?? '결론'}
          </h3>
          {(essay.conclusion_paragraphs ?? []).map((p, i) => (
            <p key={`conclusion-${i}`} className={bodyClass}>
              {p}
            </p>
          ))}
        </section>
      )}

      {essay.hero_sentence && isArticle && (
        <blockquote
          className="text-lg leading-snug italic py-4 px-5 rounded-xl"
          style={{
            color: EDU_BRAND.ink,
            backgroundColor: EDU_BRAND.accentBg,
            borderLeft: `4px solid ${EDU_BRAND.accent}`,
          }}
        >
          {essay.hero_sentence}
        </blockquote>
      )}

      {isArticle && authorName && <ArticleByline name={authorName} />}
    </article>
  )
}

function ArticleByline({ name }: { name: string }) {
  return (
    <footer
      className="text-right text-sm pt-6 mt-4"
      style={{ color: EDU_BRAND.muted, borderTop: `1px solid ${EDU_BRAND.border}` }}
    >
      by {name}
    </footer>
  )
}
