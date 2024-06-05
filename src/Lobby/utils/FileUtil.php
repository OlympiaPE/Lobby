<?php

namespace Lobby\utils;

use Lobby\libraries\SenseiTarzan\Path\PathScanner;
use Lobby\Loader;

class FileUtil
{
    /**
     * @param string $directory
     * @param callable $callable
     * @param string|null $filesType
     * @param array $ignoreNamespace
     * @return void
     */
    public static function callDirectory(string $directory, callable $callable, string $filesType = null, array $ignoreNamespace = []): void
    {
        $main = explode("\\", Loader::getInstance()->getDescription()->getMain());
        unset($main[array_key_last($main)]);
        $main = implode("/", $main);
        $directory = rtrim(str_replace(DIRECTORY_SEPARATOR, "/", $directory), "/");
        $dir = Loader::getInstance()->getFile() . "src/$main/" . $directory;

        foreach (PathScanner::scanDirectory($dir, $filesType ?? ["php"]) as $file) {

            $namespace = str_replace(["/", ".php"], ["\\", ""], strstr($file, $main));
            if(!in_array($namespace, $ignoreNamespace)) {
                $callable($namespace);
            }
        }
    }
}
