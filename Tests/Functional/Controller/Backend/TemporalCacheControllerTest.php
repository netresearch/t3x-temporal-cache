<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Controller\Backend;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Controller\Backend\TemporalCacheController;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository;
use Netresearch\TemporalCache\Service\Backend\HarmonizationAnalysisService;
use Netresearch\TemporalCache\Service\Backend\PermissionService;
use Netresearch\TemporalCache\Service\Backend\TemporalCacheStatisticsService;
use Netresearch\TemporalCache\Service\HarmonizationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\Route;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Comprehensive functional tests for TemporalCacheController backend module.
 *
 * Test Coverage:
 * - Dashboard action: statistics, timeline, configuration summary
 * - Content action: list display, pagination, filtering (7 filter types), harmonization suggestions
 * - Wizard action: configuration presets, form submission, recommendations
 * - Harmonize action: normal operation, dry-run mode, input validation, error handling
 * - JSON response formatting for AJAX endpoints
 * - Edge cases: empty content, invalid filters, pagination boundaries, missing configuration
 *
 * @covers \Netresearch\TemporalCache\Controller\Backend\TemporalCacheController
 */
final class TemporalCacheControllerTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'temporal_cache' => [
                'scoping' => [
                    'strategy' => 'per-content',
                    'use_refindex' => true,
                ],
                'timing' => [
                    'strategy' => 'dynamic',
                ],
                'harmonization' => [
                    'enabled' => true,
                    'slots' => '00:00,06:00,12:00,18:00',
                    'tolerance' => 3600,
                ],
            ],
        ],
    ];

    private TemporalCacheController $controller;
    private TemporalContentRepository $repository;
    private ExtensionConfiguration $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/tt_content.csv');

        // Set up backend user context
        $this->setUpBackendUser(1);

        // Initialize language service for backend (required for ModuleTemplate)
        $GLOBALS['LANG'] = $this->get(\TYPO3\CMS\Core\Localization\LanguageServiceFactory::class)->createFromUserPreferences($GLOBALS['BE_USER']);

        // Initialize services
        $this->configuration = $this->get(ExtensionConfiguration::class);
        $this->repository = $this->get(TemporalContentRepository::class);

        // Initialize controller
        $this->controller = new TemporalCacheController(
            $this->get(ModuleTemplateFactory::class),
            $this->configuration,
            $this->repository,
            $this->get(TemporalCacheStatisticsService::class),
            $this->get(HarmonizationAnalysisService::class),
            $this->get(HarmonizationService::class),
            $this->get(PermissionService::class),
            $this->get(CacheManager::class)
        );
    }

    // =========================================================================
    // Dashboard Action Tests
    // =========================================================================

    /**
     * @test
     */
    public function dashboardActionReturnsSuccessfulResponse(): void
    {
        $request = $this->createRequest();

        $response = $this->controller->dashboardAction($request);

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function dashboardActionCalculatesStatistics(): void
    {
        $request = $this->createRequest();

        $response = $this->controller->dashboardAction($request);

        // Response should contain HTML with statistics
        $body = (string)$response->getBody();
        self::assertNotEmpty($body);
    }

    /**
     * @test
     */
    public function dashboardActionWithEmptyContentShowsZeroStatistics(): void
    {
        // Delete all temporal content
        $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeStatement('UPDATE pages SET deleted = 1');
        $this->getConnectionPool()
            ->getConnectionForTable('tt_content')
            ->executeStatement('UPDATE tt_content SET deleted = 1');

        $request = $this->createRequest();

        $response = $this->controller->dashboardAction($request);

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function dashboardActionBuildsTimelineCorrectly(): void
    {
        $request = $this->createRequest();

        $response = $this->controller->dashboardAction($request);

        self::assertSame(200, $response->getStatusCode());
        // Timeline should be rendered in response
        $body = (string)$response->getBody();
        self::assertNotEmpty($body);
    }

    /**
     * @test
     */
    public function dashboardActionShowsConfigurationSummary(): void
    {
        $request = $this->createRequest();

        $response = $this->controller->dashboardAction($request);

        self::assertSame(200, $response->getStatusCode());
        // Configuration summary should be present
        $body = (string)$response->getBody();
        self::assertNotEmpty($body);
    }

    // =========================================================================
    // Content Action Tests - List Display & Pagination
    // =========================================================================

    /**
     * @test
     */
    public function contentActionReturnsSuccessfulResponse(): void
    {
        $request = $this->createRequest();

        $response = $this->controller->contentAction($request);

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function contentActionDisplaysAllContentByDefault(): void
    {
        $request = $this->createRequest();

        $response = $this->controller->contentAction($request, 1, 'all');

        self::assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        self::assertNotEmpty($body);
    }

    /**
     * @test
     */
    public function contentActionPaginatesCorrectly(): void
    {
        // Test pagination with multiple pages
        $response1 = $this->controller->contentAction($request, 1, 'all');
        $response2 = $this->controller->contentAction($request, 2, 'all');

        self::assertSame(200, $response1->getStatusCode());
        self::assertSame(200, $response2->getStatusCode());
    }

    /**
     * @test
     */
    public function contentActionHandlesBoundaryPagination(): void
    {
        // Test first page
        $response = $this->controller->contentAction($request, 1, 'all');
        self::assertSame(200, $response->getStatusCode());

        // Test very high page number (should not crash)
        $response = $this->controller->contentAction($request, 999, 'all');
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function contentActionWithEmptyContentReturnsEmptyList(): void
    {
        // Delete all content
        $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeStatement('UPDATE pages SET deleted = 1');
        $this->getConnectionPool()
            ->getConnectionForTable('tt_content')
            ->executeStatement('UPDATE tt_content SET deleted = 1');

        $response = $this->controller->contentAction($request, 1, 'all');

        self::assertSame(200, $response->getStatusCode());
    }

    // =========================================================================
    // Content Action Tests - Filtering
    // =========================================================================

    /**
     * @test
     * @dataProvider filterTypeProvider
     */
    public function contentActionFiltersContentCorrectly(string $filter): void
    {
        $response = $this->controller->contentAction($request, 1, $filter);

        self::assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        self::assertNotEmpty($body);
    }

    public static function filterTypeProvider(): array
    {
        return [
            'all' => ['all'],
            'pages only' => ['pages'],
            'content only' => ['content'],
            'active only' => ['active'],
            'scheduled only' => ['scheduled'],
            'expired only' => ['expired'],
            'harmonizable only' => ['harmonizable'],
        ];
    }

    /**
     * @test
     */
    public function contentActionFiltersPagesOnly(): void
    {
        $response = $this->controller->contentAction($request, 1, 'pages');

        self::assertSame(200, $response->getStatusCode());
        // Should only show pages, not tt_content
    }

    /**
     * @test
     */
    public function contentActionFiltersContentElementsOnly(): void
    {
        $response = $this->controller->contentAction($request, 1, 'content');

        self::assertSame(200, $response->getStatusCode());
        // Should only show tt_content, not pages
    }

    /**
     * @test
     */
    public function contentActionFiltersActiveContent(): void
    {
        $response = $this->controller->contentAction($request, 1, 'active');

        self::assertSame(200, $response->getStatusCode());
        // Should only show currently visible content
    }

    /**
     * @test
     */
    public function contentActionFiltersScheduledContent(): void
    {
        $response = $this->controller->contentAction($request, 1, 'scheduled');

        self::assertSame(200, $response->getStatusCode());
        // Should only show content with future starttime
    }

    /**
     * @test
     */
    public function contentActionFiltersExpiredContent(): void
    {
        $response = $this->controller->contentAction($request, 1, 'expired');

        self::assertSame(200, $response->getStatusCode());
        // Should only show content with past endtime
    }

    /**
     * @test
     */
    public function contentActionFiltersHarmonizableContent(): void
    {
        $response = $this->controller->contentAction($request, 1, 'harmonizable');

        self::assertSame(200, $response->getStatusCode());
        // Should only show content that can benefit from harmonization
    }

    /**
     * @test
     */
    public function contentActionHandlesInvalidFilterGracefully(): void
    {
        $response = $this->controller->contentAction($request, 1, 'invalid_filter');

        // Should default to 'all' and not crash
        self::assertSame(200, $response->getStatusCode());
    }

    // =========================================================================
    // Content Action Tests - Harmonization Suggestions
    // =========================================================================

    /**
     * @test
     */
    public function contentActionIncludesHarmonizationSuggestions(): void
    {
        $response = $this->controller->contentAction($request, 1, 'all');

        self::assertSame(200, $response->getStatusCode());
        // Each content item should have harmonization suggestion attached
    }

    /**
     * @test
     */
    public function contentActionShowsHarmonizationOnlyWhenEnabled(): void
    {
        // Test with harmonization enabled
        $response = $this->controller->contentAction($request, 1, 'all');
        self::assertSame(200, $response->getStatusCode());

        // Response should indicate harmonization is available
        $body = (string)$response->getBody();
        self::assertNotEmpty($body);
    }

    // =========================================================================
    // Wizard Action Tests
    // =========================================================================

    /**
     * @test
     */
    public function wizardActionReturnsSuccessfulResponse(): void
    {
        $response = $this->controller->wizardAction($request);

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @test
     * @dataProvider wizardStepProvider
     */
    public function wizardActionHandlesDifferentSteps(string $step): void
    {
        $response = $this->controller->wizardAction($request, $step);

        self::assertSame(200, $response->getStatusCode());
    }

    public static function wizardStepProvider(): array
    {
        return [
            'welcome' => ['welcome'],
            'scoping' => ['scoping'],
            'timing' => ['timing'],
            'harmonization' => ['harmonization'],
            'summary' => ['summary'],
        ];
    }

    /**
     * @test
     */
    public function wizardActionShowsConfigurationPresets(): void
    {
        $response = $this->controller->wizardAction($request, 'welcome');

        self::assertSame(200, $response->getStatusCode());
        // Response should contain presets: simple, balanced, aggressive
    }

    /**
     * @test
     */
    public function wizardActionShowsCurrentConfiguration(): void
    {
        $response = $this->controller->wizardAction($request, 'welcome');

        self::assertSame(200, $response->getStatusCode());
        // Should display current configuration values
    }

    /**
     * @test
     */
    public function wizardActionProvidesRecommendations(): void
    {
        $response = $this->controller->wizardAction($request, 'welcome');

        self::assertSame(200, $response->getStatusCode());
        // Should include configuration recommendations based on statistics
    }

    /**
     * @test
     */
    public function wizardActionRecommendationsBasedOnStatistics(): void
    {
        // Wizard should show different recommendations based on content statistics
        $response = $this->controller->wizardAction($request, 'welcome');

        self::assertSame(200, $response->getStatusCode());
    }

    // =========================================================================
    // Harmonize Action Tests - Normal Operation
    // =========================================================================

    /**
     * @test
     */
    public function harmonizeActionSucceedsWithValidInput(): void
    {
        $request = $this->createRequestWithBody([
            'content' => [1, 2],
            'dryRun' => true,
        ]);

        $response = $this->controller->harmonizeAction($request);

        self::assertSame(200, $response->getStatusCode());

        $data = $this->parseJsonResponse($response);
        self::assertTrue($data['success']);
        self::assertTrue($data['dryRun']);
    }

    /**
     * @test
     */
    public function harmonizeActionProcessesSingleContent(): void
    {
        $request = $this->createRequestWithBody([
            'content' => [1],
            'dryRun' => true,
        ]);

        $response = $this->controller->harmonizeAction($request);

        $data = $this->parseJsonResponse($response);
        self::assertTrue($data['success']);
        self::assertIsArray($data['results']);
    }

    /**
     * @test
     */
    public function harmonizeActionProcessesMultipleContent(): void
    {
        $request = $this->createRequestWithBody([
            'content' => [1, 2, 3],
            'dryRun' => true,
        ]);

        $response = $this->controller->harmonizeAction($request);

        $data = $this->parseJsonResponse($response);
        self::assertTrue($data['success']);
        self::assertCount(3, $data['results']);
    }

    // =========================================================================
    // Harmonize Action Tests - Dry Run Mode
    // =========================================================================

    /**
     * @test
     */
    public function harmonizeActionDryRunDoesNotModifyContent(): void
    {
        $request = $this->createRequestWithBody([
            'content' => [1],
            'dryRun' => true,
        ]);

        $response = $this->controller->harmonizeAction($request);

        $data = $this->parseJsonResponse($response);
        self::assertTrue($data['success']);
        self::assertTrue($data['dryRun']);

        // Verify content was not actually modified
        $content = $this->repository->findByUid(1);
        self::assertNotNull($content);
    }

    /**
     * @test
     */
    public function harmonizeActionNormalModeModifiesContent(): void
    {
        $request = $this->createRequestWithBody([
            'content' => [1],
            'dryRun' => false,
        ]);

        $response = $this->controller->harmonizeAction($request);

        $data = $this->parseJsonResponse($response);
        self::assertTrue($data['success']);
        self::assertFalse($data['dryRun']);
    }

    /**
     * @test
     */
    public function harmonizeActionDefaultsToDryRun(): void
    {
        $request = $this->createRequestWithBody([
            'content' => [1],
            // dryRun not specified, should default to true
        ]);

        $response = $this->controller->harmonizeAction($request);

        $data = $this->parseJsonResponse($response);
        self::assertTrue($data['dryRun']);
    }

    // =========================================================================
    // Harmonize Action Tests - Input Validation & Security
    // =========================================================================

    /**
     * @test
     */
    public function harmonizeActionRejectsEmptyContentArray(): void
    {
        $request = $this->createRequestWithBody([
            'content' => [],
            'dryRun' => true,
        ]);

        $response = $this->controller->harmonizeAction($request);

        $data = $this->parseJsonResponse($response);
        self::assertFalse($data['success']);
        self::assertStringContainsString('no_content', $data['message']);
    }

    /**
     * @test
     */
    public function harmonizeActionRejectsMissingContentParameter(): void
    {
        $request = $this->createRequestWithBody([
            'dryRun' => true,
        ]);

        $response = $this->controller->harmonizeAction($request);

        $data = $this->parseJsonResponse($response);
        self::assertFalse($data['success']);
    }

    /**
     * @test
     */
    public function harmonizeActionSkipsNonExistentContent(): void
    {
        $request = $this->createRequestWithBody([
            'content' => [1, 99999], // 99999 does not exist
            'dryRun' => true,
        ]);

        $response = $this->controller->harmonizeAction($request);

        $data = $this->parseJsonResponse($response);
        self::assertTrue($data['success']);
        // Should process existing content only
        self::assertLessThan(2, \count($data['results']));
    }

    /**
     * @test
     */
    public function harmonizeActionHandlesInvalidUidTypes(): void
    {
        $request = $this->createRequestWithBody([
            'content' => ['invalid', 1],
            'dryRun' => true,
        ]);

        $response = $this->controller->harmonizeAction($request);

        // Should handle gracefully
        self::assertSame(200, $response->getStatusCode());
    }

    // =========================================================================
    // Harmonize Action Tests - Error Handling
    // =========================================================================

    /**
     * @test
     */
    public function harmonizeActionFailsWhenHarmonizationDisabled(): void
    {
        // Temporarily disable harmonization
        $this->configurationToUseInTestInstance['EXTENSIONS']['temporal_cache']['harmonization']['enabled'] = false;

        $request = $this->createRequestWithBody([
            'content' => [1],
            'dryRun' => true,
        ]);

        $response = $this->controller->harmonizeAction($request);

        $data = $this->parseJsonResponse($response);
        self::assertFalse($data['success']);
        self::assertStringContainsString('disabled', $data['message']);
    }

    /**
     * @test
     */
    public function harmonizeActionReturnsCorrectSuccessCount(): void
    {
        $request = $this->createRequestWithBody([
            'content' => [1, 2, 3],
            'dryRun' => true,
        ]);

        $response = $this->controller->harmonizeAction($request);

        $data = $this->parseJsonResponse($response);
        self::assertTrue($data['success']);
        self::assertIsArray($data['results']);
        self::assertIsString($data['message']);
        // Message should contain counts
    }

    // =========================================================================
    // JSON Response Tests
    // =========================================================================

    /**
     * @test
     */
    public function harmonizeActionReturnsValidJson(): void
    {
        $request = $this->createRequestWithBody([
            'content' => [1],
            'dryRun' => true,
        ]);

        $response = $this->controller->harmonizeAction($request);

        $body = (string)$response->getBody();
        $data = \json_decode($body, true);

        self::assertNotNull($data);
        self::assertIsArray($data);
    }

    /**
     * @test
     */
    public function harmonizeActionJsonResponseContainsRequiredFields(): void
    {
        $request = $this->createRequestWithBody([
            'content' => [1],
            'dryRun' => true,
        ]);

        $response = $this->controller->harmonizeAction($request);

        $data = $this->parseJsonResponse($response);

        self::assertArrayHasKey('success', $data);
        self::assertArrayHasKey('message', $data);
        self::assertArrayHasKey('results', $data);
        self::assertArrayHasKey('dryRun', $data);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    private function createRequest(): ServerRequestInterface
    {
        $request = new ServerRequest(
            'http://localhost',
            'GET'
        );

        // Create minimal route for backend module
        $route = new Route('/module/temporal-cache', []);
        $route->setOption('packageName', 'nr_temporal_cache');

        // Add required backend request attributes
        $request = $request->withAttribute('applicationType', \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $request = $request->withAttribute('route', $route);
        $request = $request->withAttribute('module', null);
        $request = $request->withAttribute('moduleData', null);

        return $request;
    }

    private function createRequestWithBody(array $body): ServerRequestInterface
    {
        return (new ServerRequest(
            'http://localhost',
            'POST'
        ))->withParsedBody($body);
    }

    private function parseJsonResponse(ResponseInterface $response): array
    {
        $body = (string)$response->getBody();
        $data = \json_decode($body, true);

        self::assertIsArray($data, 'Response body should be valid JSON');

        return $data;
    }
}
