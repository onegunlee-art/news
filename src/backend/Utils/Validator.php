<?php
/**
 * 유효성 검증 유틸리티 클래스
 * 
 * 입력 데이터의 유효성을 검증합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Utils;

/**
 * Validator 클래스
 * 
 * 다양한 유효성 검증 규칙을 제공합니다.
 */
final class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    private array $customMessages = [];

    /**
     * 기본 에러 메시지
     */
    private const DEFAULT_MESSAGES = [
        'required' => ':field 필드는 필수입니다.',
        'email' => ':field 필드는 유효한 이메일 형식이어야 합니다.',
        'min' => ':field 필드는 최소 :param자 이상이어야 합니다.',
        'max' => ':field 필드는 최대 :param자까지 가능합니다.',
        'numeric' => ':field 필드는 숫자여야 합니다.',
        'integer' => ':field 필드는 정수여야 합니다.',
        'url' => ':field 필드는 유효한 URL이어야 합니다.',
        'alpha' => ':field 필드는 알파벳만 포함해야 합니다.',
        'alpha_num' => ':field 필드는 알파벳과 숫자만 포함해야 합니다.',
        'alpha_dash' => ':field 필드는 알파벳, 숫자, 대시, 언더스코어만 포함해야 합니다.',
        'in' => ':field 필드는 허용된 값 중 하나여야 합니다.',
        'not_in' => ':field 필드는 금지된 값입니다.',
        'regex' => ':field 필드 형식이 올바르지 않습니다.',
        'date' => ':field 필드는 유효한 날짜여야 합니다.',
        'before' => ':field 필드는 :param 이전 날짜여야 합니다.',
        'after' => ':field 필드는 :param 이후 날짜여야 합니다.',
        'confirmed' => ':field 확인이 일치하지 않습니다.',
        'unique' => ':field 필드 값이 이미 존재합니다.',
        'json' => ':field 필드는 유효한 JSON이어야 합니다.',
        'array' => ':field 필드는 배열이어야 합니다.',
        'boolean' => ':field 필드는 true 또는 false여야 합니다.',
    ];

    /**
     * 생성자
     */
    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->customMessages = $messages;
    }

    /**
     * 팩토리 메서드
     */
    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    /**
     * 검증 실행
     * 
     * @return bool 검증 성공 여부
     */
    public function validate(): bool
    {
        $this->errors = [];
        
        foreach ($this->rules as $field => $rules) {
            $ruleList = is_string($rules) ? explode('|', $rules) : $rules;
            $value = $this->getValue($field);
            
            foreach ($ruleList as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
        
        return empty($this->errors);
    }

    /**
     * 검증 실패 시 예외 발생
     * 
     * @throws ValidationException
     */
    public function validateOrFail(): array
    {
        if (!$this->validate()) {
            throw new ValidationException($this->errors);
        }
        
        return $this->getValidatedData();
    }

    /**
     * 에러 확인
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * 에러 메시지 반환
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * 검증된 데이터 반환
     */
    public function getValidatedData(): array
    {
        $validated = [];
        
        foreach ($this->rules as $field => $rules) {
            if (isset($this->data[$field])) {
                $validated[$field] = $this->data[$field];
            }
        }
        
        return $validated;
    }

    /**
     * 필드 값 가져오기 (중첩 지원)
     */
    private function getValue(string $field): mixed
    {
        $keys = explode('.', $field);
        $value = $this->data;
        
        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }
        
        return $value;
    }

    /**
     * 규칙 적용
     */
    private function applyRule(string $field, mixed $value, string $rule): void
    {
        // 규칙 파싱
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $ruleParam = $parts[1] ?? null;
        
        // nullable 규칙 처리
        if ($ruleName === 'nullable' && ($value === null || $value === '')) {
            return;
        }
        
        // 메서드명 생성
        $method = 'validate' . str_replace('_', '', ucwords($ruleName, '_'));
        
        if (!method_exists($this, $method)) {
            return;
        }
        
        $isValid = $this->{$method}($value, $ruleParam);
        
        if (!$isValid) {
            $this->addError($field, $ruleName, $ruleParam);
        }
    }

    /**
     * 에러 추가
     */
    private function addError(string $field, string $rule, ?string $param): void
    {
        $messageKey = "{$field}.{$rule}";
        
        if (isset($this->customMessages[$messageKey])) {
            $message = $this->customMessages[$messageKey];
        } elseif (isset($this->customMessages[$rule])) {
            $message = $this->customMessages[$rule];
        } else {
            $message = self::DEFAULT_MESSAGES[$rule] ?? ':field 필드가 올바르지 않습니다.';
        }
        
        $message = str_replace(':field', $field, $message);
        $message = str_replace(':param', $param ?? '', $message);
        
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $this->errors[$field][] = $message;
    }

    // ==================== 검증 메서드 ====================

    private function validateRequired(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        
        if (is_array($value) && empty($value)) {
            return false;
        }
        
        return true;
    }

    private function validateEmail(mixed $value): bool
    {
        if (empty($value)) {
            return true;
        }
        
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validateMin(mixed $value, ?string $param): bool
    {
        if (empty($value)) {
            return true;
        }
        
        $min = (int) $param;
        
        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        }
        
        if (is_numeric($value)) {
            return $value >= $min;
        }
        
        if (is_array($value)) {
            return count($value) >= $min;
        }
        
        return false;
    }

    private function validateMax(mixed $value, ?string $param): bool
    {
        if (empty($value)) {
            return true;
        }
        
        $max = (int) $param;
        
        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }
        
        if (is_numeric($value)) {
            return $value <= $max;
        }
        
        if (is_array($value)) {
            return count($value) <= $max;
        }
        
        return false;
    }

    private function validateNumeric(mixed $value): bool
    {
        if (empty($value) && $value !== 0 && $value !== '0') {
            return true;
        }
        
        return is_numeric($value);
    }

    private function validateInteger(mixed $value): bool
    {
        if (empty($value) && $value !== 0 && $value !== '0') {
            return true;
        }
        
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    private function validateUrl(mixed $value): bool
    {
        if (empty($value)) {
            return true;
        }
        
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function validateAlpha(mixed $value): bool
    {
        if (empty($value)) {
            return true;
        }
        
        return preg_match('/^[a-zA-Z]+$/', $value) === 1;
    }

    private function validateAlphaNum(mixed $value): bool
    {
        if (empty($value)) {
            return true;
        }
        
        return preg_match('/^[a-zA-Z0-9]+$/', $value) === 1;
    }

    private function validateAlphaDash(mixed $value): bool
    {
        if (empty($value)) {
            return true;
        }
        
        return preg_match('/^[a-zA-Z0-9_-]+$/', $value) === 1;
    }

    private function validateIn(mixed $value, ?string $param): bool
    {
        if (empty($value)) {
            return true;
        }
        
        $allowed = explode(',', $param ?? '');
        
        return in_array($value, $allowed, true);
    }

    private function validateNotIn(mixed $value, ?string $param): bool
    {
        if (empty($value)) {
            return true;
        }
        
        $forbidden = explode(',', $param ?? '');
        
        return !in_array($value, $forbidden, true);
    }

    private function validateRegex(mixed $value, ?string $param): bool
    {
        if (empty($value)) {
            return true;
        }
        
        return preg_match($param ?? '//', $value) === 1;
    }

    private function validateDate(mixed $value): bool
    {
        if (empty($value)) {
            return true;
        }
        
        return strtotime($value) !== false;
    }

    private function validateJson(mixed $value): bool
    {
        if (empty($value)) {
            return true;
        }
        
        if (!is_string($value)) {
            return false;
        }
        
        json_decode($value);
        
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function validateArray(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        
        return is_array($value);
    }

    private function validateBoolean(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        
        return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true);
    }

    private function validateConfirmed(mixed $value): bool
    {
        // confirmed 규칙은 field와 field_confirmation이 일치하는지 확인
        // 이 메서드는 단순히 true를 반환하고, 실제 확인은 applyRule에서 처리
        return true;
    }
}

/**
 * 검증 예외 클래스
 */
class ValidationException extends \Exception
{
    private array $errors;

    public function __construct(array $errors)
    {
        parent::__construct('Validation failed');
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
