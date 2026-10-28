<?php

namespace Fayne\PageBuilder\Support;

use InvalidArgumentException;

final class ValidationRuleConfig
{
    public static function catalog(): array
    {
        return ['required' => [__('filament-page-builder::ui.required'), false], 'nullable' => [__('filament-page-builder::ui.nullable'), false], 'string' => [__('filament-page-builder::ui.text'), false], 'numeric' => [__('filament-page-builder::ui.number'), false], 'integer' => [__('filament-page-builder::ui.integer'), false], 'boolean' => [__('filament-page-builder::ui.boolean'), false], 'email' => [__('filament-page-builder::ui.email_format'), false], 'url' => [__('filament-page-builder::ui.url_format'), false], 'date' => [__('filament-page-builder::ui.date_format'), false], 'min' => [__('filament-page-builder::ui.minimum_length_value_or_file_size'), true], 'max' => [__('filament-page-builder::ui.maximum_length_value_or_file_size'), true], 'size' => [__('filament-page-builder::ui.exact_length_value_or_file_size'), true], 'between' => [__('filament-page-builder::ui.range_minimum_maximum'), true], 'in' => [__('filament-page-builder::ui.allowed_values_comma_separated'), true], 'not_in' => [__('filament-page-builder::ui.disallowed_values_comma_separated'), true], 'file' => [__('filament-page-builder::ui.file'), false], 'image' => [__('filament-page-builder::ui.image'), false], 'mimes' => [__('filament-page-builder::ui.file_extensions_such_as_jpg_png'), true], 'mimetypes' => [__('filament-page-builder::ui.file_mime_types'), true], 'date_format' => [__('filament-page-builder::ui.date_format_pattern_such_as_y_m_d'), true], 'alpha' => [__('filament-page-builder::ui.letters_only'), false], 'alpha_num' => [__('filament-page-builder::ui.letters_and_numbers_only'), false], 'alpha_dash' => [__('filament-page-builder::ui.letters_numbers_dashes_and_underscores'), false], 'distinct' => [__('filament-page-builder::ui.distinct_values'), false]];
    }

    public static function decode(mixed $value): ?array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new InvalidArgumentException(__('filament-page-builder::ui.invalid_rules_json'));
            }
        }
        if ($value === null) {
            return null;
        }
        if (! is_array($value)) {
            throw new InvalidArgumentException(__('filament-page-builder::ui.rules_must_be_a_configuration_object'));
        }
        foreach (['rules', 'creationRules', 'updateRules'] as $group) {
            $rules = $value[$group] ?? [];
            if (is_string($rules)) {
                if (! preg_match('/(?:^|\|)(?:regex|not_regex):/', $rules)) {
                    $value[$group] = implode('|', array_unique(explode('|', $rules), SORT_STRING));
                }

                continue; // Preserve opaque regular expressions without splitting their pipes.
            }
            if (! is_array($rules) || ! array_is_list($rules)) {
                throw new InvalidArgumentException(__('filament-page-builder::ui.each_rule_group_must_be_a_list_or_a_laravel_rule_string'));
            }
            foreach ($rules as $rule) {
                if (! is_string($rule) || $rule === '') {
                    throw new InvalidArgumentException(__('filament-page-builder::ui.rules_must_be_nonempty_strings'));
                }
                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
                if ((self::catalog()[$name][1] ?? false) && ($parameter === null || trim($parameter) === '')) {
                    throw new InvalidArgumentException(__('filament-page-builder::ui.enter_rule_parameters_label').$name);
                }
                if (in_array($name, ['min', 'max', 'size'], true) && ! is_numeric($parameter)) {
                    throw new InvalidArgumentException(__('filament-page-builder::ui.length_value_or_file_size_parameters_must_be_numeric'));
                }
                if ($name === 'between' && ! preg_match('/^-?\d+(?:\.\d+)?,-?\d+(?:\.\d+)?$/', $parameter ?? '')) {
                    throw new InvalidArgumentException(__('filament-page-builder::ui.range_parameters_must_contain_two_numbers_such_as_1_100'));
                }
            }
            if (is_array($value[$group] ?? null)) {
                $value[$group] = array_values(array_unique($rules, SORT_STRING));
            }
        }

        return $value;
    }
}
