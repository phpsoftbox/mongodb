<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Migration;

use PhpSoftBox\MongoDb\Exception\ConfigurationException;
use PhpSoftBox\MongoDb\Migration\FileMigrationLoader;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function implode;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function var_export;

final class FileMigrationLoaderTest extends TestCase
{
    private ?string $tmpDir = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->tmpDir !== null && is_dir($this->tmpDir)) {
            $files = scandir($this->tmpDir) ?: [];
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                unlink($this->tmpDir . '/' . $file);
            }

            rmdir($this->tmpDir);
        }
    }

    /**
     * Проверяет загрузку и сортировку mongo-миграций из файлов.
     */
    public function testLoadsMigrationFilesInLexicographicalOrder(): void
    {
        $loader = new FileMigrationLoader();
        $this->writeFile(
            '20260422190100_create_cache.php',
            $this->validMigrationBody('20260422190100_create_cache'),
        );
        $this->writeFile(
            '20260422190200_add_indexes.php',
            $this->validMigrationBody('20260422190200_add_indexes'),
        );

        $loaded = $loader->load($this->tmpDir());

        $this->assertCount(2, $loaded);
        $this->assertSame('20260422190100_create_cache', $loaded[0]['id']);
        $this->assertSame('20260422190200_add_indexes', $loaded[1]['id']);
        $this->assertSame('20260422190100_create_cache', $loaded[0]['migration']->version());
        $this->assertSame('20260422190200_add_indexes', $loaded[1]['migration']->version());
    }

    /**
     * Проверяет ошибку для файла с неверным именем миграции.
     */
    public function testRejectsInvalidMigrationFilename(): void
    {
        $loader = new FileMigrationLoader();
        $this->writeFile('bad_name.php', $this->validMigrationBody('bad_name'));

        $this->expectException(ConfigurationException::class);
        $loader->load($this->tmpDir());
    }

    /**
     * Проверяет ошибку, если файл не возвращает MigrationInterface.
     */
    public function testRejectsFileThatDoesNotReturnMigration(): void
    {
        $loader = new FileMigrationLoader();
        $this->writeFile('20260422190300_invalid.php', '<?php return ["invalid"];');

        $this->expectException(ConfigurationException::class);
        $loader->load($this->tmpDir());
    }

    private function tmpDir(): string
    {
        if ($this->tmpDir !== null) {
            return $this->tmpDir;
        }

        $dir = sys_get_temp_dir() . '/mongo_migration_test_' . uniqid('', true);
        mkdir($dir, 0777, true);
        $this->tmpDir = $dir;

        return $dir;
    }

    private function writeFile(string $name, string $body): void
    {
        $path = $this->tmpDir() . '/' . $name;
        file_put_contents($path, $body);
    }

    private function validMigrationBody(string $version): string
    {
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;',
            'use PhpSoftBox\MongoDb\Migration\AbstractMigration;',
            '',
            sprintf('return new class (%s) extends AbstractMigration {', var_export($version, true)),
            '    public function up(MongoConnectionManagerInterface $mongo, string $connection = \'default\'): void',
            '    {',
            '    }',
            '',
            '    public function down(MongoConnectionManagerInterface $mongo, string $connection = \'default\'): void',
            '    {',
            '    }',
            '};',
        ];

        return implode("\n", $lines);
    }
}
