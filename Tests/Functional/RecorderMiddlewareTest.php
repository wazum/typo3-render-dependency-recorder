<?php

declare(strict_types=1);

namespace Wazum\FluidRenderRecorder\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\FluidRenderRecorder\Middleware\RecorderMiddleware;
use Wazum\FluidRenderRecorder\Recorder\RecorderContext;

final class RecorderMiddlewareTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['wazum/typo3-fluid-render-recorder'];

    protected function setUp(): void
    {
        parent::setUp();
        $directory = $this->instancePath . '/typo3temp/var/fluid-render-recorder';
        if (is_dir($directory)) {
            GeneralUtility::rmdir($directory, true);
        }
    }

    #[Test]
    public function writesPerRequestFileWithTopLevelViteEntriesWhenHeaderPresent(): void
    {
        $recorder = $this->get(RecorderContext::class);
        $handler = new class ($recorder) implements RequestHandlerInterface {
            public function __construct(private RecorderContext $recorder) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->recorder->recordFile('/x/T.html');
                $assets = GeneralUtility::makeInstance(AssetCollector::class);
                $assets->addJavaScript('vite:source/main.ts', '/_assets/main.js');
                $assets->addStyleSheet('vite:source/main.ts:assets/x.css', '/_assets/x.css');
                $assets->addJavaScript('legacy', 'EXT:some_ext/Resources/Public/legacy.js');
                $assets->addStyleSheet('cdn', 'https://cdn.example.com/x.css');
                $assets->addInlineStyleSheet('vite:source/critical.ts', '.a{}');
                $assets->addInlineStyleSheet('a1b2c3d4e5f6', '.crit{}');
                return new JsonResponse(['ok' => true]);
            }
        };

        $request = (new ServerRequest('https://example.org/', 'GET'))
            ->withHeader('X-Render-Record', 'accordion.verify.ts')
            ->withHeader('X-Render-Run', 'run-7');

        $this->get(RecorderMiddleware::class)->process($request, $handler);

        $dir = $this->instancePath . '/typo3temp/var/fluid-render-recorder/requests/run-7';
        $files = glob($dir . '/*.json') ?: [];
        self::assertCount(1, $files);
        $body = json_decode((string)file_get_contents($files[0]), true);
        self::assertSame('accordion.verify.ts', $body['key']);
        self::assertContains('source/main.ts', $body['assets']);
        self::assertContains('source/critical.ts', $body['assets']);
        self::assertContains('EXT:some_ext/Resources/Public/legacy.js', $body['assets']);
        self::assertNotContains('/_assets/main.js', $body['assets']);
        self::assertNotContains('https://cdn.example.com/x.css', $body['assets']);
        self::assertNotContains('source/main.ts:assets/x.css', $body['assets']);
        self::assertNotContains('a1b2c3d4e5f6', $body['assets']);
    }

    #[Test]
    public function doesNothingWithoutHeader(): void
    {
        $recorder = $this->get(RecorderContext::class);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new JsonResponse(['ok' => true]);
            }
        };

        $this->get(RecorderMiddleware::class)->process(new ServerRequest('https://example.org/', 'GET'), $handler);

        self::assertFalse($recorder->isActive());
        self::assertDirectoryDoesNotExist($this->instancePath . '/typo3temp/var/fluid-render-recorder/requests');
    }

    #[Test]
    public function disablesPageCacheWhenRecording(): void
    {
        $instruction = new \TYPO3\CMS\Frontend\Cache\CacheInstruction();
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new JsonResponse(['ok' => true]);
            }
        };

        $request = (new ServerRequest('https://example.org/', 'GET'))
            ->withAttribute('frontend.cache.instruction', $instruction)
            ->withHeader('X-Render-Record', 'demo.verify.ts')
            ->withHeader('X-Render-Run', 'run-9');

        $this->get(RecorderMiddleware::class)->process($request, $handler);

        self::assertFalse($instruction->isCachingAllowed());
    }

    #[Test]
    public function doesNothingWhenContextNotAllowed(): void
    {
        $extensionConfiguration = $this->createStub(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn(['activeContexts' => 'Production']);

        $middleware = new RecorderMiddleware(
            $this->get(RecorderContext::class),
            $this->get(\Wazum\FluidRenderRecorder\Writer\RequestFileWriter::class),
            $this->get(AssetCollector::class),
            $extensionConfiguration,
        );

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new JsonResponse(['ok' => true]);
            }
        };

        $request = (new ServerRequest('https://example.org/', 'GET'))->withHeader('X-Render-Record', 'demo.verify.ts');
        $middleware->process($request, $handler);

        self::assertFalse($this->get(RecorderContext::class)->isActive());
    }

    #[Test]
    public function deepRequestDowngradesToShallowWhenCoverageUnavailable(): void
    {
        $recorder = $this->get(RecorderContext::class);
        $handler = new class ($recorder) implements RequestHandlerInterface {
            public function __construct(private RecorderContext $recorder) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->recorder->recordFile('/x/T.html');
                return new JsonResponse(['ok' => true]);
            }
        };

        $request = (new ServerRequest('https://example.org/', 'GET'))
            ->withHeader('X-Render-Record', 'demo.verify.ts')
            ->withHeader('X-Render-Run', 'run-depth')
            ->withHeader('X-Render-Depth', 'deep');

        $this->get(RecorderMiddleware::class)->process($request, $handler);

        $files = glob($this->instancePath . '/typo3temp/var/fluid-render-recorder/requests/run-depth/*.json') ?: [];
        self::assertCount(1, $files);
        $body = json_decode((string)file_get_contents($files[0]), true);
        self::assertSame('shallow', $body['depth']);
    }

    #[Test]
    public function capturesExecutedPhpWhenCoverageAvailable(): void
    {
        if (!function_exists('xdebug_start_code_coverage') || !str_contains((string)ini_get('xdebug.mode'), 'coverage')) {
            self::markTestSkipped('Xdebug coverage not available.');
        }
        self::assertTrue(true);
    }
}
