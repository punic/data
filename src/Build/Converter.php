<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build;

use DOMDocument;
use DOMElement;
use LogicException;
use Punic\DataBuilder\Traits;
use RuntimeException;

abstract class Converter
{
    use Traits\SilentCaller;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string[]
     */
    protected $roots;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @param string[] $roots
     */
    public function __construct(string $type, array $roots, ?string $identifier = null)
    {
        $this->type = $type;
        $this->roots = $roots;
        $this->identifier = $identifier ?? end($roots);
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @param string $roots
     * @param string[] $unsetByPath
     *
     * @throws \RuntimeException
     */
    protected function simplify(array $data, array $roots, array $unsetByPath): array
    {
        $path = '';
        foreach ($roots as $root) {
            if (!is_array($data)) {
                throw new RuntimeException("Decoded data should be an array (path: {$path})");
            }
            if (isset($unsetByPath[$path])) {
                foreach ($unsetByPath[$path] as $node) {
                    if (array_key_exists($node, $data)) {
                        unset($data[$node]);
                    }
                }
            }
            $this->checkExactKeys($data, [$root]);
            $data = $data[$root];
            $path .= "/{$root}";
        }
        if (!is_array($data)) {
            throw new RuntimeException("Decoded data should be an array (path: {$path})");
        }

        return $data;
    }

    /**
     * @param array|mixed $node
     * @param string[] $expectedKeys
     *
     * @throws \RuntimeException
     */
    protected function checkExactKeys($node, array $expectedKeys): void
    {
        if (!is_array($node)) {
            throw new RuntimeException("{$node} is not an array");
        }
        $nodeKeys = array_keys($node);
        $missingKeys = array_diff($expectedKeys, $nodeKeys);
        if ($missingKeys !== []) {
            throw new RuntimeException('Missing these node keys: ' . implode(', ', $missingKeys));
        }
        $extraKeys = array_diff($nodeKeys, $expectedKeys);
        if ($extraKeys !== []) {
            throw new RuntimeException('Unexpected node keys: ' . implode(', ', $extraKeys));
        }
    }

    /**
     * @throws \RuntimeException
     */
    protected function loadJson(string $sourceFile): array
    {
        [$json, $error] = $this->silentCall(static function () use ($sourceFile) {
            return file_get_contents($sourceFile);
        });
        if ($json === false) {
            throw new RuntimeException('Failed to read from file ' . str_replace('/', DIRECTORY_SEPARATOR, $sourceFile) . ":\n{$error}");
        }
        [$data, $error] = $this->silentCall(static function () use (&$json) {
            return json_decode($json, true);
        });
        if (!is_array($data)) {
            throw new RuntimeException('Failed to decode JSON data of file ' . str_replace('/', DIRECTORY_SEPARATOR, $sourceFile) . ":\n{$error}");
        }

        return $data;
    }

    /**
     * @throws \RuntimeException
     */
    protected function loadXml(string $sourceFile, array $fallbackData = []): array
    {
        $doc = new DOMDocument();
        [$loaded, $error] = $this->silentCall(static function () use ($doc, $sourceFile) {
            return $doc->load($sourceFile);
        });
        if ($loaded === false) {
            throw new RuntimeException('Failed to read from file ' . str_replace('/', DIRECTORY_SEPARATOR, $sourceFile) . ":\n{$error}");
        }

        return $this->convertDomElement($doc->documentElement, '', $fallbackData);
    }

    protected function getXmlPaths(): array
    {
        throw new LogicException();
    }

    /**
     * @param array|string|null $fallbackData
     *
     * @return array|string
     */
    protected function convertDomElement(DOMElement $element, string $path, $fallbackData = null)
    {
        $paths = $this->getXmlPaths();
        $path .= '/' . $element->tagName;

        $data = $fallbackData;

        foreach ($element->childNodes as $childNode) {
            switch ($childNode->nodeType) {
                case XML_ELEMENT_NODE:
                    $childPath = $path . '/' . $childNode->tagName;
                    if (isset($paths[$childPath])) {
                        $childAttributes = [];
                        $values = [];
                        foreach ($childNode->attributes as $attribute) {
                            $attributeName = (string) $attribute->name;
                            if (in_array($attributeName, $paths[$childPath], true)) {
                                $childAttributes[] = $attribute->value;
                            } else {
                                $values['_' . $attributeName] = $attribute->value;
                            }
                        }
                        $key = implode('-', $childAttributes);
                        if ($childNode->childNodes->length === 0) {
                            $data[$childNode->tagName][$key] = $values;
                        } else {
                            $childFallbackData = $fallbackData[$childNode->tagName][$key] ?? [];
                            $data[$childNode->tagName][$key] = $this->convertDomElement($childNode, $path, $childFallbackData);
                        }
                    } else {
                        $childFallbackData = $fallbackData[$childNode->tagName] ?? [];
                        $data[$childNode->tagName] = $this->convertDomElement($childNode, $path, $childFallbackData);
                    }
                    break;
                case XML_TEXT_NODE:
                    if ($element->childNodes->length === 1) {
                        return $childNode->wholeText;
                    }
                    break;
            }
        }

        return $data;
    }

    /**
     * @param string|mixed $fmt
     *
     * @throws \RuntimeException
     *
     * @return string|mixed
     */
    protected function toPhpSprintf($fmt)
    {
        if (!is_string($fmt)) {
            throw new RuntimeException('Wrong parameter type for ' . get_class($this) . '::' . __METHOD__);
        }

        return preg_replace_callback(
            '/\\{(\\d+)\\}/',
            static function ($matches): string {
                return '%' . (1 + (int) $matches[1]) . '$s';
            },
            str_replace('%', '%%', $fmt)
        );
    }

    protected function clearEmptyArray(array &$array, string $key): void
    {
        if (!is_array($array[$key])) {
            return;
        }
        foreach (array_keys($array[$key]) as $subKey) {
            if (!is_string($subKey) || !is_array($array[$key][$subKey])) {
                continue;
            }
            $this->clearEmptyArray($array[$key], $subKey);
        }
        if ($array[$key] === []) {
            unset($array[$key]);
        }
    }

    protected function asInt(&$value): bool
    {
        switch (gettype($value)) {
            case 'integer':
                return true;
            case 'string':
            case 'double':
                $integer = (int) $value;
                if ((string) $value !== (string) $integer) {
                    return false;
                }
                $value = $integer;
                return true;
        }

        return false;
    }

    protected function asNumber(&$value): bool
    {
        switch (gettype($value)) {
            case 'integer':
            case 'double':
                return true;
            case 'string':
                $number = (int) $value;
                if ((string) $value !== (string) $number) {
                    $number = (float) $value;
                    if ((string) $value !== (string) $number) {
                        return false;
                    }
                }
                $value = $number;
                return true;
        }

        return false;
    }
}
