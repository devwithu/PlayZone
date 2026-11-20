<?php

/*
 * 한국어 검증 메시지 (자주 쓰는 규칙만). 관리자 화면(Filament)처럼 메시지를 따로 정하지 않은 검증에 쓰인다.
 * 웹·API 요청 클래스에서 직접 정한 메시지가 있으면 그쪽이 우선한다. 없는 규칙은 영어(fallback)로 나온다.
 */
return [
    'accepted' => ':attribute에 동의해야 합니다.',
    'after_or_equal' => ':attribute은(는) :date 이후 날짜여야 합니다.',
    'array' => ':attribute 형식이 올바르지 않습니다.',
    'before_or_equal' => ':attribute은(는) :date 이전 날짜여야 합니다.',
    'boolean' => ':attribute 값이 올바르지 않습니다.',
    'date' => ':attribute은(는) 올바른 날짜가 아닙니다.',
    'distinct' => ':attribute에 중복된 값이 있습니다.',
    'email' => ':attribute 형식이 올바르지 않습니다.',
    'enum' => '선택한 :attribute이(가) 올바르지 않습니다.',
    'exists' => '선택한 :attribute이(가) 올바르지 않습니다.',
    'file' => ':attribute은(는) 파일이어야 합니다.',
    'image' => ':attribute은(는) 이미지 파일이어야 합니다.',
    'in' => '선택한 :attribute이(가) 올바르지 않습니다.',
    'integer' => ':attribute은(는) 정수여야 합니다.',
    'max' => [
        'array' => ':attribute은(는) :max개 이하여야 합니다.',
        'file' => ':attribute은(는) :maxKB 이하여야 합니다.',
        'numeric' => ':attribute은(는) :max 이하여야 합니다.',
        'string' => ':attribute은(는) :max자 이하여야 합니다.',
    ],
    'mimes' => ':attribute은(는) :values 형식이어야 합니다.',
    'mimetypes' => ':attribute은(는) :values 형식이어야 합니다.',
    'min' => [
        'array' => ':attribute은(는) :min개 이상이어야 합니다.',
        'file' => ':attribute은(는) :minKB 이상이어야 합니다.',
        'numeric' => ':attribute은(는) :min 이상이어야 합니다.',
        'string' => ':attribute은(는) :min자 이상이어야 합니다.',
    ],
    'numeric' => ':attribute은(는) 숫자여야 합니다.',
    'regex' => ':attribute 형식이 올바르지 않습니다.',
    'required' => ':attribute을(를) 입력해 주세요.',
    'required_if' => ':other이(가) :value이면 :attribute을(를) 입력해 주세요.',
    'required_unless' => ':other이(가) :values이(가) 아니면 :attribute을(를) 입력해 주세요.',
    'string' => ':attribute은(는) 문자열이어야 합니다.',
    'unique' => '이미 사용 중인 :attribute입니다.',
    'uploaded' => ':attribute을(를) 업로드하지 못했습니다.',

    'custom' => [],

    'attributes' => [],
];
