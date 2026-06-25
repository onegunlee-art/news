<?php
/**
 * 기업 고객 표시명 (company_tag 또는 이메일 도메인)
 */

function corporateResolveTag(?string $companyTag, ?string $email): ?string
{
    $tag = strtolower(trim((string) $companyTag));
    if ($tag !== '') {
        return $tag;
    }

    $email = strtolower(trim((string) $email));
    if ($email === '' || !str_contains($email, '@')) {
        return null;
    }

    $domain = substr(strrchr($email, '@'), 1) ?: '';
    foreach (['hyundai', 'kt', 'samsung'] as $known) {
        if (str_contains($domain, $known)) {
            return $known;
        }
    }

    return null;
}

function corporateBrandName(string $tag): string
{
    return match (strtolower($tag)) {
        'hyundai' => '현대 자동차',
        'kt' => 'KT',
        'samsung' => '삼성',
        default => $tag,
    };
}

function corporateSubscriptionPlanName(?string $companyTag, ?string $email): ?string
{
    $tag = corporateResolveTag($companyTag, $email);
    if ($tag === null) {
        return null;
    }

    return corporateBrandName($tag) . ' 구독';
}
