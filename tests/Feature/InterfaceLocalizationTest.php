<?php

use Fayne\PageBuilder\PageBuilderServiceProvider;
use Fayne\PageBuilder\Support\FieldOptionDefinitions;
use Fayne\PageBuilder\Support\FieldSchemaFactory;
use Fayne\PageBuilder\Support\ValidationRuleConfig;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

it('provides matching interface catalogs and the hyphenated Chinese alias', function () {
    $path = dirname(__DIR__, 2).'/resources/lang';
    foreach (glob($path.'/en/*.php') as $file) {
        $name = basename($file);
        $english = require $file;
        $chinese = require $path.'/zh_CN/'.$name;
        $alias = require $path.'/zh-CN/'.$name;
        expect(array_keys($chinese))->toBe(array_keys($english))
            ->and($alias)->toBe($chinese);
        foreach ($english as $key => $value) {
            expect($value)->toBeString()->not->toBe('');
            preg_match_all('/:([a-z_]+)/', $value, $englishParameters);
            preg_match_all('/:([a-z_]+)/', $chinese[$key], $chineseParameters);
            $englishParameters[0] = array_values(array_unique($englishParameters[0]));
            sort($englishParameters[0]);
            $chineseParameters[0] = array_values(array_unique($chineseParameters[0]));
            sort($chineseParameters[0]);
            expect($chineseParameters[0])->toBe($englishParameters[0]);
        }
    }
});

it('loads interface translations during provider registration without changing content language', function () {
    $loader = new FileLoader(app('files'), sys_get_temp_dir().'/unregistered-package-translations');
    app()->instance('translator', new Translator($loader, 'en'));
    $provider = new PageBuilderServiceProvider(app());
    $provider->register();
    $default = config('filament-cms.default_locale');
    foreach (['en' => 'Switch to JSON', 'zh_CN' => '切换到 JSON', 'zh-CN' => '切换到 JSON'] as $locale => $expected) {
        app()->setLocale($locale);
        expect(__('filament-page-builder::ui.switch_to_json'))->toBe($expected)
            ->and(config('filament-cms.default_locale'))->toBe($default);
    }
});

it('localizes definition controls while retaining resolver and option identifiers', function () {
    app()->setLocale('en');
    $english = FieldOptionDefinitions::all()['text'];
    $englishRules = ValidationRuleConfig::catalog();
    app()->setLocale('zh_CN');
    $chinese = FieldOptionDefinitions::all()['text'];
    $chineseRules = ValidationRuleConfig::catalog();
    expect(array_keys($english))->toBe(array_keys($chinese))
        ->and($english['placeholder'][0])->toBe('Placeholder')
        ->and($chinese['placeholder'][0])->toBe('输入提示')
        ->and($english['placeholder'][1])->toBe($chinese['placeholder'][1])
        ->and(array_keys($englishRules))->toBe(array_keys($chineseRules))
        ->and($englishRules['required'])->toBe(['Required', false])
        ->and($chineseRules['required'])->toBe(['必填', false]);
});

it('renders translated visual editor controls and preserves Alpine expressions', function () {
    foreach (['en' => 'Switch to JSON', 'zh_CN' => '切换到 JSON', 'zh-CN' => '切换到 JSON'] as $locale => $label) {
        app()->setLocale($locale);
        $html = view('filament-page-builder::editor-mode-toggle')->render();
        expect($html)->toContain($label, 'advanced = !advanced', ':aria-label="advanced ?')
            ->not->toContain('filament-page-builder::ui.');
    }
});

it('translates validation failures without translating rule identifiers', function () {
    foreach (['en' => 'Enter rule parameters: min', 'zh_CN' => '请填写规则参数：min', 'zh-CN' => '请填写规则参数：min'] as $locale => $message) {
        app()->setLocale($locale);
        expect(fn () => ValidationRuleConfig::decode(['rules' => ['min:']]))
            ->toThrow(InvalidArgumentException::class, $message);
    }
});

it('localizes unsupported field diagnostics in both Chinese locale spellings', function () {
    foreach (['en' => 'Invalid or missing field resolver for: retired', 'zh_CN' => '字段解析器无效或缺失：retired', 'zh-CN' => '字段解析器无效或缺失：retired'] as $locale => $message) {
        app()->setLocale($locale);
        expect(fn () => (new FieldSchemaFactory)->make(['name' => 'value', 'resolver' => 'retired']))
            ->toThrow(InvalidArgumentException::class, $message);
    }
});
