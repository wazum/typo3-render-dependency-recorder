<?php

declare(strict_types=1);

namespace Wazum\RenderDependencyRecorder\View;

use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext as FluidRenderingContext;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use Wazum\RenderDependencyRecorder\Fluid\RecordingTemplatePaths;
use Wazum\RenderDependencyRecorder\Recorder\RecorderContext;

final class RecordingViewFactory implements ViewFactoryInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly ViewFactoryInterface $inner,
        private readonly RecorderContext $recorder,
    ) {}

    public function create(ViewFactoryData $data): ViewInterface
    {
        $view = $this->inner->create($data);

        if (!$this->recorder->isActive() || !$view instanceof FluidViewAdapter) {
            return $view;
        }

        try {
            $renderingContext = $view->getRenderingContext();
            $renderingContext->setTemplatePaths(
                RecordingTemplatePaths::fromExisting($renderingContext->getTemplatePaths(), $this->recorder),
            );
            $this->disableCompileCache($renderingContext);
        } catch (\Throwable $exception) {
            $this->logger?->warning('Fluid render recorder failed to instrument a view', ['exception' => $exception]);

            return $view;
        }

        return $view;
    }

    private function disableCompileCache(RenderingContextInterface $renderingContext): void
    {
        $property = new \ReflectionProperty(FluidRenderingContext::class, 'cache');
        $property->setValue($renderingContext, null);
    }
}
