<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Component\DataLoader\Traits;

use Klipper\Component\DataLoader\Exception\InvalidArgumentException;
use Klipper\Component\DataLoader\Exception\RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Yaml;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
trait YamlLoaderTrait
{
    protected ?YamlParser $yamlParser = null;

    public function supports($resource): bool
    {
        return \is_string($resource)
            && \in_array(pathinfo($resource, PATHINFO_EXTENSION), ['yml', 'yaml'], true);
    }

    protected function loadContent($resource): array
    {
        if (!class_exists(YamlParser::class)) {
            throw new RuntimeException('Unable to load YAML config files as the Symfony Yaml Component is not installed.');
        }

        if (!stream_is_local($resource)) {
            throw new InvalidArgumentException(sprintf('This is not a local file "%s".', $resource));
        }

        if (!file_exists($resource)) {
            throw new InvalidArgumentException(sprintf('The file "%s" is not valid.', $resource));
        }

        if (null === $this->yamlParser) {
            $this->yamlParser = new YamlParser();
        }

        try {
            $configuration = $this->yamlParser->parse(file_get_contents($resource), Yaml::PARSE_CONSTANT);
        } catch (ParseException $e) {
            throw new InvalidArgumentException(sprintf('The file "%s" does not contain valid YAML.', $resource), 0, $e);
        }

        return $this->validate($configuration, $resource);
    }

    /**
     * Validates the content file.
     *
     * @param mixed $content
     *
     * @throws InvalidArgumentException When file is not valid
     */
    private function validate($content, string $file): array
    {
        if (!\is_array($content)) {
            throw new InvalidArgumentException(sprintf('The file "%s" is not valid. It should contain an array. Check your YAML syntax.', $file));
        }

        return $content;
    }
}
