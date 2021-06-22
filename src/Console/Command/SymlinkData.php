<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Console\Command;

use InvalidArgumentException;
use Punic\DataBuilder\Console\Command;
use Punic\DataBuilder\DataSymlinker;
use Punic\DataBuilder\Environment;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SymlinkData extends Command
{
    private const OPERATION_COMPACT = 'compact';

    private const OPERATION_EXPAND = 'expand';

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $environment = $this->container->make(Environment::class);

        $this
            ->setName('data:symlink')
            ->setDescription('Expand or compact Punic data')
            ->addArgument('operation', InputArgument::REQUIRED, 'The operation to be performed (' . implode(' or ', $this->getValidOperations()) . ')')
            ->addOption('data-path', 'd', InputOption::VALUE_REQUIRED, 'The path to the data directory', str_replace('/', DIRECTORY_SEPARATOR, $environment->getDefaultDataDirectory()))
            ->setHelp(
                <<<'EOT'
You may want to create symbolic links for data files.
This is particular useful to save disk space, since many data files for different languages are the same.

Using symlinks is *REQUIRED* when we push data files to https://github.com/punic/data
EOT
            )
        ;
    }

    /**
     * @return string
     */
    protected function getValidOperations(): array
    {
        return [self::OPERATION_COMPACT, self::OPERATION_EXPAND];
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symlinker = $this->container->make(DataSymlinker::class, ['output' => $output]);
        /** @var DataSymlinker $symlinker */
        $operation = $input->getArgument('operation');
        try {
            switch ($operation) {
                case self::OPERATION_COMPACT:
                    $symlinker->compact($input->getOption('data-path'));
                    break;
                case self::OPERATION_EXPAND:
                    $symlinker->expand($input->getOption('data-path'));
                    break;
                default:
                    $validOperations = $this->getValidOperations();
                    $output->write("<error>'{$operation}' is not a valid operation.\nValid operations are:\n- " . implode("\n- ", $validOperations) . '</error>');
                    return $this::INVALID;
            }
        } catch (InvalidArgumentException $x) {
            $output->write("<error>{$x->getMessage()}</error>");
            return $this::INVALID;
        }

        return $this::SUCCESS;
    }
}
