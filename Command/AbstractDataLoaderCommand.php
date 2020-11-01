<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Component\DataLoader\Command;

use Klipper\Component\DataLoader\DataLoaderInterface;
use Klipper\Component\DataLoader\Exception\ConsoleResourceException;
use Klipper\Component\DataLoader\Exception\InvalidArgumentException;
use Klipper\Component\DataLoader\StateableDataLoaderInterface;
use Klipper\Component\Resource\Domain\DomainManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Abstract command to build a command to use a data loader.
 *
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
abstract class AbstractDataLoaderCommand extends Command
{
    protected DomainManagerInterface $domainManager;

    protected string $projectDir;

    protected array $bundles;

    public function __construct(DomainManagerInterface $domainManager, string $projectDir, array $bundles)
    {
        parent::__construct();

        $this->domainManager = $domainManager;
        $this->projectDir = $projectDir;
        $this->bundles = $bundles;
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $finder = (new Finder())
            ->ignoreVCS(true)
            ->in($this->getBundlePaths())
            ->name($this->getFindFileNames())
        ;

        if (0 === $finder->count()) {
            $output->writeln('  '.$this->getEmptyMessage().' in "<project_dir>/config/data" or "<bundle>/Resources/config/data"');

            return 0;
        }

        $loader = $this->getDataLoader();
        $updated = false;
        $errorRes = null;

        foreach ($finder->files() as $file) {
            $filename = $file->getPathname();

            if (!$loader->supports($filename)) {
                throw new InvalidArgumentException(sprintf(
                    'The resource "%s" is not supported by this data loader',
                    $filename
                ));
            }

            $res = $loader->load($filename);

            if ($res->hasErrors() && null === $errorRes) {
                $errorRes = $res;
            }

            $updated = $updated || ($loader instanceof StateableDataLoaderInterface ? $loader->isEdited() : true);
        }

        if (null !== $errorRes) {
            throw new ConsoleResourceException($errorRes);
        }

        if ($updated) {
            $output->writeln('  '.$this->getInitializedMessage());
        } else {
            $output->writeln('  '.$this->getUpToDateMessage());
        }

        return 0;
    }

    /**
     * @return string[]
     */
    protected function getBundlePaths(): array
    {
        $paths = [];

        foreach ($this->bundles as $bundle) {
            $ref = new \ReflectionClass($bundle);
            $path = \dirname($ref->getFileName()).'/Resources/config/data';

            if (is_dir($path)) {
                $paths[] = $path;
            }
        }

        $paths[] = $this->projectDir.'/config/data';

        return $paths;
    }

    abstract protected function getDataLoader(): DataLoaderInterface;

    /**
     * @return string[]
     */
    abstract protected function getFindFileNames(): array;

    abstract protected function getEmptyMessage(): string;

    abstract protected function getInitializedMessage(): string;

    abstract protected function getUpToDateMessage(): string;
}
