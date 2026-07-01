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

        $context->recordFile('/some/Template.html');
        $context->recordAssetEntry('source/main.ts');

        self::assertFalse($context->isActive());
        self::assertSame([], $context->files());
        self::assertSame([], $context->assets());
    }

    #[Test]
    public function activeContextRecordsSortedUniqueValues(): void
    {
        $context = new RecorderContext();
        $context->activate('accordion.verify.ts', 'run-1');

        $context->recordFile('/b/Second.html');
        $context->recordFile('/a/First.html');
        $context->recordFile('/b/Second.html');
        $context->recordAssetEntry('z.ts');
        $context->recordAssetEntry('a.ts');

        self::assertTrue($context->isActive());
        self::assertSame('accordion.verify.ts', $context->key());
        self::assertSame('run-1', $context->runId());
        self::assertSame(['/a/First.html', '/b/Second.html'], $context->files());
        self::assertSame(['a.ts', 'z.ts'], $context->assets());
    }

    #[Test]
    public function deactivateClearsState(): void
    {
        $context = new RecorderContext();
        $context->activate('k', 'r');
        $context->recordFile('/x/T.html');
        $context->deactivate();

        self::assertFalse($context->isActive());
        self::assertNull($context->key());
        self::assertSame('', $context->runId());
        self::assertSame([], $context->files());
    }

    #[Test]
    public function activateDefaultsDepthToShallow(): void
    {
        $context = new RecorderContext();
        $context->activate('k', 'r');

        self::assertSame('shallow', $context->depth());
    }

    #[Test]
    public function activateStoresGivenDepth(): void
    {
        $context = new RecorderContext();
        $context->activate('k', 'r', 'deep');

        self::assertSame('deep', $context->depth());
    }

    #[Test]
    public function deactivateResetsDepth(): void
    {
        $context = new RecorderContext();
        $context->activate('k', 'r', 'deep');
        $context->deactivate();

        self::assertSame('', $context->depth());
    }
}
