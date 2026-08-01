<?php
declare(strict_types=1);

namespace Discovery;

final class DiscoveryQualityGate
{
    /** @param list<array<string,mixed>> $candidates @param list<array<string,mixed>> $discarded @return list<array<string,mixed>> */
    public function applyImportanceCategory(array $candidates, array &$discarded): array
    {
        $passed = [];
        foreach ($candidates as $candidate) {
            if ($this->passesImportanceCategory($candidate)) {
                $passed[] = $candidate;
                continue;
            }
            $discarded[] = [
                'title' => $candidate['title'] ?? '',
                'reason' => 'importance_category_failed',
                'detail' => $this->importanceFailureDetail($candidate),
            ];
        }

        return $passed;
    }

    /** @param list<array<string,mixed>> $candidates @param list<array<string,mixed>> $discarded @return list<array<string,mixed>> */
    public function applyCompleteness(array $candidates, array &$discarded): array
    {
        $passed = [];
        foreach ($candidates as $candidate) {
            if ($this->passesCompleteness($candidate)) {
                $passed[] = $candidate;
                continue;
            }
            $discarded[] = [
                'title' => $candidate['title'] ?? '',
                'reason' => 'content_incomplete',
                'detail' => $this->completenessFailureDetail($candidate),
            ];
        }

        return $passed;
    }

    /**
     * @param list<array<string,mixed>> $candidates
     * @param list<array<string,mixed>> $discarded
     * @return list<array<string,mixed>>
     */
    public function applyDeduplication(array $candidates, array &$discarded): array
    {
        $kept = [];
        foreach ($candidates as $candidate) {
            $mergedInto = null;
            foreach ($kept as $idx => $existing) {
                if (!$this->areDuplicates($existing, $candidate)) {
                    continue;
                }
                $mergedInto = $idx;
                break;
            }

            if ($mergedInto === null) {
                $kept[] = $candidate;
                continue;
            }

            $kept[$mergedInto] = $this->mergeChanges($kept[$mergedInto], $candidate);
            $discarded[] = [
                'title' => $candidate['title'] ?? '',
                'reason' => 'duplicate_merged',
                'merged_into' => $kept[$mergedInto]['title'] ?? '',
            ];
        }

        return $kept;
    }

    /** @param array<string,mixed> $candidate */
    public function passesImportanceCategory(array $candidate): bool
    {
        $text = $this->combinedText($candidate);
        if ($text === '') {
            return false;
        }

        if ($this->matchesExcludedImportance($text)) {
            return false;
        }

        if (preg_match('/(발표 예정|논의 중|개최 예정|할 예정|예정으로|예정이며|예정인)/u', $text) === 1) {
            return false;
        }

        $category = (string) ($candidate['category'] ?? 'other');
        if (in_array($category, ['geopolitics', 'business', 'tech', 'climate'], true)) {
            return $this->hasStructuralImpact($text);
        }

        return $this->hasStructuralImpact($text) && $this->matchesIncludedTopic($text);
    }

    /** @param array<string,mixed> $candidate */
    public function passesMaterialSignificance(array $candidate): bool
    {
        return $this->passesImportanceCategory($candidate);
    }

    /** @param array<string,mixed> $candidate */
    public function passesCompleteness(array $candidate): bool
    {
        $text = $this->combinedText($candidate);
        if ($text === '') {
            return false;
        }

        if (preg_match('/(발표 예정|논의 중|개최 예정|할 예정|예정으로|예정이며)/u', $text) === 1) {
            return false;
        }

        $hasNumber = $this->hasMeaningfulFigure($text);
        $title = trim((string) ($candidate['title'] ?? ''));
        $vagueAnnouncement = preg_match('/(발표|논의|회담|방문|착수|개막|예정)$/u', $title) === 1;

        if ($vagueAnnouncement && !$hasNumber) {
            return false;
        }

        if ($hasNumber) {
            return true;
        }

        if (preg_match('/(동결|인상|인하|승인|거부|체결|발효|제재|해제|파산|인수|합병|퇴출|당선|낙선|승리|패배|'
            . '지진|테러|공격|휴전|미사일|폭격|침공|'
            . '중지|중단|가동|폐쇄|철수|대피|탈출|사망|사상|'
            . '산불|화재|홍수|태풍|재난|쿠데타|탄핵|사임|석방|체포)/u', $text) === 1) {
            return true;
        }

        $whatChanged = trim((string) ($candidate['briefing']['what_changed'] ?? ''));
        if (mb_strlen($whatChanged) >= 40 && preg_match('/[가-힣A-Za-z]{2,}/u', $whatChanged) === 1) {
            return $this->hasMeaningfulFigure($whatChanged)
                || preg_match('/(법안|협정|조약|결의|판결|선언|명령|규정|금지|허가)/u', $whatChanged) === 1;
        }

        return false;
    }

    private function hasMeaningfulFigure(string $text): bool
    {
        if (preg_match('/\d[\d,\.]*\s*%|\d+\.\d+\s*%/u', $text) === 1) {
            return true;
        }
        if (preg_match('/\d+\.\d+/u', $text) === 1) {
            return true;
        }
        if (preg_match('/\d{2,}[\d,\.]*/u', $text) === 1) {
            return true;
        }

        return preg_match('/\d[\d,\.]*\s*(조|억|만|bn|billion|million|trillion|bp|bps|달러|유로|엔|원|명|대)/iu', $text) === 1;
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b */
    public function areDuplicates(array $a, array $b): bool
    {
        $titleA = trim((string) ($a['title'] ?? ''));
        $titleB = trim((string) ($b['title'] ?? ''));
        if ($titleA === '' || $titleB === '') {
            return false;
        }

        $normA = $this->normalizeTitle($titleA);
        $normB = $this->normalizeTitle($titleB);
        if ($normA === $normB) {
            return true;
        }

        $shorter = mb_strlen($normA) <= mb_strlen($normB) ? $normA : $normB;
        $longer = mb_strlen($normA) > mb_strlen($normB) ? $normA : $normB;
        if (mb_strlen($shorter) >= 8 && str_contains($longer, $shorter)) {
            return true;
        }

        $tokensA = $this->titleTokens($titleA);
        $tokensB = $this->titleTokens($titleB);
        if ($tokensA === [] || $tokensB === []) {
            return false;
        }

        $intersection = array_intersect($tokensA, $tokensB);
        $overlap = count($intersection) / min(count($tokensA), count($tokensB));

        if ($overlap >= 0.65) {
            return true;
        }

        return $this->sharePrimarySource($a, $b) && $overlap >= 0.45;
    }

    /** @param array<string,mixed> $primary @param array<string,mixed> $duplicate @return array<string,mixed> */
    private function mergeChanges(array $primary, array $duplicate): array
    {
        $sources = array_merge($primary['sources'] ?? [], $duplicate['sources'] ?? []);
        $seen = [];
        $mergedSources = [];
        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }
            $url = trim((string) ($source['url'] ?? ''));
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $mergedSources[] = $source;
        }

        $primaryText = $this->combinedText($primary);
        $duplicateText = $this->combinedText($duplicate);
        $winner = mb_strlen($duplicateText) > mb_strlen($primaryText) ? $duplicate : $primary;

        return array_merge($primary, [
            'title' => $winner['title'] ?? $primary['title'],
            'summary' => $winner['summary'] ?? $primary['summary'],
            'briefing' => $winner['briefing'] ?? $primary['briefing'],
            'sources' => $mergedSources,
        ]);
    }

    /** @param array<string,mixed> $candidate */
    private function combinedText(array $candidate): string
    {
        $briefing = is_array($candidate['briefing'] ?? null) ? $candidate['briefing'] : [];
        $highlights = is_array($briefing['highlights'] ?? null) ? $briefing['highlights'] : [];

        return trim(implode(' ', array_filter([
            (string) ($candidate['title'] ?? ''),
            (string) ($candidate['summary'] ?? ''),
            (string) ($briefing['what_changed'] ?? ''),
            (string) ($briefing['why_changed'] ?? ''),
            (string) ($briefing['why_important'] ?? ''),
            (string) ($briefing['future_impact'] ?? ''),
            implode(' ', array_map('strval', $highlights)),
        ])));
    }

    private function matchesExcludedImportance(string $text): bool
    {
        if (preg_match(
            '/(콘서트|공연|가수|아이돌|k-pop|kpop|연예|시상식|grammy|oscar|oscars|셀럽|celebrity|'
            . 'entertainment|드라마|영화|예능|아카데미|billboard|윤종신|'
            . '스포츠|올림픽|월드컵|프리미어리그|premier league|epl|nba|mlb|nfl|'
            . '골프|테니스|메달|우승|패배|감독|선수|축구|야구|농구|e-스포츠|'
            . '축제|지역행사|마을축제|사진전|체육대회|'
            . '홍보|마케팅|광고 캠페인|브랜드 론칭)/iu',
            $text
        ) === 1) {
            return true;
        }

        if (preg_match('/(방문|회담|협의|논의|만찬|오찬|간담회).{0,20}(위해|예정|다녀|나서|개최)/u', $text) === 1) {
            return !$this->hasStructuralImpact($text);
        }

        return false;
    }

    private function matchesIncludedTopic(string $text): bool
    {
        return preg_match(
            '/(정책|규제|법안|금리|제재|외교|무역|협정|gdp|고용|인플레|시장|반도체|ai|에너지|'
            . '안보|군사|기후|탄소|opec|fed|fomc|관세|동결|인상|인하|협상|분쟁|전쟁|휴전|'
            . '반독점|공급망|수출|수입|관세|칩|semiconductor|tariff|sanction)/iu',
            $text
        ) === 1;
    }

    private function hasStructuralImpact(string $text): bool
    {
        return preg_match(
            '/(파급|영향|규제|정책|시장|공급망|안보|무역|금리|성장|제재|협정|법|관세|전쟁|분쟁|'
            . '협상|동결|인상|인하|출시|금지|허가|실시|시행|발효|체결|조사|소송|과징금|'
            // Geopolitical events
            . '공격|attack|미사일|missile|폭격|폭탄|테러|침공|invasion|strike|'
            . '난민|refugee|migrant|이주민|망명|asylum|'
            . '휴전|ceasefire|정전|종전|'
            // Climate/disaster
            . '산불|wildfire|화재|홍수|flood|지진|earthquake|태풍|허리케인|hurricane|'
            . '재난|disaster|대피|evacuation|사망|death|사상자|casualty|'
            // Political/structural
            . '쿠데타|coup|탄핵|impeach|퇴진|사임|resign|선거|election|'
            . '파산|bankrupt|인수|acquisition|합병|merger)/iu',
            $text
        ) === 1;
    }

    /** @param array<string,mixed> $candidate */
    private function importanceFailureDetail(array $candidate): string
    {
        $text = $this->combinedText($candidate);
        if (preg_match('/(콘서트|공연|가수|k-pop|kpop|연예|시상식|윤종신|celebrity|entertainment)/iu', $text) === 1) {
            return 'entertainment_excluded';
        }
        if (preg_match('/(스포츠|올림픽|월드컵|epl|nba|mlb|nfl|우승|선수)/iu', $text) === 1) {
            return 'sports_excluded';
        }
        if (preg_match('/(방문|회담|협의|논의).{0,20}(위해|예정|다녀)/u', $text) === 1) {
            return 'personal_schedule_excluded';
        }

        return 'low_structural_impact';
    }

    /** @param array<string,mixed> $candidate */
    private function completenessFailureDetail(array $candidate): string
    {
        $title = trim((string) ($candidate['title'] ?? ''));
        if (preg_match('/(발표|논의|회담|방문|착수|개막|예정)$/u', $title) === 1) {
            return 'title_vague_no_figure';
        }

        return 'missing_concrete_fact_or_number';
    }

    /** @return list<string> */
    private function titleTokens(string $title): array
    {
        $clean = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title) ?? $title);
        $parts = preg_split('/\s+/u', $clean) ?: [];
        $stop = ['the', 'a', 'an', 'and', 'or', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', '및', '등', '관련', '대한'];
        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part) < 2 || in_array($part, $stop, true)) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    private function normalizeTitle(string $title): string
    {
        $norm = mb_strtolower(trim($title));
        $norm = preg_replace('/\s+/u', '', $norm) ?? $norm;

        return $norm;
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b */
    private function sharePrimarySource(array $a, array $b): bool
    {
        $urlA = trim((string) (($a['sources'][0]['url'] ?? '') ?: ''));
        $urlB = trim((string) (($b['sources'][0]['url'] ?? '') ?: ''));
        if ($urlA === '' || $urlB === '') {
            return false;
        }

        return $urlA === $urlB;
    }
}
