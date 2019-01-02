<?php

declare(strict_types=1);

namespace Ekino\Drupal\Debug\Tests\Unit\Exception;

use Ekino\Drupal\Debug\Exception\NotImplementedException;
use PHPUnit\Framework\TestCase;

class NotImplementedExceptionTest extends TestCase
{
    /**
     * @var NotImplementedException
     */
    private $notImplementedException;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->notImplementedException = new NotImplementedException('foo');
    }

    public function testInstanceOfException(): void
    {
        $this->assertInstanceOf(\Exception::class, $this->notImplementedException);
    }

    public function testGetMessage(): void
    {
        $this->assertSame('foo', $this->notImplementedException->getMessage());
    }
}
