<?php

declare(strict_types=1);

it('leaves all runtime registration to Interceptor 4 without registering builders or factory overrides', function (): void {
    expect((new \Componenta\Interceptor\App\ConfigProvider())())->toBe([\Componenta\Config\ConfigKey::DEPENDENCIES => []]);
});
