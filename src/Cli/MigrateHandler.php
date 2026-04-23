<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;
use PhpSoftBox\MongoDb\Migration\FileMigrationLoader;
use PhpSoftBox\MongoDb\Migration\MigrationInterface;
use PhpSoftBox\MongoDb\Migration\MigrationsConfig;
use PhpSoftBox\MongoDb\Migration\Migrator;
use PhpSoftBox\MongoDb\Migration\MongoMigrationStateStore;
use Throwable;

use function count;
use function getcwd;
use function is_dir;
use function is_string;
use function rtrim;
use function str_starts_with;

final class MigrateHandler implements HandlerInterface
{
    public function __construct(
        private readonly MongoConnectionManagerInterface $mongo,
        private readonly MigrationsConfig $config,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $connectionName = $this->resolveConnection($runner);
        if ($connectionName === null) {
            $runner->io()->writeln('Некорректное имя подключения.', 'error');

            return Response::FAILURE;
        }

        try {
            $paths = $this->resolvePaths($runner, $connectionName);
        } catch (Throwable $exception) {
            $runner->io()->writeln($exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        if ($paths === []) {
            $runner->io()->writeln('Не найдены директории mongo-миграций.', 'error');

            return Response::FAILURE;
        }

        $migrations = $this->loadMigrations($runner, $paths);
        if ($migrations === null) {
            return Response::FAILURE;
        }

        $migrator = new Migrator($this->mongo, new MongoMigrationStateStore($this->mongo));

        try {
            $applied = $migrator->migrate($migrations, $connectionName);
        } catch (Throwable $exception) {
            $runner->io()->writeln('Ошибка миграции: ' . $exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        $runner->io()->writeln('Количество примененных миграций: ' . (string) count($applied) . '.', 'success');
        foreach ($applied as $id) {
            $runner->io()->writeln(' - ' . $id, 'info');
        }

        return Response::SUCCESS;
    }

    private function resolveConnection(RunnerInterface $runner): ?string
    {
        $connectionName = $runner->request()->option('connection');
        if (is_string($connectionName) && $connectionName !== '') {
            return $connectionName;
        }

        return $this->config->defaultConnection();
    }

    /**
     * @return list<string>
     */
    private function resolvePaths(RunnerInterface $runner, string $connectionName): array
    {
        $basePaths = $this->config->paths($connectionName);

        $relative = $runner->request()->option('path');
        if ($relative !== null && (!is_string($relative) || $relative === '')) {
            return [];
        }

        $paths = [];
        foreach ($basePaths as $basePath) {
            $path = $basePath;
            if (is_string($relative) && $relative !== '') {
                if (str_starts_with($relative, '/')) {
                    return [];
                }

                $path = rtrim($basePath, '/') . '/' . $relative;
            }

            $resolved = $this->normalizePath($path);
            if ($resolved !== null) {
                $paths[] = $resolved;
            }
        }

        return $paths;
    }

    /**
     * @param list<string> $paths
     * @return list<MigrationInterface>|null
     */
    private function loadMigrations(RunnerInterface $runner, array $paths): ?array
    {
        $loader = new FileMigrationLoader();
        $known  = [];
        $out    = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                $runner->io()->writeln('Директория миграций не найдена: ' . $path, 'error');

                return null;
            }

            foreach ($loader->load($path, recursive: false) as $item) {
                if (isset($known[$item['id']])) {
                    $runner->io()->writeln('Дублирующаяся миграция: ' . $item['id'], 'error');

                    return null;
                }

                $known[$item['id']] = true;
                $out[]              = $item['migration'];
            }
        }

        return $out;
    }

    private function normalizePath(mixed $path): ?string
    {
        if (!is_string($path) || $path === '') {
            return null;
        }

        $path = rtrim($path, '/');
        if ($path === '') {
            return null;
        }

        if (!str_starts_with($path, '/')) {
            $cwd = getcwd();
            if ($cwd !== false) {
                $path = rtrim($cwd, '/') . '/' . $path;
            }
        }

        return $path;
    }
}
