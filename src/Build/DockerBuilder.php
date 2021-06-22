<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build;

use Punic\DataBuilder\Console\Command\BuildData;
use Punic\DataBuilder\Docker;
use Punic\DataBuilder\Environment;
use Punic\DataBuilder\Traits;

class DockerBuilder
{
    use Traits\Shell;

    /**
     * @var string
     */
    protected const REPOSITORY = 'punicdata';

    /**
     * @var string
     */
    protected const WORKING_DIRECTORY_PATH = '/work';

    /**
     * @var \Punic\DataBuilder\Docker
     */
    protected $docker;

    /**
     * @var \Punic\DataBuilder\Environment
     */
    protected $environment;

    public function __construct(Docker $docker, Environment $environment)
    {
        $this->docker = $docker;
        $this->environment = $environment;
    }

    /**
     * @param string[] $extraArguments
     *
     * @throws \RuntimeException
     */
    public function run(Options $options, array $extraArguments = []): void
    {
        $this->docker->checkEnvironment();
        $dockerImage = $this->docker->ensureImage(static::REPOSITORY, $this->getDockerfileContents(), true, !in_array('-q', $extraArguments, true));
        $commandLineArgs = array_merge($this->getDockerBuildArguments($dockerImage, $options, $extraArguments), $extraArguments);

        $this->shell('docker', $commandLineArgs, true);
    }

    /**
     * @return string[]
     */
    protected function getDockerBuildArguments(Docker\Image $image, Options $options): array
    {
        $dockerMapper = new Docker\PathMapper(static::WORKING_DIRECTORY_PATH);
        $dockerMapper
            ->addLocalDirectory('app', $this->environment->getProjectRootDirectory())
            ->addLocalDirectory('output', $options->getOutputDirectory())
            ->addLocalDirectory('temp', $options->getTemporaryDirectory())
        ;
        if ($options->getStatefilePath() !== null) {
            $dockerMapper->addLocalFile('state-file', $options->getStatefilePath());
        }
        $dockerMap = $dockerMapper->process();
        $result = ['run', '--rm'];
        foreach ($dockerMap->getVolumes() as $mappedPath => $localPath) {
            $result[] = '--volume=' . str_replace('/', DIRECTORY_SEPARATOR, $localPath) . ':' . $mappedPath;
        }
        $result[] = $image->getReference();
        $result[] = $dockerMap->getMappedPath('app') . '/bin/punic-data-builder';
        $result[] = BuildData::NAME;
        $result[] = '--cldr=' . $options->getCldrVersion();
        $result[] = '--libphonenumber=' . $options->getLibphonenumberVersion();
        $result[] = '--locale=' . $options->getLocales()->serialize();
        $result[] = '--draft-status=' . $options->getCldrDraftStatus();
        $result[] = '--output=' . $dockerMap->getMappedPath('output');
        $result[] = '--temp=' . $dockerMap->getMappedPath('temp');
        $result[] = '--state-file=' . ($options->getStatefilePath() === null ? '' : $dockerMap->getMappedPath('state-file'));
        if ($options->isResetCldrData()) {
            $result[] = '--reset-cldr-data';
        }
        if ($options->isResetPunicData()) {
            $result[] = '--reset-punic-data';
        }
        if ($options->isPrettyOutput()) {
            $result[] = '--pretty-output';
        }

        return $result;
    }

    protected function getDockerfileContents(): string
    {
        $workingDirectoryPath = static::WORKING_DIRECTORY_PATH;

        return <<<EOT
FROM alpine:3.13

RUN apk upgrade -U \
    && apk add --update --no-cache \
        git \
        git-lfs \
        openjdk8 \
        apache-ant \
        maven \
        php7 \
        php7-dom \
        php7-iconv \
        php7-intl \
        php7-json \
        php7-openssl \
        php7-simplexml \
    && mkdir -p '{$workingDirectoryPath}'
EOT
        ;
    }
}
