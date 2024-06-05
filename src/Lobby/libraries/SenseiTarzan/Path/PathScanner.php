<?php

namespace Lobby\libraries\SenseiTarzan\Path;

use Generator;
use Symfony\Component\Filesystem\Path;

class PathScanner
{

    public static function scanDirectory(string $path, array $filterExtension = []): array
    {
        $scanDir = [];
        foreach (self::scanDirectoryGenerator($path, $filterExtension) as $file) {
            $scanDir[] = $file;
        }
        return $scanDir;
    }
    public static function scanDirectoryGenerator(string $path, array $filterExtension = []): ?Generator
    {
        foreach (scandir($path,0) as $file) {
            if ($file === ".." || $file === '.') continue;
            if (is_dir($realpath = Path::join($path, $file))) {
                foreach (self::scanSubDirectoryGenerator($path, $file, $filterExtension) as $file){
                    yield $file;
                }
                continue;
            }
            if (!empty($filterExtension) && !in_array(pathinfo($realpath)["extension"] ?? "NULL", $filterExtension)) continue;
            if (!is_file($realpath)) continue;
            yield $realpath;
        }
    }
    public static function scanDirectoryToConfig(string $path, array $filterExtension = [], int $type = Config::YAML): ?Generator
    {
        foreach (self::scanDirectoryGenerator($path, $filterExtension) as $file){
            yield $file => new Config($file, $type);
        }
    }

    private static function scanSubDirectoryGenerator(string $path, string $nextPath, array $filterExtension = []): ?Generator{
        foreach (scandir($pathJoin = Path::join($path,$nextPath),0) as $file){
            if ($file === ".." || $file === '.') continue;
            if (is_dir($realpath = Path::join($pathJoin, $file))) {
                foreach (self::scanSubDirectoryGenerator($pathJoin, $file, $filterExtension) as $file){
                    yield $file;
                }
                continue;
            }
            if (!empty($filterExtension) && !in_array(pathinfo($realpath)["extension"] ?? "NULL", $filterExtension)) continue;
            if (!is_file($realpath)) continue;
            yield $realpath;
        }
    }
}