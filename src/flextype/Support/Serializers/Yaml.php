<?php

declare(strict_types=1);

/**
 * Flextype (https://flextype.org)
 * Founded by Sergey Romanenko and maintained by Flextype Community.
 */

namespace Flextype\Support\Serializers;

use Atomastic\Strings\Strings;
use RuntimeException;
use Symfony\Component\Yaml\Exception\DumpException as SymfonyYamlDumpException;
use Symfony\Component\Yaml\Exception\ParseException as SymfonyYamlParseException;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

use function error_reporting;
use function function_exists;
use function ini_get;
use function ini_set;

class Yaml
{
    public const DUMP_OBJECT                     = 1;
    public const PARSE_EXCEPTION_ON_INVALID_TYPE = 2;
    public const PARSE_OBJECT                    = 4;
    public const PARSE_OBJECT_FOR_MAP            = 8;
    public const DUMP_EXCEPTION_ON_INVALID_TYPE  = 16;
    public const PARSE_DATETIME                  = 32;
    public const DUMP_OBJECT_AS_MAP              = 64;
    public const DUMP_MULTI_LINE_LITERAL_BLOCK   = 128;
    public const PARSE_CONSTANT                  = 256;
    public const PARSE_CUSTOM_TAGS               = 512;
    public const DUMP_EMPTY_ARRAY_AS_SEQUENCE    = 1024;

    /**
     * Native
     *
     * Use native parser or symfony parser
     *
     * @var bool
     */
    public static $native = true;

    /**
     * Dumps a PHP value to a YAML string.
     *
     * The dump method, when supplied with an array, will do its best
     * to convert the array into friendly YAML.
     *
     * @param mixed $input  The PHP value
     * @param int   $inline The level where you switch to inline YAML
     * @param int   $indent The amount of spaces to use for indentation of nested nodes
     * @param int   $flags  A bit field of DUMP_* constants to customize the dumped YAML string
     *
     * @return string A YAML string representing the original PHP value
     */
    public function encode($input, int $inline = 5, int $indent = 2, int $flags = 0): string
    {
        return $this->_encode($input, $inline, $indent, $flags);
    }

    /**
     * Parses YAML into a PHP value.
     *
     * @param string $input A string containing YAML
     * @param bool   $cache Cache result data or no. Default is true
     * @param int    $flags A bit field of PARSE_* constants to customize the YAML parser behavior
     *
     * @return mixed The YAML converted to a PHP value
     *
     * @throws ParseException If the YAML is not valid
     */
    public function decode(string $input, bool $cache = true, int $flags = 0): array
    {
        if ($cache === true && flextype('registry')->get('flextype.settings.cache.enabled') === true) {
            $key = $this->getCacheID($input);

            if ($data_from_cache = flextype('cache')->get($key)) {
                return $data_from_cache;
            }

            $data = $this->_decode($input, $flags);
            flextype('cache')->set($key, $data);

            return $data;
        }

        return $this->_decode($input, $flags);
    }

    /**
     * @see encode()
     */
    protected function _encode($input, int $inline = 5, int $indent = 2, int $flags = 0): string
    {
        try {
            return SymfonyYaml::dump(
                $input,
                $inline,
                $indent,
                $flags
            );
        } catch (SymfonyYamlDumpException $e) {
            throw new RuntimeException('Encoding YAML failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @see decode()
     */
    protected function _decode(string $input, int $flags = 0): array
    {
        // Try native PECL YAML PHP extension first if available.
        if (function_exists('yaml_parse') && $this->native) {
            // Safely decode YAML.

            // Save and Mute error_reporting
            $errorReporting = error_reporting();
            error_reporting(0);

            $saved = ini_get('yaml.decode_php');
            ini_set('yaml.decode_php', '0');
            $decoded = yaml_parse($input);
            ini_set('yaml.decode_php', $saved);

            // Restore error_reporting
            error_reporting($errorReporting);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        try {
            return SymfonyYaml::parse($input, $flags);
        } catch (SymfonyYamlParseException $e) {
            throw new RuntimeException('Decoding YAML failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getCacheID($input): string
    {
        return Strings::create('yaml' . $input)->hash()->toString();
    }
}
