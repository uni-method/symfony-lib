<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Container\ContainerInterface;
use Throwable;
use UniMethod\Frame\Config\{ActionType, Config};
use UniMethod\Frame\Persistence\EntityCollectionInterface;
use UniMethod\SymfonyLib\Config\ConfigChecker;

/**
 * Checks API configs without HTTP requests, on the same configuration errors
 * that would otherwise give a 500 on the first request. Services of the impl dictionaries are checked
 * already when the container is built (RegisterApisPass), here — the rest:
 *
 * 1. load     — loading and validating the config;
 * 2. services — repositories of list actions, assembling ApiService;
 * 3. impl     — static reconciliation of impl with classes (ConfigChecker).
 *
 * Exit code 1 if at least one error is found — for CI.
 *
 * Проверяет конфиги API без HTTP-запросов, на тех же ошибках конфигурации,
 * которые иначе дают 500 на первом запросе. Сервисы словарей impl проверяются
 * уже при сборке контейнера (RegisterApisPass), здесь — остальное:
 *
 * 1. load     — загрузка и валидация конфига;
 * 2. services — репозитории list-действий, сборка ApiService;
 * 3. impl     — статическая сверка impl с классами (ConfigChecker).
 *
 * Код выхода 1, если найдена хотя бы одна ошибка, — для CI.
 */
#[AsCommand(
    name: "symfony-lib:config:validate",
    description: "Validates API configs: loading, service wiring and impl against classes",
)]
final class ValidateConfigCommand extends Command
{
    /**
     * @param ContainerInterface $configs ServiceLocator: API name → Config
     * @param ContainerInterface $apiServices ServiceLocator: API name → ApiService
     * @param list<string> $apiNames
     * @param iterable<object> $repositories
     */
    public function __construct(
        private readonly ContainerInterface $configs,
        private readonly ContainerInterface $apiServices,
        private readonly ConfigChecker $checker,
        private readonly array $apiNames,
        private readonly iterable $repositories,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            "api",
            InputArgument::OPTIONAL,
            "API name from symfony_lib.apis; all APIs if omitted",
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string|null $api */
        $api = $input->getArgument("api");

        if ($api !== null && !in_array($api, $this->apiNames, true)) {
            $io->error(
                sprintf(
                    "Unknown API '%s'. Configured: %s",
                    $api,
                    $this->apiNames === [] ? "none" : implode(", ", $this->apiNames),
                ),
            );

            return Command::INVALID;
        }

        $names = $api !== null ? [$api] : $this->apiNames;

        if ($names === []) {
            $io->warning("No APIs configured in symfony_lib.apis");

            return Command::SUCCESS;
        }

        $failed = false;

        foreach ($names as $name) {
            $io->section(sprintf("API '%s'", $name));

            $issues = $this->validate($name);

            if ($issues === []) {
                $io->writeln("<info>OK</info>");
                continue;
            }

            $failed = true;
            $io->listing($issues);
        }

        if ($failed) {
            $io->error("Configuration errors found");

            return Command::FAILURE;
        }

        $io->success("All API configs are valid");

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function validate(string $name): array
    {
        try {
            $config = $this->configs->get($name);
        } catch (Throwable $throwable) {
            // Without a loaded config there is nothing to check the other levels with
            //
            // Без загруженного конфига остальные уровни проверить нечем
            return ["[load] " . $this->describe($throwable)];
        }

        if (!$config instanceof Config) {
            return [
                sprintf(
                    "[load] Service symfony_lib.config.%s is %s, expected %s",
                    $name,
                    get_debug_type($config),
                    Config::class,
                ),
            ];
        }

        $issues = [];

        foreach ($this->missingRepositories($config) as $issue) {
            $issues[] = "[services] " . $issue;
        }

        // ApiService is assembled only after the repositories are fine:
        // otherwise it would fail on the first of the errors already listed
        //
        // ApiService собирается только после того, как репозитории в порядке:
        // иначе он упадёт на первой из уже перечисленных ошибок
        if ($issues === []) {
            try {
                $this->apiServices->get($name);
            } catch (Throwable $throwable) {
                $issues[] = "[services] " . $this->describe($throwable);
            }
        }

        foreach ($this->checker->check($config) as $issue) {
            $issues[] = "[impl] " . $issue;
        }

        return $issues;
    }

    /**
     * The repository of a list action must be a service implementing
     * EntityCollectionInterface — otherwise ApiService will not receive it.
     *
     * Репозиторий list-действия должен быть сервисом, реализующим
     * EntityCollectionInterface, — иначе ApiService его не получит.
     *
     * @return list<string>
     */
    private function missingRepositories(Config $config): array
    {
        $available = [];
        foreach ($this->repositories as $repository) {
            if ($repository instanceof EntityCollectionInterface) {
                $available[] = $repository;
            }
        }

        $issues = [];

        foreach ($config->actions as $action) {
            $repository = $action->config?->repository;

            if (
                $action->type !== ActionType::List ||
                $repository === null
            ) {
                continue;
            }

            foreach ($available as $service) {
                // instanceof rather than a name comparison: the service may be
                // a lazy proxy extending the repository class
                //
                // instanceof, а не сравнение имён: сервис может быть
                // lazy-прокси, наследующим класс репозитория
                if ($service instanceof $repository) {
                    continue 2;
                }
            }

            $issues[] = sprintf(
                "List action of model '%s': repository %s is not a service implementing %s",
                $action->model,
                $repository,
                EntityCollectionInterface::class,
            );
        }

        return $issues;
    }

    private function describe(Throwable $throwable): string
    {
        return sprintf("%s: %s", $throwable::class, $throwable->getMessage());
    }
}
