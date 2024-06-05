<?php

namespace Lobby\libraries\SenseiTarzan\Path;

use ErrorException;
use InvalidArgumentException;
use JsonException;
use pocketmine\errorhandler\ErrorToExceptionHandler;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Config as PMConfig;
use pocketmine\utils\ConfigLoadException;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils;
use RuntimeException;
use Symfony\Component\Filesystem\Path;
use function array_fill_keys;
use function array_keys;
use function array_shift;
use function count;
use function explode;
use function file_exists;
use function file_get_contents;
use function get_debug_type;
use function implode;
use function is_array;
use function json_decode;
use function json_encode;
use function preg_replace;
use function serialize;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;
use function unserialize;
use function yaml_emit;
use function yaml_parse;
use const JSON_THROW_ON_ERROR;
use const YAML_UTF8_ENCODING;

/**
 * Config Class extends Config of PocketMine-MP and add support .ini config
 */
class Config extends PMConfig
{
    public const INI = 6; //.ini

    /**
     * @var array
     * @phpstan-var array<string, mixed>
     */
    private array $config = [];

    /**
     * @var array
     * @phpstan-var array<string, mixed>
     */
    private array $nestedCache = [];

    private string $file;
    private int $type = self::DETECT;


    public static $formats = [
        "properties" => self::PROPERTIES,
        "cnf" => self::CNF,
        "conf" => self::CNF,
        "config" => self::CNF,
        "json" => self::JSON,
        "js" => self::JSON,
        "yml" => self::YAML,
        "yaml" => self::YAML,
        "sl" => self::SERIALIZED,
        "serialize" => self::SERIALIZED,
        "txt" => self::ENUM,
        "list" => self::ENUM,
        "enum" => self::ENUM,
        "ini" => self::INI
    ];

    /**
     * @param string $file Path of the file to be loaded
     * @param int $type Config type to load, -1 by default (detect)
     * @param array $default Array with the default values that will be written to the file if it did not exist
     * @phpstan-param array<string, mixed> $default
     */
    public function __construct(string $file, int $type = self::DETECT, array $default = [])
    {
        $this->load($file, $type, $default);
    }

    /**
     * Removes all the changes in memory and loads the file again
     */
    public function reload() : void{
        $this->config = [];
        $this->nestedCache = [];
        $this->load($this->file, $this->type);
    }

    public static function fixYAMLIndexes(string $str) : string{
        return preg_replace("#^( *)(y|Y|yes|Yes|YES|n|N|no|No|NO|true|True|TRUE|false|False|FALSE|on|On|ON|off|Off|OFF)( *)\:#m", "$1\"$2\"$3:", $str);
    }

    /**
     * @param mixed[] $default
     * @phpstan-param array<string, mixed> $default
     *
     * @throws \InvalidArgumentException|JsonException if config type is invalid or could not be auto-detected
     */
    private function load(string $file, int $type = self::DETECT, array $default = []): void
    {
        $this->file = $file;

        $this->type = $type;
        if ($this->type === self::DETECT) {
            $extension = strtolower(Path::getExtension($this->file));
            if (isset(Config::$formats[$extension])) {
                $this->type = Config::$formats[$extension];
            } else {
                throw new InvalidArgumentException("Cannot detect config type of " . $this->file);
            }
        }

        if (!file_exists($file)) {
            $this->config = $default;
            $this->save();
        } else {
            $content = file_get_contents($this->file);
            if ($content === false) {
                throw new RuntimeException("Unable to load config file");
            }
            switch ($this->type) {
                case self::PROPERTIES:
                    $config = self::parseProperties($content);
                    break;
                case self::INI:
                    $config = self::parseIni($content);
                    break;
                case self::JSON:
                    try {
                        $config = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
                    } catch (JsonException $e) {
                        throw ConfigLoadException::wrap($this->file, $e);
                    }
                    break;
                case self::YAML:
                    $content = self::fixYAMLIndexes($content);
                    try {
                        $config = ErrorToExceptionHandler::trap(fn() => yaml_parse($content));
                    } catch (ErrorException $e) {
                        throw ConfigLoadException::wrap($this->file, $e);
                    }
                    break;
                case self::SERIALIZED:
                    try {
                        $config = ErrorToExceptionHandler::trap(fn() => unserialize($content));
                    } catch (ErrorException $e) {
                        throw ConfigLoadException::wrap($this->file, $e);
                    }
                    break;
                case self::ENUM:
                    $config = array_fill_keys(self::parseList($content), true);
                    break;
                default:
                    throw new InvalidArgumentException("Invalid config type specified");
            }
            if (!is_array($config)) {
                throw new ConfigLoadException("Failed to load config $this->file: Expected array for base type, but got " . get_debug_type($config));
            }
            $this->config = $config;
            if ($this->fillDefaults($default, $this->config) > 0) {
                $this->save();
            }
        }
    }

    /**
     * Returns the path of the config.
     */
    public function getPath(): string
    {
        return $this->file;
    }

    /**
     * Flushes the config to disk in the appropriate format.
     * @throws JsonException
     */
    public function save(): void
    {
        $content = match ($this->type) {
            self::PROPERTIES => self::writeProperties($this->config),
            self::INI => self::writeIni($this->config),
            self::JSON => json_encode($this->config, $this->getJsonOptions() | JSON_THROW_ON_ERROR),
            self::YAML => yaml_emit($this->config, YAML_UTF8_ENCODING),
            self::SERIALIZED => serialize($this->config),
            self::ENUM => self::writeList(array_keys($this->config)),
            default => throw new AssumptionFailedError("Config type is unknown, has not been set or not detected"),
        };

        Filesystem::safeFilePutContents($this->file, $content);

        $this->setChanged(false);
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function setNested($key, mixed $value): void
    {
        if ($this->type === Config::INI) {
            $key = preg_replace('/[?\-{}\-|\-&\-~\-!\-[\-(\-)\-^\-§\- ]/', '', $key);
        }
        $vars = explode(".", $key);
        $base = array_shift($vars);

        if(!isset($this->config[$base])){
            $this->config[$base] = [];
        }

        $base = &$this->config[$base];

        while(count($vars) > 0){
            $baseKey = array_shift($vars);
            if(!isset($base[$baseKey])){
                $base[$baseKey] = [];
            }
            $base = &$base[$baseKey];
        }

        $base = $value;
        $this->nestedCache = [];
        $this->setChanged();
    }

    /**
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getNested($key, mixed $default = null): mixed
    {
        if ($this->type === Config::INI) {
            $key = preg_replace('/[?\-{}\-|\-&\-~\-!\-[\-(\-)\-^\-§\- ]/', '', $key);
        }
        if(isset($this->nestedCache[$key])){
            return $this->nestedCache[$key];
        }

        $vars = explode(".", $key);
        $base = array_shift($vars);
        if(isset($this->config[$base])){
            $base = $this->config[$base];
        }else{
            return $default;
        }

        while(count($vars) > 0){
            $baseKey = array_shift($vars);
            if(is_array($base) && isset($base[$baseKey])){
                $base = $base[$baseKey];
            }else{
                return $default;
            }
        }

        return $this->nestedCache[$key] = $base;
    }

    public function removeNested(string $key): void
    {
        if ($this->type === Config::INI) {
            $key = preg_replace('/[?\-{}\-|\-&\-~\-!\-[\-(\-)\-^\-§\- ]/', '', $key);
        }
        $this->nestedCache = [];
        $this->setChanged();

        $vars = explode(".", $key);

        $currentNode = &$this->config;
        while(count($vars) > 0){
            $nodeName = array_shift($vars);
            if(isset($currentNode[$nodeName])){
                if(count($vars) === 0){ //final node
                    unset($currentNode[$nodeName]);
                }elseif(is_array($currentNode[$nodeName])){
                    $currentNode = &$currentNode[$nodeName];
                }
            }else{
                break;
            }
        }
    }

    /**
     * @param string $k
     * @param mixed $default
     *
     * @return bool|mixed
     */
    public function get($k, $default = false)
    {
        if ($this->type === Config::INI) {
            $k = preg_replace('/[?\-{}\-|\-&\-~\-!\-[\-(\-)\-^\-§\- ]/', '', $k);
        }
        return $this->config[$k] ?? $default;
    }

    /**
     * @param string $k key to be set
     * @param mixed $v value to set key
     */
    public function set($k, $v = true): void
    {
        if ($this->type === Config::INI) {
            $k = preg_replace('/[?\-{}\-|\-&\-~\-!\-[\-(\-)\-^\-§\- ]/', '', $k);
        }

        $this->config[$k] = $v;
        $this->setChanged();
        foreach(Utils::stringifyKeys($this->nestedCache) as $nestedKey => $nvalue){
            if(substr($nestedKey, 0, strlen($k) + 1) === ($k . ".")){
                unset($this->nestedCache[$nestedKey]);
            }
        }
    }

    /**
     * @param string $k
     */
    public function remove($k): void
    {

        if ($this->type === Config::INI) {
            $k = preg_replace('/[?\-{}\-|\-&\-~\-!\-[\-(\-)\-^\-§\- ]/', '', $k);
        }
        unset($this->config[$k]);
        $this->setChanged();
    }



    /**
     * @return mixed[]
     * @phpstan-return list<string>|array<string, mixed>
     */
    public function getAll(bool $keys = false) : array{
        return ($keys ? array_keys($this->config) : $this->config);
    }

    public function setAll(array $data) : void{
        $this->config = $data;
        $this->setChanged();
    }


    /**
     * @param mixed[] $default
     * @param mixed[] $data    reference parameter
     * @phpstan-param array<string, mixed> $default
     * @phpstan-param array<string, mixed> $data
     * @phpstan-param-out array<string, mixed> $data
     */
    private function fillDefaults(array $default, &$data) : int{
        $changed = 0;
        foreach(Utils::stringifyKeys($default) as $k => $v){
            if(is_array($v)){
                if(!isset($data[$k]) || !is_array($data[$k])){
                    $data[$k] = [];
                }
                $changed += $this->fillDefaults($v, $data[$k]);
            }elseif(!isset($data[$k])){
                $data[$k] = $v;
                ++$changed;
            }
        }

        if($changed > 0){
            $this->setChanged();
        }

        return $changed;
    }

    private static function parseIni(string $data): array
    {
        $p_ini = parse_ini_string($data, true, INI_SCANNER_RAW);
        if (!$p_ini) {
            return [];
        }
        $config = [];
        foreach ($p_ini as $namespace => $properties) {
            self::createSectionInIni($config, str_replace(['[', ']'], "", $namespace), self::fixPropertiesIni($properties));
        }
        return $config;
    }

    private static function createSectionInIni(array &$config, $key, $value): void
    {
        $vars = explode(".", $key);
        $base = self::decodeIniKey(array_shift($vars));
        if (!isset($config[$base])) {
            $config[$base] = [];
        }

        $base = &$config[$base];

        while (count($vars) > 0) {
            $baseKey = self::decodeIniKey(array_shift($vars));
            if (!isset($base[$baseKey])) {
                $base[$baseKey] = [];
            }
            $base = &$base[$baseKey];
        }
        $base = $value;
    }

    private static function fixPropertiesIni(mixed $properties)
    {
        if (!is_array($properties)) return $properties;
        $fix = [];
        foreach ($properties as $key => $value) {
            $key = self::decodeIniKey($key);
            if (is_array($value)) {
                $fix[$key] = self::fixPropertiesIni($value);
                continue;
            }
            $fix[$key] = $value;

        }
        return $fix;
    }


    private static function writeIni(array $config): string
    {
        $file_content = [];
        $file_content[""] = "";
        foreach ($config as $key_1 => $value_1) {
            if ($key_1 === "") continue;
            $key_1 = self::encodeIniKey($key_1);
            if (!is_array($value_1)) {
                $file_content[""] .= "$key_1=" . self::encodeIniValue($value_1) . PHP_EOL;
                continue;
            }
            $file_content[$key_1] = "[$key_1]" . PHP_EOL;
            $lenFirstKey = strlen($file_content[$key_1]);
            foreach ($value_1 as $key_2 => $value_2) {
                if ($key_2 === "") continue;
                $key_2 = self::encodeIniKey($key_2);
                if (is_array($value_2)) {
                    if (!self::array_is_list($value_2)) {
                        foreach ($value_2 as $key_3 => $value_3) {
                            if ($key_3 === "") continue;
                            $key_3 = self::encodeIniKey($key_3);
                            if (is_string($key_3)) {
                                if (is_array($value_3)) {
                                    if (!self::array_is_list($value_3)) {
                                        $file_content[$key_1 . "." . $key_2 . "." . $key_3] = "[$key_1.$key_2.$key_3]" . PHP_EOL . self::subwriteIni($key_1 . "." . $key_2 . "." . $key_3, $value_3) . PHP_EOL;
                                    } else {
                                        foreach ($value_3 as $list) {
                                            $file_content[$key_1 . "." . $key_2] .= $key_3 . "[]=" . self::encodeIniValue($list) . PHP_EOL;
                                        }
                                    }
                                    continue;
                                }
                                if (!isset($file_content[$key_1 . "." . $key_2])) {
                                    $file_content[$key_1 . "." . $key_2] = "[$key_1.$key_2]" . PHP_EOL;
                                }
                                $file_content[$key_1 . "." . $key_2] .= $key_3 . "=" . self::encodeIniValue($value_3) . PHP_EOL;
                            }
                            if (strlen($file_content[$key_1 . "." . $key_2]) === strlen("[$key_1.$key_2]" . PHP_EOL)){
                                unset($file_content[$key_1 . "." . $key_2]);
                            }
                        }
                    } else {
                        foreach ($value_2 as $list) {
                            $file_content[$key_1] .= $key_2 . "[]=" . self::encodeIniValue($list) . PHP_EOL;
                        }
                    }
                } else {
                    $file_content[$key_1] .= "$key_2=". self::encodeIniValue($value_2) . PHP_EOL;
                }
            }
            if (strlen($file_content[$key_1]) === $lenFirstKey){
                unset($file_content[$key_1]);
            }
        }

        return implode(PHP_EOL, $file_content);
    }

    private static function subwriteIni(string $key, array $subConfig): string
    {
        $file_content = [];
        $file_content[""] = "";
        foreach ($subConfig as $key_1 => $value_1) {
            if ($key_1 === "") continue;
            $key_1 = self::encodeIniKey($key_1);
            if (!is_array($value_1)) {
                $file_content[""] .= "$key_1=" . self::encodeIniValue($value_1) . PHP_EOL;
                continue;
            }
            $file_content[$key . "." . $key_1] = "[$key.$key_1]" . PHP_EOL;
            $lenFirstKey = strlen($file_content[$key . "." . $key_1]);
            foreach ($value_1 as $key_2 => $value_2) {
                if ($key_2 === "") continue;
                $key_2 = self::encodeIniKey($key_2);
                if (is_array($value_2)) {
                    foreach ($value_2 as $key_3 => $value_3) {
                        if ($key_3 === "") continue;
                        $key_3 = self::encodeIniKey($key_3);
                        if (!isset($file_content[$key . "." . $key_1 . "." . $key_2])) {
                            $file_content[$key . "." . $key_1 . "." . $key_2] = "[$key.$key_1.$key_2]" . PHP_EOL;
                        }
                        if (is_array($value_3)) {
                            if (!self::array_is_list($value_3)) {
                                $file_content[$key . "." . $key_1 . "." . $key_2 . "." . $key_3] = "[$key.$key_1.$key_2.$key_3]" . PHP_EOL . self::subwriteIni($key . "." . $key_1 . "." . $key_2 . "." . $key_3, $value_3);
                            } else {
                                foreach ($value_3 as $list) {
                                    $file_content[$key . "." . $key_1 . "." . $key_2] .= $key_3 . "[]=" . self::encodeIniValue($list) . PHP_EOL;
                                }
                            }
                            continue;
                        }
                        $file_content[$key . "." . $key_1 . "." . $key_2] .= $key_3 . "=" . self::encodeIniValue($value_3) . PHP_EOL;
                    }
                } else {
                    $file_content[$key . "." . $key_1] .= "$key_2=" . self::encodeIniValue($value_2) . PHP_EOL;
                }
            }
            if (strlen($file_content[$key . "." . $key_1]) === $lenFirstKey){
                unset($file_content[$key . "." . $key_1]);
            }
        }
        return implode(PHP_EOL, $file_content);
    }

    private static function encodeIniValue(mixed $value): mixed
    {
        return match ($value) {
            true => "true",
            false => "false",
            null => "null",
            default => is_string($value) ? '"' . str_replace(["\n"], ['\n'], $value) . '"' : $value
        };
    }

    private static function encodeIniKey(mixed $value): string
    {
        return match ($value) {
            "true" => "__type_true__",
            "false" => "__type_false__",
            "null" => "__type_null__",
            default => (string)(is_string($value) ? preg_replace('/[?\-{}\-|\-&\-~\-!\-[\-(\-)\-^\-§\- ]/', '', $value) : $value)
        };
    }

    private static function decodeIniKey(mixed $value): mixed
    {
        return match ($value) {
            "__type_true__" => 'true',
            "__type_false__" => 'false',
            "__type_null__" => 'null',
            default => $value
        };
    }

    public static function array_is_list(array $array): bool
    {
        $i = 0;
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                return false;
            }
            if ($k !== $i++) {
                return false;
            }
        }
        return true;
    }

}
