<?php

declare(strict_types=1);

namespace Componenta\Interceptor\App\Build;

use Componenta\App\Build\ApplicationBuilderInterface;
use Componenta\App\Build\PhpMapFile;
use Componenta\App\Build\ApplicationBuildCleanerInterface;
use Componenta\ClassFinder\ClassIteratorInterface;
use Componenta\Interceptor\Internal\AttributeMetadata;
use Componenta\VarExport\VarExport;
use ErrorException;
use RuntimeException;

final readonly class InterceptorBuilder implements ApplicationBuilderInterface, ApplicationBuildCleanerInterface
{
    public function __construct(private ClassIteratorInterface $classes, private string $file)
    {
    }

    public function clean(): void
    {
        PhpMapFile::remove($this->file);
    }

    public function build(): void
    {
        $map = [];
        foreach ($this->classes as $info) {
            foreach ($info->reflector->getMethods() as $method) {
                $positions = AttributeMetadata::positions($method);
                if ($positions !== null) {
                    $map[$method->class . '::' . $method->name] = $positions;
                }
            }
        }
        ksort($map, SORT_STRING);
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . VarExport::withDefaults()->export($map) . ";\n";
        $directory = dirname($this->file);
        $temporary = null;
        $stream = null;
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
                throw new RuntimeException('Cannot create interceptor map directory "' . $directory . '".');
            }
            $temporary = $directory . '/.' . basename($this->file) . '.' . bin2hex(random_bytes(12)) . '.tmp';
            $stream = fopen($temporary, 'xb');
            if ($stream === false) {
                throw new RuntimeException('Cannot open interceptor map "' . $temporary . '".');
            }
            if (fwrite($stream, $content) !== strlen($content) || !fflush($stream)) {
                throw new RuntimeException('Cannot write complete interceptor map "' . $temporary . '".');
            }
            fclose($stream);
            $stream = null;
            if (!rename($temporary, $this->file)) {
                throw new RuntimeException('Cannot publish interceptor map "' . $this->file . '".');
            }
            $temporary = null;
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($this->file, true);
            }
        } finally {
            try {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if ($temporary !== null && is_file($temporary)) {
                    unlink($temporary);
                }
            } finally {
                restore_error_handler();
            }
        }
    }
}
