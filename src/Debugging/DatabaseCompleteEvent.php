<?php
declare(strict_types=1);

namespace Szemul\Database\Debugging;

use JetBrains\PhpStorm\Pure;
use Szemul\Debugger\Event\DebugEventInterface;
use Throwable;

class DatabaseCompleteEvent implements DebugEventInterface
{
    private float $timestamp;
    private float $runtime;

    #[Pure]
    public function __construct(private DatabaseStartEvent $startEvent, private ?Throwable $exception = null)
    {
        $this->timestamp = microtime(true);
        $this->runtime   = $this->timestamp = $this->startEvent->getTimestamp();
    }

    public function isSuccessful(): bool
    {
        return null === $this->exception;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function getRuntime(): float
    {
        return $this->runtime;
    }

    public function getStartEvent(): DatabaseStartEvent
    {
        return $this->startEvent;
    }

    public function getException(): ?Throwable
    {
        return $this->exception;
    }

    public function __toString(): string
    {
        return 'Database query complete in ' . number_format($this->getRuntime() * 1000, 2) . ' ms';
    }
}
