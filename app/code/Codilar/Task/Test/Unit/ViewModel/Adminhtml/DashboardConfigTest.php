<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\ViewModel\Adminhtml;

use Codilar\Task\Model\Dashboard\DashboardFilter;
use Codilar\Task\Model\Dashboard\DashboardFilterParser;
use Codilar\Task\Model\Dashboard\DashboardService;
use Codilar\Task\Model\Source\Priority;
use Codilar\Task\Model\Source\Status;
use Codilar\Task\ViewModel\Adminhtml\DashboardConfig;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class DashboardConfigTest extends TestCase
{
    /** @var DashboardFilterParser&MockObject */
    private $parser;
    /** @var DashboardService&MockObject */
    private $service;

    /**
     * @param bool $canViewGrid
     * @return DashboardConfig
     */
    private function config(bool $canViewGrid = true): DashboardConfig
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParams')->willReturn(['date_from' => '2026-01-01']);
        $this->parser = $this->createMock(DashboardFilterParser::class);
        $this->service = $this->createMock(DashboardService::class);
        $this->service->method('getDashboard')->willReturn(['summary' => ['total' => 1]]);
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(fn($route) => 'http://admin/' . $route);
        $auth = $this->createMock(AuthorizationInterface::class);
        $auth->method('isAllowed')->willReturn($canViewGrid);
        return new DashboardConfig(
            $request,
            $this->parser,
            $this->service,
            new Status(),
            new Priority(),
            $url,
            $auth,
            new Json()
        );
    }

    public function testConfigContainsUrlsOptionsAndFirstDataSet(): void
    {
        $config = $this->config();
        $this->parser->method('parse')->willReturn(new DashboardFilter());
        $c = $config->getConfig();

        $this->assertSame('http://admin/codilar_task/dashboard/data', $c['dataUrl']);
        $this->assertSame('http://admin/codilar_task/task', $c['gridUrl']);
        $this->assertNull($c['initialError']);
        $this->assertSame(['Pending', 'In Progress', 'Completed'], array_column($c['options']['status'], 'label'));
        $this->assertSame(['Low', 'Medium', 'High'], array_column($c['options']['priority'], 'label'));
        $this->assertSame(['created', 'due'], array_column($c['options']['dateField'], 'key'));
        $this->assertSame(['summary' => ['total' => 1]], $c['data']);
        foreach (array_merge($c['options']['status'], $c['options']['priority']) as $option) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $option['color']);
        }
    }

    public function testBadBookmarkedFiltersFallBackToNoFilterAndReportWhy(): void
    {
        $config = $this->config();
        $this->parser->method('parse')->willThrowException(new InputException(__('"date_from" is invalid.')));
        $c = $config->getConfig();

        $this->assertSame('"date_from" is invalid.', $c['initialError']);
        $this->assertSame(['summary' => ['total' => 1]], $c['data'], 'the page still shows unfiltered data');
    }

    public function testGridLinkIsHiddenWithoutViewPermission(): void
    {
        $config = $this->config(false);
        $this->parser->method('parse')->willReturn(new DashboardFilter());
        $this->assertNull($config->getConfig()['gridUrl']);
    }

    public function testJsonIsValidAndKeepsSpecialCharactersIntact(): void
    {
        $config = $this->config();
        $this->parser->method('parse')->willReturn(new DashboardFilter());
        $decoded = json_decode($config->getConfigJson(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('http://admin/codilar_task/dashboard/data', $decoded['dataUrl']);
    }

    public function testMageInitStartsTheDashboardScriptWithTheConfig(): void
    {
        $config = $this->config();
        $this->parser->method('parse')->willReturn(new DashboardFilter());
        $decoded = json_decode($config->getMageInitJson(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['Codilar_Task/js/dashboard'], array_keys($decoded));
        $this->assertSame('http://admin/codilar_task/dashboard/data', $decoded['Codilar_Task/js/dashboard']['dataUrl']);
    }

    public function testInitialErrorListsEveryProblemInsteadOfTheGenericMessage(): void
    {
        $config = $this->config();
        $e = new InputException(__('One or more input exceptions have occurred.'));
        $e->addError(__('"date_from" must be a valid date in Y-m-d format.'));
        $e->addError(__('"status" contains an invalid value.'));
        $this->parser->method('parse')->willThrowException($e);

        $this->assertSame(
            '"date_from" must be a valid date in Y-m-d format. "status" contains an invalid value.',
            $config->getConfig()['initialError']
        );
    }
}
