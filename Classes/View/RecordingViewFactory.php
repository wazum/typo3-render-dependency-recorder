<?php

declare(strict_types=1);

namespace Wazum\FluidRenderRecorder\View;

use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext as FluidRenderingContext;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use Wazum\FluidRenderRecorder\Fluid\RecordingTemplatePaths;
use Wazum\FluidRenderRecorder\Recorder\RecorderContext;

final readonly class RecordingViewFactory implements ViewFactoryInterface
{
    public function __construct(
        private ViewFactoryInterface $inner,
        private RecorderContext $recorder,
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
        } catch (\Throwable) {
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
