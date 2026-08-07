<?php

declare(strict_types=1);

namespace Componenta\Interceptor\App\Tests\Fixture;

use Componenta\Interceptor\App\Tests\Fixture\Attribute\RecordingIntercept;
use Componenta\Interceptor\App\Tests\Fixture\Attribute\WrapResultAttribute;

final class TargetClass
{
    #[RecordingIntercept(name: 'A')]
    public function single(): string
    {
        return 'single';
    }

    #[WrapResultAttribute(marker: 'wrap')]
    public function direct(): string
    {
        return 'direct';
    }
}
