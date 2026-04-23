<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Migration;

use DateTimeImmutable;
use PhpSoftBox\MongoDb\Exception\ConfigurationException;

use function file_exists;
use function file_put_contents;
use function is_dir;
use function preg_replace;
use function rtrim;
use function str_replace;
use function strtolower;
use function trim;
use function var_export;

final class MigrationCreator
{
    public function create(string $directory, string $name): string
    {
        $slug      = $this->slug($name);
        $timestamp = new DateTimeImmutable('now')->format('YmdHis');

        $filename = $timestamp . '_' . $slug . '.php';

        $directory = rtrim($directory, '/');
        if (!is_dir($directory)) {
            throw new ConfigurationException('Mongo migrations directory does not exist: ' . $directory);
        }

        $path = $directory . '/' . $filename;
        if (file_exists($path)) {
            throw new ConfigurationException('Mongo migration already exists: ' . $path);
        }

        if (file_put_contents($path, $this->stub($timestamp . '_' . $slug, $name)) === false) {
            throw new ConfigurationException('Failed to write mongo migration file: ' . $path);
        }

        return $path;
    }

    private function slug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';
        $slug = trim($slug, '_');

        if ($slug === '') {
            throw new ConfigurationException('Mongo migration name must contain letters or digits.');
        }

        return $slug;
    }

    private function stub(string $version, string $description): string
    {
        $versionValue     = var_export($version, true);
        $descriptionValue = var_export($description, true);

        return str_replace(
            ['%VERSION%', '%DESCRIPTION%'],
            [$versionValue, $descriptionValue],
            <<<'PHP'
<?php

declare(strict_types=1);

use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;
use PhpSoftBox\MongoDb\Migration\AbstractMigration;

return new class (
    %VERSION%,
    %DESCRIPTION%,
) extends AbstractMigration {
    public function up(MongoConnectionManagerInterface $mongo, string $connection = 'default'): void
    {
        // TODO: описать применение миграции
    }

    public function down(MongoConnectionManagerInterface $mongo, string $connection = 'default'): void
    {
        // TODO: описать откат миграции
    }
};
PHP,
        );
    }
}
