<?php

declare(strict_types=1);
use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\Detector\DetectorInterface;
use Componenta\Detector\MimeMapInterface;
use Componenta\DI\ContainerFactory;
use Componenta\Http\ResponderConfigProvider;

it('keeps detector services provided by the responder available with Config 3', function (): void {
    $composition = (new ConfigFactory())->create(new Environment([]), new ResponderConfigProvider());
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies);
    expect($container->get(DetectorInterface::class))->toBeInstanceOf(DetectorInterface::class)
        ->and($container->get(MimeMapInterface::class))->toBeInstanceOf(MimeMapInterface::class);
});

final class ApplicationMimeDetector implements \Componenta\Detector\DetectorInterface
{
    public function detectMimeType(string|\Psr\Http\Message\StreamInterface $content, bool $asObject = false): string|\Componenta\Detector\MimeType|null
    {
        return $asObject ? new \Componenta\Detector\MimeType('application/x-custom') : 'application/x-custom';
    }

    public function detectFileMimeType(string $filename, bool $asObject = false): string|\Componenta\Detector\MimeType|null
    {
        throw new LogicException('This detector only supports content detection.');
    }

    public function detectExtension(string|\Psr\Http\Message\StreamInterface $content, bool $asObject = false): string|\Componenta\Detector\Ext|null
    {
        throw new LogicException('This detector only supports content detection.');
    }

    public function detectFileExtension(string $filename, bool $asObject = false): string|\Componenta\Detector\Ext|null
    {
        throw new LogicException('This detector only supports content detection.');
    }
}

it('uses application MIME services when creating responses through the provider', function (): void {
    $http = new \Nyholm\Psr7\Factory\Psr17Factory();
    $map = (new \Componenta\Detector\MimeMap())->extend(['application/x-componenta' => ['cmpaudit']]);
    $composition = (new ConfigFactory())->create(
        new Environment([]),
        new ResponderConfigProvider(),
        static fn (): array => [\Componenta\Config\ConfigKey::DEPENDENCIES => [
            \Componenta\Config\ConfigKey::SERVICES => [
                \Psr\Http\Message\ResponseFactoryInterface::class => $http,
                \Psr\Http\Message\StreamFactoryInterface::class => $http,
                'application.mime_map' => $map,
                'application.detector' => new ApplicationMimeDetector(),
            ],
            \Componenta\Config\ConfigKey::ALIASES => [
                MimeMapInterface::class => 'application.mime_map',
                DetectorInterface::class => 'application.detector',
            ],
        ]],
    );
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies);
    $responder = $container->get(\Componenta\Http\Responder::class);

    expect([
        $responder->file('payload', 'example.cmpaudit')->getHeaderLine('Content-Type'),
        $responder->respond(content: 'payload')->getHeaderLine('Content-Type'),
    ])->toBe(['application/x-componenta', 'application/x-custom']);
});
