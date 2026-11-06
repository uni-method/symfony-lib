<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Config;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use UniMethod\Frame\Config\{AbstractConfigLoader, Config};
use UniMethod\Frame\Validator\Exception\{
    InvalidConfigStructureException,
    MissingRequiredFieldException,
};

final class YamlConfigLoader extends AbstractConfigLoader
{
    /**
     * @throws InvalidYamlException|InvalidConfigStructureException|MissingRequiredFieldException
     */
    public function load(string $filePath): Config
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new InvalidYamlException(
                sprintf("Unable to read file '%s'", $filePath),
            );
        }

        return $this->loadFromString($content);
    }

    /**
     * @throws InvalidYamlException|InvalidConfigStructureException|MissingRequiredFieldException
     */
    public function loadFromString(string $content): Config
    {
        try {
            $data = Yaml::parse($content);
        } catch (ParseException $parseException) {
            throw new InvalidYamlException("Failed to parse YAML: " . $parseException->getMessage(), $parseException);
        }

        if (!is_array($data)) {
            throw new InvalidConfigStructureException(
                "Root element must be an object",
            );
        }

        // Check that this is not a list (as in JSON it is checked via stdClass)
        //
        // Проверяем, что это не список (как в JSON проверяется через stdClass)
        if (array_is_list($data)) {
            throw new InvalidConfigStructureException(
                "Root element must be an object",
            );
        }

        return $this->createConfig($data);
    }
}
