<?php
/**
 * Agent Interface
 * 
 * 모든 Agent가 구현해야 하는 인터페이스
 * OOP 원칙에 따른 추상화 레이어
 * 
 * @package Agents\Core
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Agents\Core;

use Agents\Models\AgentContext;
use Agents\Models\AgentResult;

interface AgentInterface
{
    /**
     * Agent의 주요 처리 로직 실행
     *
     * @param AgentContext $context 처리할 컨텍스트
     * @return AgentResult 처리 결과
     */
    public function process(AgentContext $context): AgentResult;

    /**
     * 입력 데이터 유효성 검증
     *
     * @param mixed $input 검증할 입력 데이터
     * @return bool 유효성 여부
     */
    public function validate(mixed $input): bool;

    /**
     * Agent 이름 반환
     *
     * @return string Agent 식별 이름
     */
    public function getName(): string;

    /**
     * Agent 설정 반환
     *
     * @return array<string, mixed> 설정 배열
     */
    public function getConfig(): array;

    /**
     * Agent 상태 확인
     *
     * @return bool Agent가 정상 작동 가능한지 여부
     */
    public function isReady(): bool;

    /**
     * Agent 초기화
     *
     * @return void
     */
    public function initialize(): void;
}
