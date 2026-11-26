<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Support\Config;

/** A broken secrets.php must give a clear message, never a blank page, and never echo secrets. */
final class ConfigTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sc-config-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents($this->dir . '/app.php', "<?php return ['db' => ['name' => '']];");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    private function load(string $secrets): Config
    {
        file_put_contents($this->dir . '/secrets.php', $secrets);

        return Config::load($this->dir . '/app.php', $this->dir . '/secrets.php');
    }

    public function testGoodSecretsAreMerged(): void
    {
        $c = $this->load("<?php\nreturn ['db' => ['name' => 'if0_1_sc']];\n");
        self::assertSame('', $c->secretsError);
        self::assertSame('if0_1_sc', $c->string('db.name'));
    }

    public function testCurlyQuotesFromAnIpadAreExplained(): void
    {
        $c = $this->load("<?php\nreturn [\n  'db' => [\n    ‘password’ => ‘TopSecret99’,\n  ],\n];\n");
        self::assertStringContainsString('line 4', $c->secretsError);
        self::assertStringContainsString('curly quotes', $c->secretsError);
        self::assertStringContainsString('Smart Punctuation', $c->secretsError);
        self::assertStringNotContainsString('TopSecret99', $c->secretsError);
    }

    public function testMissingCommaIsExplained(): void
    {
        $c = $this->load("<?php\nreturn [\n  'db' => ['name' => 'a' 'user' => 'b'],\n];\n");
        self::assertStringContainsString('line 3', $c->secretsError);
        self::assertStringContainsString('missing comma', $c->secretsError);
    }

    public function testFileThatDoesNotReturnSettings(): void
    {
        $c = $this->load("db name = abc\n");
        self::assertStringContainsString('must start with <?php', $c->secretsError);
        self::assertStringNotContainsString('abc', $c->secretsError);
    }
}
