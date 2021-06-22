<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build;

use Punic\DataBuilder\Filesystem;
use Punic\DataBuilder\Traits;
use RuntimeException;

class StateWriter
{
    use Traits\SilentCaller;

    /**
     * @var \Punic\DataBuilder\Filesystem
     */
    protected $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * @param string[] $localeIDs
     */
    public function save(SourceData $sourceData, array $localeIDs, array $files): void
    {
        $path = $sourceData->getOptions()->getStatefilePath();
        if ($path === null) {
            return;
        }
        $state = $this->readState($path);
        $state = $this->updateState($state, $sourceData, $localeIDs, $files);
        $this->writeState($path, $state);
    }

    protected function readState(string $path): array
    {
        if (!is_file($path)) {
            return [
                'formats' => [],
            ];
        }
        $json = $this->filesystem->getFileContents($path);
        [$state, $error] = $this->silentCall(static function () use (&$json) {
            return json_decode($json, true);
        });
        if (!is_array($state)) {
            throw new RuntimeException('Failed to decode the state file ' . str_replace('/', DIRECTORY_SEPARATOR, $state) . ":\n{$error}");
        }
        if (!isset($state['formats'])) {
            $state['formats'] = [];
        } elseif (!is_array($state['formats'])) {
            throw new RuntimeException('Invalid state file: ' . str_replace('/', DIRECTORY_SEPARATOR, $state));
        }
    }

    protected function updateState(array $state, SourceData $sourceData, array $localeIDs, array $files): array
    {
        $formatVersion = $sourceData->getOptions()->getDataFormatVersion();
        if (isset($state['formats'][$formatVersion])) {
            if (!is_array($state['formats'][$formatVersion])) {
                throw new RuntimeException('Invalid state file: ' . str_replace('/', DIRECTORY_SEPARATOR, $state));
            }
        } else {
            $state['formats'][$formatVersion] = [];
        }
        if (isset($state['formats'][$formatVersion]['cldr'])) {
            if (!is_array($state['formats'][$formatVersion]['cldr'])) {
                throw new RuntimeException('Invalid state file: ' . str_replace('/', DIRECTORY_SEPARATOR, $state));
            }
        } else {
            $state['formats'][$formatVersion]['cldr'] = [];
        }
        $cldrVersion = $sourceData->getOptions()->getCldrVersion();
        $state['formats'][$formatVersion]['cldr'][$cldrVersion] = [
            'locales' => $localeIDs,
            'localeFiles' => $files['localeFiles'],
            'supplementalFiles' => $files['supplementalFiles'],
        ];
        if (($files['testFiles'] ?? []) !== []) {
            $state['formats'][$formatVersion]['cldr'][$cldrVersion]['testFiles'] = $files['testFiles'];
        }

        return $state;
    }

    protected function writeState(string $path, array $state): void
    {
        [$json, $error] = $this->silentCall(static function () use (&$state) {
            return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        });
        if ($json === false) {
            throw new RuntimeException("Failed to encode the state file:\n{$error}");
        }
        $this->filesystem->setFileContents($path, $json);
    }
}
