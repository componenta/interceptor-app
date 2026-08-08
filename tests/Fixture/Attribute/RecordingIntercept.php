<?php

declare(strict_types=1);

namespace Componenta\Interceptor\App\Tests\Fixture\Attribute;

use Attribute;
use Componenta\Interceptor\Attribute\Intercept;
use Componenta\Interceptor\App\Tests\Fixture\RecordingInterceptor;

/**
 * Test attribute that resolves to a {@see RecordingInterceptor} instance
 * named via the constructor.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class RecordingIntercept extends Intercept
{
    public function __construct(string $name)
    {
        parent::__construct(RecordingInterceptor::class, ['name' => $name]);
    }
}
