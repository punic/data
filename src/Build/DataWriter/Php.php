<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\DataWriter;

use Punic\DataBuilder\Build\DataWriter;
use Punic\DataBuilder\Filesystem;
use RuntimeException;

class Php implements DataWriter
{
    public const FLAG_PRETTYOUTPUT = 0b00000001;

    /**
     * @var int
     */
    protected const TAB_WIDTH = 4;

    /**
     * @var int
     */
    protected const INT32_MIN = -2147483648; // -1 * (2 ^ (32 - 1))

    /**
     * @var int
     */
    protected const INT32_MAX = 2147483647; // 2 ^ (32 - 1) - 1

    /**
     * @var \Punic\DataBuilder\Filesystem
     */
    protected $filesystem;

    /**
     * @var int
     */
    private $flags;

    public function __construct(int $flags, Filesystem $filesystem)
    {
        $this->flags = $flags;
        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\DataWriter::save()
     */
    public function save(array $data, string $file): void
    {
        $php = "<?php\n// This file is auto-generated. Do not edit!\nreturn " . $this->encode($data) . ";\n";
        $this->filesystem->setFileContents($file, $php);
    }

    protected function getFlags(): int
    {
        return $this->flags;
    }

    protected function hasFlag(int $flag): bool
    {
        return ($this->getFlags() & $flag) === $flag;
    }

    /**
     * @throws \RuntimeException
     */
    protected function encode($data, int $indent = 0, bool $isArrayKey = false): string
    {
        $type = gettype($data);
        switch ($type) {
            case 'boolean':
                return $data ? 'true' : 'false';
            case 'string':
                return "'" . addcslashes($data, "'\\") . "'";
                break;
            case 'NULL':
                return 'null';
                break;
            case 'double':
                $string = (string) $data;
                if ($isArrayKey) {
                    $integer = (int) $data;
                    if ($string === (string) $integer && (PHP_INT_SIZE <= 4 || ($integer >= static::INT32_MIN && $integer <= static::INT32_MAX))) {
                        return $string;
                    }
                    return "'{$string}'";
                }
                return $string;
            case 'integer':
                $string = (string) $data;
                if ($isArrayKey && PHP_INT_SIZE > 4 && ($data < static::INT32_MIN || $data > static::INT32_MAX)) {
                    return "'{$string}'";
                }
                return $string;
            case 'array':
                $prettyOutput = $this->hasFlag(static::FLAG_PRETTYOUTPUT);
                $space = $prettyOutput ? ' ' : '';
                $result = 'array(';
                $index = 0;
                $assoc = array_keys($data) !== range(0, count($data) - 1);
                foreach ($data as $key => $value) {
                    if ($index++ !== 0) {
                        $result .= ',';
                    }
                    if ($prettyOutput) {
                        $result .= "\n" . str_repeat($space, $indent + static::TAB_WIDTH);
                    }
                    if ($assoc) {
                        $result .= $this->encode($key, 0, true) . $space . '=>' . $space;
                    }
                    $result .= $this->encode($value, $indent + static::TAB_WIDTH);
                }
                if ($prettyOutput) {
                    $result .= "\n" . str_repeat($space, $indent);
                }
                return $result . ')';
            default:
                throw new RuntimeException("Unsupported type: {$type}");
        }
    }
}
