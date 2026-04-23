<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Migration;

use PhpSoftBox\MongoDb\Exception\ConfigurationException;
use PhpSoftBox\MongoDb\Migration\MigrationCreator;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function file_get_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function str_contains;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class MigrationCreatorTest extends TestCase
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
     * Проверяет создание mongo migration файла по имени.
     */
    public function testCreatesMigrationFile(): void
    {
        $creator = new MigrationCreator();

        $path    = $creator->create($this->tmpDir(), 'Create Marketplace Cache');
        $content = file_get_contents($path);

        $this->assertTrue(file_exists($path));
        $this->assertIsString($content);
        $this->assertTrue(str_contains($path, '_create_marketplace_cache.php'));
        $this->assertTrue(str_contains($content, 'extends AbstractMigration'));
    }

    /**
     * Проверяет валидацию некорректного имени миграции.
     */
    public function testRejectsInvalidMigrationName(): void
    {
        $creator = new MigrationCreator();

        $this->expectException(ConfigurationException::class);
        $creator->create($this->tmpDir(), '!!!');
    }

    private function tmpDir(): string
    {
        if ($this->tmpDir !== null) {
            return $this->tmpDir;
        }

        $dir = sys_get_temp_dir() . '/mongo_migration_creator_test_' . uniqid('', true);
        mkdir($dir, 0777, true);
        $this->tmpDir = $dir;

        return $dir;
    }
}
