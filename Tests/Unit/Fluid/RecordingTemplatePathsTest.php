<?php

declare(strict_types=1);

namespace Wazum\RenderDependencyRecorder\Tests\Unit\Fluid;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Fluid\View\TemplatePaths;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3Fluid\Fluid\View\TemplatePaths as FluidTemplatePaths;
use Wazum\RenderDependencyRecorder\Fluid\RecordingTemplatePaths;
use Wazum\RenderDependencyRecorder\Recorder\RecorderContext;

final class RecordingTemplatePathsTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    #[Test]
    public function recordsResolvedTemplateFilePath(): void
    {
        $fixtures = __DIR__ . '/Fixtures';
        $recorder = new RecorderContext();
        $recorder->activate('k', 'r');

        $source = new TemplatePaths();
        $source->setTemplateRootPaths([$fixtures . '/Templates']);
        $source->setFormat('html');

        $paths = RecordingTemplatePaths::fromExisting($source, $recorder);
        $paths->getTemplateSource('Default', 'Simple');

        self::assertContains($fixtures . '/Templates/Default/Simple.html', $recorder->files());
    }

    #[Test]
    public function inlineTemplateSourceRecordsNoFile(): void
    {
        $recorder = new RecorderContext();
        $recorder->activate('k', 'r');

        $source = new TemplatePaths();
        $paths = RecordingTemplatePaths::fromExisting($source, $recorder);
        $paths->setTemplateSource('<h1>inline</h1>');

        self::assertSame('<h1>inline</h1>', $paths->getTemplateSource());
        self::assertSame([], $recorder->files());
    }

    #[Test]
    public function wrapWhenActiveReturnsTheOriginalPathsWhenRecorderIsInactive(): void
    {
        $paths = new TemplatePaths();

        self::assertSame($paths, RecordingTemplatePaths::wrapWhenActive($paths));
    }

    #[Test]
    public function wrapWhenActiveReturnsRecordingPathsWhenRecorderIsActive(): void
    {
        $fixtures = __DIR__ . '/Fixtures';
        $recorder = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(RecorderContext::class);
        $recorder->activate('k', 'r');

        $paths = new TemplatePaths();
        $paths->setTemplateRootPaths([$fixtures . '/Templates']);
        $paths->setFormat('html');

        $wrapped = RecordingTemplatePaths::wrapWhenActive($paths);

        self::assertInstanceOf(RecordingTemplatePaths::class, $wrapped);
        $wrapped->getTemplateSource('Default', 'Simple');
        self::assertContains($fixtures . '/Templates/Default/Simple.html', $recorder->files());
    }

    #[Test]
    public function wrapWhenActiveDoesNotWrapTwice(): void
    {
        $recorder = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(RecorderContext::class);
        $recorder->activate('k', 'r');

        $wrapped = RecordingTemplatePaths::wrapWhenActive(new TemplatePaths());

        self::assertSame($wrapped, RecordingTemplatePaths::wrapWhenActive($wrapped));
    }

    #[Test]
    public function acceptsStandaloneBaseTemplatePathsInstance(): void
    {
        $fixtures = __DIR__ . '/Fixtures';
        $recorder = new RecorderContext();
        $recorder->activate('k', 'r');

        $base = new FluidTemplatePaths();
        $base->setTemplateRootPaths([$fixtures . '/Templates']);
        $base->setFormat('html');

        $paths = RecordingTemplatePaths::fromExisting($base, $recorder);
        $paths->getTemplateSource('Default', 'Simple');

        self::assertContains($fixtures . '/Templates/Default/Simple.html', $recorder->files());
    }
}
