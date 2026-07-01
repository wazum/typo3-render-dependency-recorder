<?php

declare(strict_types=1);

namespace Wazum\FluidRenderRecorder\Tests\Unit\Recorder;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Wazum\FluidRenderRecorder\Recorder\RecorderContext;

final class RecorderContextTest extends UnitTestCase
{
    #[Test]
    public function inactiveContextRecordsNothing(): void
    {
        $context = new RecorderContext();

        $context->recordTemplate('/some/Template.html');
        $context->recordAssetEntry('source/main.ts');

        self::assertFalse($context->isActive());
        self::assertSame([], $context->templates());
        self::assertSame([], $context->assets());
    }

    #[Test]
    public function activeContextRecordsSortedUniqueValues(): void
    {
        $context = new RecorderContext();
        $context->activate('accordion.verify.ts', 'run-1');

        $context->recordTemplate('/b/Second.html');
        $context->recordTemplate('/a/First.html');
        $context->recordTemplate('/b/Second.html');
        $context->recordAssetEntry('z.ts');
        $context->recordAssetEntry('a.ts');

        self::assertTrue($context->isActive());
        self::assertSame('accordion.verify.ts', $context->key());
        self::assertSame('run-1', $context->runId());
        self::assertSame(['/a/First.html', '/b/Second.html'], $context->templates());
        self::assertSame(['a.ts', 'z.ts'], $context->assets());
    }

    #[Test]
    public function deactivateClearsState(): void
    {
        $context = new RecorderContext();
        $context->activate('k', 'r');
        $context->recordTemplate('/x/T.html');
        $context->deactivate();

        self::assertFalse($context->isActive());
        self::assertNull($context->key());
        self::assertSame('', $context->runId());
        self::assertSame([], $context->templates());
    }
}
