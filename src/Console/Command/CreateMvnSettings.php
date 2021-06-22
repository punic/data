<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Console\Command;

use InvalidArgumentException;
use Punic\DataBuilder\Console\Command;
use Punic\DataBuilder\Environment;
use Punic\DataBuilder\Filesystem;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class CreateMvnSettings extends Command
{
    /**
     * @var string
     */
    public const NAME = 'mvn:configure';

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this
            ->setName(static::NAME)
            ->setDescription('Create the mvn settings')
            ->addArgument('username', InputArgument::REQUIRED, 'Your GitHub username')
            ->addArgument('token', InputArgument::OPTIONAL, 'Your GitHub personal access token')
            ->setHelp(
                <<<'EOT'
In order to parse the source Unicode CLDR data we need to read some public GitHub repositories.
As described at http://cldr.unicode.org/development/maven you need GitHub authentication.

If you don't have a GitHub account, you can create it for free - see
http://cldr.unicode.org/development/maven#TOC-Getting-Started---GitHub-token

If you don't have a GitHub personal access token, you can create it at:
https://github.com/settings/tokens
EOT
            )
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = trim((string) $input->getArgument('username'));
        if ($username === '') {
            $output->write('<error>The GitHub username can NOT be empty</error>');
            return $this::INVALID;
        }
        $token = $input->getArgument('token');
        if ($token !== null) {
            $token = trim($token);
            if ($token === '') {
                $output->write('<error>The GitHub personal access token can NOT be empty</error>');
                return $this::INVALID;
            }
        } else {
            $token = $this->askToken($input, $output);
        }

        return $this->writeMvnSettings($output, $this->buildXml($username, $token));
    }

    private function askToken(InputInterface $input, OutputInterface $output): string
    {
        $question = new Question('Your GitHub personal access token [hidden]: ');
        $question
            ->setHidden(true)
            ->setNormalizer(static function (?string $token): string {
                return $token === null ? '' : trim($token);
            })
            ->setValidator(static function (string $token): string {
                if ($token === '') {
                    throw new InvalidArgumentException('The GitHub personal access token can NOT be empty');
                }
                return $token;
            })
        ;

        return $this->getHelper('question')->ask($input, $output, $question);
    }

    private function buildXml(string $username, string $token): string
    {
        $xmlUsername = htmlspecialchars($username, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $xmlToken = htmlspecialchars($token, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return <<<EOT
<settings
    xmlns="http://maven.apache.org/SETTINGS/1.0.0"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://maven.apache.org/SETTINGS/1.0.0 http://maven.apache.org/xsd/settings-1.0.0.xsd"
>
    <servers>
        <!-- needed for building CLDR -->
        <server>
            <id>githubicu</id>
            <username>{$xmlUsername}</username>
            <password>{$xmlToken}</password>
        </server>
    </servers>
</settings>
EOT
        ;
    }

    private function writeMvnSettings(OutputInterface $output, string $xml): int
    {
        $filename = $this->container->make(Environment::class)->getMvnSettingsFilePath();
        $filesystem = $this->container->make(Filesystem::class);
        // @var Filesystem $filesystem
        try {
            $filesystem->setFileContents($filename, $xml);
        } catch (RuntimeException $x) {
            $output->writeln("<error>{$x->getMessage()}</error>");
            return $this::FAILURE;
        }
        if (!$output->isQuiet()) {
            $output->write('<info>mvn settings have been saved to ' . str_replace('/', DIRECTORY_SEPARATOR, $filename) . '</info>');
        }

        return $this::SUCCESS;
    }
}
