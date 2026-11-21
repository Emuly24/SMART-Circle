<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Data\Migrator;
use SmartCircle\Support\Config;

final class SupportTest extends TestCase
{
    public function testConfigMergesSecretsOverDefaults(): void
    {
        $dir = sys_get_temp_dir() . '/sc-config-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents("$dir/app.php", "<?php return ['db' => ['host' => 'localhost', 'port' => 3306], 'app' => ['debug' => false]];");
        file_put_contents("$dir/secrets.php", "<?php return ['db' => ['host' => 'sql.example', 'password' => 's3cret']];");

        $config = Config::load("$dir/app.php", "$dir/secrets.php");

        self::assertSame('sql.example', $config->string('db.host'));
        self::assertSame(3306, $config->int('db.port'));
        self::assertSame('s3cret', $config->string('db.password'));
        self::assertFalse($config->bool('app.debug'));
        self::assertSame('fallback', $config->string('missing.key', 'fallback'));

        array_map('unlink', glob("$dir/*") ?: []);
        rmdir($dir);
    }

    public function testConfigWorksWithoutSecretsFile(): void
    {
        $config = Config::load(dirname(__DIR__, 2) . '/config/app.php', '/nonexistent/secrets.php');

        self::assertSame('SMART Circle', $config->string('app.name'));
    }

    public function testMigrationSplitterIgnoresCommentsAndBlankStatements(): void
    {
        $sql = "-- comment line\nCREATE TABLE a (id INT);\n\n-- another\nCREATE TABLE b (\n  id INT\n);\n";

        self::assertSame(['CREATE TABLE a (id INT)', "CREATE TABLE b (\n  id INT\n)"], Migrator::split($sql));
    }

    public function testCoreMigrationSplitsIntoItsTables(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database/migrations/0001_core.sql');
        $statements = Migrator::split($sql);

        self::assertCount(5, $statements);
        foreach ($statements as $statement) {
            self::assertStringStartsWith('CREATE TABLE', $statement);
        }
    }

    public function testTemplatesCanUseVariableNamesTheRendererUsesInternally(): void
    {
        $dir = sys_get_temp_dir() . '/sc-view-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents("$dir/t.php", '<?= e($data["name"]) ?>|<?= e($file) ?>|<?= e($template) ?>');
        $view = new \SmartCircle\Presentation\View\View($dir);

        self::assertSame('Chikondi|f|x', $view->render('t', ['data' => ['name' => 'Chikondi'], 'file' => 'f', 'template' => 'x'], null));

        unlink("$dir/t.php");
        rmdir($dir);
    }

    public function testEscapeHelperNeutralisesHtml(): void
    {
        self::assertSame('&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;', e("<script>alert('x')</script>"));
        self::assertSame('Tom &amp; Jerry &quot;quoted&quot;', e('Tom & Jerry "quoted"'));
        self::assertSame('', e(null));
        self::assertSame('42', e(42));
    }
}
