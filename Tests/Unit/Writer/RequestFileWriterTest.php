<?php

declare(strict_types=1);

namespace Wazum\RenderDependencyRecorder\Tests\Unit\Writer;

use JsonException;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Wazum\RenderDependencyRecorder\Recorder\RecorderContext;
use Wazum\RenderDependencyRecorder\Writer\RequestFileWriter;

final class RequestFileWriterTest extends UnitTestCase
{
    #[Test]
    public function writesRepoRelativeSortedBodyWithKeyIndependentFilename(): void
    {
        $outputDir = sys_get_temp_dir() . '/far-' . bin2hex(random_bytes(4));
        $projectPath = '/project';

        $recorder = new RecorderContext();
        $recorder->activate('../evil/key', 'run 1/x');
        $recorder->recordFile('/project/local/a/B.html');
        $recorder->recordAssetEntry('source/main.ts');

        $file = (new RequestFileWriter())->write($recorder, $outputDir, $projectPath);

        self::assertFileExists($file);
        self::assertStringNotContainsString('evil', $file);
        self::assertStringContainsString('/requests/', $file);

        $body = json_decode((string)file_get_contents($file), true);
        self::assertSame('../evil/key', $body['key']);
        self::assertSame(['local/a/B.html'], $body['files']);
        self::assertSame(['source/main.ts'], $body['assets']);
    }

    #[Test]
    public function traversalRunIdCannotEscapeRequestsDirectory(): void
    {
        $outputDir = sys_get_temp_dir() . '/far-' . bin2hex(random_bytes(4));
        $recorder = new RecorderContext();
        $recorder->activate('k', '..');
        $recorder->recordFile('/project/x/T.html');

        $file = (new RequestFileWriter())->write($recorder, $outputDir, '/project');

        $requestsRoot = realpath($outputDir . '/requests');
        self::assertNotFalse($requestsRoot);
        self::assertStringStartsWith($requestsRoot, (string)realpath($file));
    }

    #[Test]
    public function throwsOnUnencodableBodyRatherThanWritingCorruptOutput(): void
    {
        $outputDir = sys_get_temp_dir() . '/far-' . bin2hex(random_bytes(4));
        $recorder = new RecorderContext();
        $recorder->activate("\xB1\x31", 'run-x');
        $recorder->recordFile('/project/x/T.html');

        $this->expectException(JsonException::class);
        (new RequestFileWriter())->write($recorder, $outputDir, '/project');
    }

    #[Test]
    public function writesDepthFromRecorder(): void
    {
        $outputDir = sys_get_temp_dir() . '/far-' . bin2hex(random_bytes(4));
        $recorder = new RecorderContext();
        $recorder->activate('k', 'run-1', 'deep');
        $recorder->recordFile('/project/x/T.html');

        $file = (new RequestFileWriter())->write($recorder, $outputDir, '/project');

        $body = json_decode((string)file_get_contents($file), true);
        self::assertSame('deep', $body['depth']);
    }

    #[Test]
    public function resolvesSymlinkedVendorPathsToTheRealRoot(): void
    {
        $base = sys_get_temp_dir() . '/frr-' . bin2hex(random_bytes(4));
        mkdir($base . '/local/ext/Resources', 0777, true);
        file_put_contents($base . '/local/ext/Resources/X.html', 'x');
        mkdir($base . '/vendor/v', 0777, true);
        symlink($base . '/local/ext', $base . '/vendor/v/ext');

        $recorder = new RecorderContext();
        $recorder->activate('k', 'run-1');
        $recorder->recordFile($base . '/vendor/v/ext/Resources/X.html');

        $file = (new RequestFileWriter())->write($recorder, $base . '/out', $base, ['local/']);

        $body = json_decode((string)file_get_contents($file), true);
        self::assertSame(['local/ext/Resources/X.html'], $body['files']);
    }

    #[Test]
    public function filtersFilesToConfiguredRoots(): void
    {
        $outputDir = sys_get_temp_dir() . '/far-' . bin2hex(random_bytes(4));
        $recorder = new RecorderContext();
        $recorder->activate('k', 'run-1');
        $recorder->recordFile('/project/local/a/B.html');
        $recorder->recordFile('/project/source/x.ts');
        $recorder->recordFile('/project/vendor/lib/C.php');
        $recorder->recordFile('/outside/D.php');

        $file = (new RequestFileWriter())->write($recorder, $outputDir, '/project', ['source/', 'local/']);

        $body = json_decode((string)file_get_contents($file), true);
        self::assertSame(['local/a/B.html', 'source/x.ts'], $body['files']);
    }
}
