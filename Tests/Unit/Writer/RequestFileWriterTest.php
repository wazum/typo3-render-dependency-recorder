<?php

declare(strict_types=1);

namespace Wazum\FluidRenderRecorder\Tests\Unit\Writer;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Wazum\FluidRenderRecorder\Recorder\RecorderContext;
use Wazum\FluidRenderRecorder\Writer\RequestFileWriter;

final class RequestFileWriterTest extends UnitTestCase
{
    #[Test]
    public function writesRepoRelativeSortedBodyWithKeyIndependentFilename(): void
    {
        $outputDir = sys_get_temp_dir() . '/far-' . bin2hex(random_bytes(4));
        $projectPath = '/project';

        $recorder = new RecorderContext();
        $recorder->activate('../evil/key', 'run 1/x');
        $recorder->recordTemplate('/project/local/a/B.html');
        $recorder->recordAssetEntry('source/main.ts');

        $file = (new RequestFileWriter())->write($recorder, $outputDir, $projectPath);

        self::assertFileExists($file);
        self::assertStringNotContainsString('evil', $file);
        self::assertStringContainsString('/requests/', $file);

        $body = json_decode((string)file_get_contents($file), true);
        self::assertSame('../evil/key', $body['key']);
        self::assertSame(['local/a/B.html'], $body['renderedTemplates']);
        self::assertSame(['source/main.ts'], $body['assets']);
    }

    #[Test]
    public function traversalRunIdCannotEscapeRequestsDirectory(): void
    {
        $outputDir = sys_get_temp_dir() . '/far-' . bin2hex(random_bytes(4));
        $recorder = new RecorderContext();
        $recorder->activate('k', '..');
        $recorder->recordTemplate('/project/x/T.html');

        $file = (new RequestFileWriter())->write($recorder, $outputDir, '/project');

        $requestsRoot = realpath($outputDir . '/requests');
        self::assertNotFalse($requestsRoot);
        self::assertStringStartsWith($requestsRoot, (string)realpath($file));
    }
}
