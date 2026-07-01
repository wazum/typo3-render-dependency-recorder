<?php

declare(strict_types=1);

namespace Wazum\FluidRenderRecorder\Fluid;

use TYPO3\CMS\Fluid\View\TemplatePaths;
use TYPO3Fluid\Fluid\View\TemplatePaths as FluidTemplatePaths;
use Wazum\FluidRenderRecorder\Recorder\RecorderContext;

final class RecordingTemplatePaths extends TemplatePaths
{
    private ?RecorderContext $recorder = null;

    public static function fromExisting(FluidTemplatePaths $source, RecorderContext $recorder): self
    {
        $instance = new self();
        $reflection = new \ReflectionObject($source);
        foreach ($reflection->getProperties() as $property) {
            $property->setValue($instance, $property->getValue($source));
        }
        $instance->recorder = $recorder;

        return $instance;
    }

    public function getTemplateSource($controller = 'Default', $action = 'Default')
    {
        $this->record(fn (): ?string => $this->resolveTemplateFileForControllerAndActionAndFormat(
            (string)($controller ?? 'Default'),
            (string)($action ?? 'Default'),
        ));

        return parent::getTemplateSource($controller, $action);
    }

    public function getPartialSource(string $partialName): string
    {
        $this->record(fn (): string => $this->getPartialPathAndFilename($partialName));

        return parent::getPartialSource($partialName);
    }

    public function getLayoutSource(string $layoutName = 'Default'): string
    {
        $this->record(fn (): string => $this->getLayoutPathAndFilename($layoutName));

        return parent::getLayoutSource($layoutName);
    }

    private function record(callable $resolver): void
    {
        if (!$this->recorder instanceof RecorderContext) {
            return;
        }
        try {
            $path = $resolver();
        } catch (\Throwable) {
            return;
        }
        if (is_string($path) && $path !== '' && is_file($path)) {
            $this->recorder->recordTemplate($path);
        }
    }
}
