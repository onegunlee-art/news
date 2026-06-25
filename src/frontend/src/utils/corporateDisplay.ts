import type { User } from '../store/authStore'

const CORPORATE_BRAND_NAMES: Record<string, string> = {
  hyundai: '현대 자동차',
  kt: 'KT',
  samsung: '삼성',
}

const KNOWN_CORPORATE_TAGS = ['hyundai', 'kt', 'samsung'] as const

export function resolveCorporateTag(user: Pick<User, 'company_tag' | 'email'> | null | undefined): string | null {
  if (!user) return null

  const tag = user.company_tag?.trim().toLowerCase()
  if (tag) return tag

  const email = user.email?.trim().toLowerCase() ?? ''
  const domain = email.split('@')[1] ?? ''
  if (!domain) return null

  for (const known of KNOWN_CORPORATE_TAGS) {
    if (domain.includes(known)) return known
  }

  return null
}

export function corporateBrandName(tag: string): string {
  return CORPORATE_BRAND_NAMES[tag.toLowerCase()] ?? tag
}

export function corporateCustomerLabel(user: Pick<User, 'company_tag' | 'email' | 'nickname'> | null | undefined): string {
  if (!user) return ''
  const tag = resolveCorporateTag(user)
  if (tag) return `${corporateBrandName(tag)} 고객님`
  return user.nickname
}

export function corporateSubscriptionLabel(user: Pick<User, 'company_tag' | 'email'> | null | undefined): string | null {
  const tag = resolveCorporateTag(user)
  if (!tag) return null
  return `${corporateBrandName(tag)} 구독`
}

export function isCorporateUser(user: Pick<User, 'company_tag' | 'email'> | null | undefined): boolean {
  return resolveCorporateTag(user) !== null
}
