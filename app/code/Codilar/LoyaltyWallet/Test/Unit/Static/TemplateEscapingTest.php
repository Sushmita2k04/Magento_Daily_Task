<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Unit\Static;

use PHPUnit\Framework\TestCase;

/**
 * Every value printed by a template of this module must be escaped, cast to a number, or be one of the framework's
 * own HTML-producing calls. A new unescaped echo fails this test.
 */
class TemplateEscapingTest extends TestCase
{
    private const SAFE = [
        '/\$escaper->escape(Html|HtmlAttr|Url|Js|Css)\(/',
        '/^\s*\(int\)/',
        '/getBlockHtml\(\'formkey\'\)/',
        '/getChildHtml\(/',
        '/^\s*\$entry->getPoints\(\) > 0 \? \'\+\' : \'\'/',
    ];

    public function testAllPhtmlOutputIsEscaped(): void
    {
        $root = dirname(__DIR__, 3) . '/view';
        $files = new \RegexIterator(
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)),
            '/\.phtml$/'
        );
        $unsafe = [];
        $checked = 0;
        foreach ($files as $file) {
            foreach (file($file->getPathname()) as $n => $line) {
                if (!preg_match_all('/<\?=\s*(.*?)\s*\?>/', $line, $matches)) {
                    continue;
                }
                foreach ($matches[1] as $expression) {
                    $checked++;
                    $ok = false;
                    foreach (self::SAFE as $pattern) {
                        if (preg_match($pattern, $expression)) {
                            $ok = true;
                            break;
                        }
                    }
                    $ok || $unsafe[] = basename($file->getPathname()) . ':' . ($n + 1) . ' ' . $expression;
                }
            }
        }
        $this->assertGreaterThan(20, $checked, 'The scan should have found the module templates');
        $this->assertSame([], $unsafe, "Unescaped template output:\n" . implode("\n", $unsafe));
    }

    public function testKnockoutTemplatesBindTextNeverHtml(): void
    {
        $root = dirname(__DIR__, 3) . '/view/frontend/web/template';
        foreach (glob($root . '/*.html') + glob($root . '/*/*.html') as $file) {
            $this->assertStringNotContainsString('html:', (string)file_get_contents($file), basename($file));
        }
    }
}
