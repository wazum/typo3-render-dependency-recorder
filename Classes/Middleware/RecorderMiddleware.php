<?php

declare(strict_types=1);

namespace Wazum\FluidRenderRecorder\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Page\AssetCollector;
use Wazum\FluidRenderRecorder\Recorder\RecorderContext;
use Wazum\FluidRenderRecorder\Writer\RequestFileWriter;

final class RecorderMiddleware implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly RecorderContext $recorder,
        private readonly RequestFileWriter $writer,
        private readonly AssetCollector $assetCollector,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $config = $this->configuration();
        if (!$this->contextAllowed($config)) {
            return $handler->handle($request);
        }

        $key = $request->getHeaderLine($this->recordHeader($config));
        if ($key === '') {
            return $handler->handle($request);
        }

        $request->getAttribute('frontend.cache.instruction')
            ?->disableCache('EXT:fluid_render_recorder: recording active');

        $requestedDeep = strtolower($request->getHeaderLine($this->depthHeader($config))) === 'deep';
        $depth = $requestedDeep && $this->coverageAvailable() ? 'deep' : 'shallow';

        $runId = $request->getHeaderLine($this->runHeader($config));
        $this->recorder->activate($key, $runId !== '' ? $runId : 'default', $depth);

        if ($depth === 'deep') {
            \xdebug_start_code_coverage();
        }

        try {
            return $handler->handle($request);
        } finally {
            try {
                if ($depth === 'deep') {
                    $coverage = \xdebug_get_code_coverage();
                    \xdebug_stop_code_coverage();
                    foreach (array_keys($coverage) as $executedFile) {
                        $this->recorder->recordFile((string)$executedFile);
                    }
                }
                $this->collectAssets();
                $this->writer->write(
                    $this->recorder,
                    Environment::getVarPath() . '/fluid-render-recorder',
                    Environment::getProjectPath(),
                    $this->rootsList($config),
                );
            } catch (\Throwable $exception) {
                $this->logger?->warning('Fluid render recorder failed to persist a request file', ['exception' => $exception]);
            } finally {
                $this->recorder->deactivate();
            }
        }
    }

    private function coverageAvailable(): bool
    {
        return function_exists('xdebug_start_code_coverage')
            && function_exists('xdebug_get_code_coverage')
            && function_exists('xdebug_stop_code_coverage')
            && str_contains((string)ini_get('xdebug.mode'), 'coverage');
    }

    private function contextAllowed(array $config): bool
    {
        $raw = is_string($config['activeContexts'] ?? null) && $config['activeContexts'] !== ''
            ? $config['activeContexts']
            : 'Testing';
        $context = (string)Environment::getContext();
        foreach (array_map('trim', explode(',', $raw)) as $allowed) {
            if ($allowed !== '' && str_starts_with($context, $allowed)) {
                return true;
            }
        }

        return false;
    }

    private function collectAssets(): void
    {
        $externalBuckets = [
            $this->assetCollector->getJavaScripts(),
            $this->assetCollector->getStyleSheets(),
        ];
        foreach ($externalBuckets as $assets) {
            foreach ($assets as $identifier => $asset) {
                $source = is_array($asset) && is_string($asset['source'] ?? null) ? $asset['source'] : '';
                $reference = $this->externalAssetReference((string)$identifier, $source);
                if ($reference !== null) {
                    $this->recorder->recordAssetEntry($reference);
                }
            }
        }

        $inlineBuckets = [
            $this->assetCollector->getInlineJavaScripts(),
            $this->assetCollector->getInlineStyleSheets(),
        ];
        foreach ($inlineBuckets as $assets) {
            foreach (array_keys($assets) as $identifier) {
                $entry = $this->viteEntry((string)$identifier);
                if ($entry !== null) {
                    $this->recorder->recordAssetEntry($entry);
                }
            }
        }
    }

    private function externalAssetReference(string $identifier, string $source): ?string
    {
        $entry = $this->viteEntry($identifier);
        if ($entry !== null) {
            return $entry;
        }

        return match (true) {
            $source === '', str_contains($source, '://'), str_starts_with($source, '//') => null,
            default => $source,
        };
    }

    private function viteEntry(string $identifier): ?string
    {
        if (!str_starts_with($identifier, 'vite:')) {
            return null;
        }
        $entry = substr($identifier, 5);

        return str_contains($entry, ':') ? null : $entry;
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        try {
            $config = $this->extensionConfiguration->get('fluid_render_recorder');
        } catch (\Throwable) {
            return [];
        }

        return is_array($config) ? $config : [];
    }

    /** @param array<string, mixed> $config */
    private function recordHeader(array $config): string
    {
        return is_string($config['recordHeader'] ?? null) && $config['recordHeader'] !== ''
            ? $config['recordHeader']
            : 'X-Render-Record';
    }

    /** @param array<string, mixed> $config */
    private function runHeader(array $config): string
    {
        return is_string($config['runHeader'] ?? null) && $config['runHeader'] !== ''
            ? $config['runHeader']
            : 'X-Render-Run';
    }

    /** @param array<string, mixed> $config */
    private function depthHeader(array $config): string
    {
        return is_string($config['depthHeader'] ?? null) && $config['depthHeader'] !== ''
            ? $config['depthHeader']
            : 'X-Render-Depth';
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string>
     */
    private function rootsList(array $config): array
    {
        $raw = is_string($config['roots'] ?? null) && $config['roots'] !== ''
            ? $config['roots']
            : 'source/,local/';

        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $root): bool => $root !== ''));
    }
}
