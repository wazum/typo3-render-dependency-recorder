<?php

declare(strict_types=1);

namespace Wazum\RenderDependencyRecorder\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\RenderDependencyRecorder\Recorder\RecorderContext;

final class RecordingViewFactoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['wazum/typo3-render-dependency-recorder'];

    #[Test]
    public function recordsTheRenderedTemplateFileWhenActive(): void
    {
        $fixtures = __DIR__ . '/Fixtures/Templates';
        $recorder = $this->get(RecorderContext::class);
        $recorder->activate('demo', 'run-1');

        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [$fixtures],
            templatePathAndFilename: $fixtures . '/Page.html',
        ));
        self::assertInstanceOf(FluidViewAdapter::class, $view);
        $view->render();

        self::assertContains($fixtures . '/Page.html', $recorder->files());
    }

    #[Test]
    public function recordsNothingWhenInactive(): void
    {
        $fixtures = __DIR__ . '/Fixtures/Templates';
        $recorder = $this->get(RecorderContext::class);

        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [$fixtures],
            templatePathAndFilename: $fixtures . '/Page.html',
        ));
        $view->render();

        self::assertSame([], $recorder->files());
    }

    #[Test]
    public function recordsEvenWhenTheCompileCacheIsWarm(): void
    {
        $fixtures = __DIR__ . '/Fixtures/Templates';
        $recorder = $this->get(RecorderContext::class);

        $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [$fixtures],
            templatePathAndFilename: $fixtures . '/Page.html',
        ))->render();

        $recorder->activate('demo', 'run-warm');
        $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [$fixtures],
            templatePathAndFilename: $fixtures . '/Page.html',
        ))->render();

        self::assertContains($fixtures . '/Page.html', $recorder->files());
    }
}
