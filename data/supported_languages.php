<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function botoscope_convert_language_code_to_locale($code) {
    $locales = [
        'en' => 'en_US',
        'es' => 'es_ES',
        'pt' => 'pt_PT',
        'ua' => 'uk_UA', // WordPress does not have "ua", but it does have "uk_UA"
        'ru' => 'ru_RU',
        'pl' => 'pl_PL',
        'kz' => 'kk_KZ', // WordPress uses "kk_KZ" instead of "kz"
    ];

    return isset($locales[$code]) ? $locales[$code] : 'en_US'; // Default language
}

return [
    'en' => 'English',
    'es' => 'Español',
    'pt' => 'Português',
    'ua' => 'Українська',
    'ru' => 'Русский',
    'pl' => 'Polski',
    'kz' => 'Қазақ тілі'
];
