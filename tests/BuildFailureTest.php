<?php

declare(strict_types=1);
use Componenta\ClassFinder\ClassIterator;
use Componenta\Interceptor\App\Build\InterceptorBuilder;
use Componenta\Interceptor\PipelineInterface;

it('preserves the previous map when source discovery fails', function (): void {
    $root = sys_get_temp_dir() . '/interceptor_failure_' . bin2hex(random_bytes(8));
    mkdir($root);
    $file = $root . '/interceptors.php';
    file_put_contents($file, '<?php return [];');
    $failure = new RuntimeException('source failed');
    $source = new ClassIterator((static function () use ($failure): Generator {
        throw $failure;
        yield;
    })());
    try {
        $caught = null;
        try {
            (new InterceptorBuilder($source, $file))->build();
        } catch (RuntimeException $actual) {
            $caught = $actual;
        }
        expect($caught)->toBe($failure);
        expect(file_get_contents($file))->toBe('<?php return [];');
        expect(glob($root . '/.*.tmp'))->toBe([]);
    } finally {
        unlink($file);
        rmdir($root);
    }
});
it('cleans temporary files if publishing fails', function (): void {
    $root = sys_get_temp_dir() . '/interceptor_publish_' . bin2hex(random_bytes(8));
    mkdir($root . '/interceptors.php', 0o755, true);
    file_put_contents($root . '/interceptors.php/previous', 'previous');
    try {
        expect(fn () => (new InterceptorBuilder(new ClassIterator([]), $root . '/interceptors.php'))->build())
            ->toThrow(ErrorException::class, 'rename');
        expect(file_get_contents($root . '/interceptors.php/previous'))->toBe('previous')
            ->and(glob($root . '/.*.tmp'))->toBe([]);
    } finally {
        unlink($root . '/interceptors.php/previous');
        rmdir($root . '/interceptors.php');
        rmdir($root);
    }
});
