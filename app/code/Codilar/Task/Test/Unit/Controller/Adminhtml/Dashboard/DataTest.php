<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\Controller\Adminhtml\Dashboard;

use Codilar\Task\Controller\Adminhtml\Dashboard\Data;
use Codilar\Task\Controller\Adminhtml\Dashboard\Index;
use Codilar\Task\Model\Dashboard\DashboardFilter;
use Codilar\Task\Model\Dashboard\DashboardFilterParser;
use Codilar\Task\Model\Dashboard\DashboardService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\InputException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class DataTest extends TestCase
{
    /** @var Json&MockObject */
    private $json;
    /** @var DashboardFilterParser&MockObject */
    private $parser;
    /** @var DashboardService&MockObject */
    private $service;
    /** @var LoggerInterface&MockObject */
    private $logger;
    /** @var mixed */
    private $payload;
    /** @var int|null */
    private ?int $httpCode = null;

    /**
     * @return Data
     */
    private function controller(): Data
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParams')->willReturn(['status' => [1]]);
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);

        $this->json = $this->createMock(Json::class);
        $this->json->method('setHeader')->willReturnSelf();
        $this->json->method('setHttpResponseCode')->willReturnCallback(function ($code) {
            $this->httpCode = $code;
            return $this->json;
        });
        $this->json->method('setData')->willReturnCallback(function ($data) {
            $this->payload = $data;
            return $this->json;
        });
        $factory = $this->createMock(JsonFactory::class);
        $factory->method('create')->willReturn($this->json);

        $this->parser = $this->createMock(DashboardFilterParser::class);
        $this->service = $this->createMock(DashboardService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        return new Data($context, $factory, $this->parser, $this->service, $this->logger);
    }

    public function testSuccessReturnsDataAndIsNotCacheable(): void
    {
        $controller = $this->controller();
        $filter = new DashboardFilter();
        $this->parser->expects($this->once())->method('parse')->with(['status' => [1]])->willReturn($filter);
        $this->service->method('getDashboard')->with($filter)->willReturn(['summary' => ['total' => 3]]);
        $this->json->expects($this->once())->method('setHeader')->with('Cache-Control', 'no-store, max-age=0', true);

        $controller->execute();

        $this->assertSame(['success' => true, 'data' => ['summary' => ['total' => 3]]], $this->payload);
        $this->assertNull($this->httpCode, 'default 200');
    }

    public function testInvalidFilterReturns400WithReadableMessages(): void
    {
        $controller = $this->controller();
        $e = new InputException(__('Invalid'));
        $e->addError(__('"date_from" must be a valid date in Y-m-d format.'));
        $e->addError(__('"status" contains an invalid value.'));
        $this->parser->method('parse')->willThrowException($e);
        $this->service->expects($this->never())->method('getDashboard');

        $controller->execute();

        $this->assertSame(400, $this->httpCode);
        $this->assertFalse($this->payload['success']);
        $this->assertCount(2, $this->payload['errors']);
    }

    public function testUnexpectedFailureIsLoggedAndReturnsGeneric500(): void
    {
        $controller = $this->controller();
        $this->parser->method('parse')->willReturn(new DashboardFilter());
        $this->service->method('getDashboard')->willThrowException(new \RuntimeException('SQLSTATE secret internals'));
        $this->logger->expects($this->once())->method('error');

        $controller->execute();

        $this->assertSame(500, $this->httpCode);
        $this->assertStringNotContainsString('SQLSTATE', json_encode($this->payload));
    }

    public function testBothDashboardControllersRequireTheDashboardPermission(): void
    {
        $this->assertSame('Codilar_Task::task_dashboard', Data::ADMIN_RESOURCE);
        $this->assertSame('Codilar_Task::task_dashboard', Index::ADMIN_RESOURCE);
        $instance = (new \ReflectionClass(Data::class))->newInstanceWithoutConstructor();
        $this->assertInstanceOf(HttpGetActionInterface::class, $instance);
    }
}
