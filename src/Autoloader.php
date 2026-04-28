<?php

namespace ProofAge\WordPress;

if (! defined('ABSPATH')) {
    exit;
}

final class Autoloader
{
    private const PREFIX = __NAMESPACE__ . '\\';

    public static function register(): void
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    private static function autoload(string $class): void
    {
        if (! str_starts_with($class, self::PREFIX)) {
            return;
        }

        $relativeClass = substr($class, strlen(self::PREFIX));
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        $file = __DIR__ . DIRECTORY_SEPARATOR . $relativePath;

        if (is_readable($file)) {
            require_once $file;
        }
    }
}
