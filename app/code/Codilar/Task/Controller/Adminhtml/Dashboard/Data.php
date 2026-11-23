<?php
declare(strict_types=1);

namespace Codilar\Task\Controller\Adminhtml\Dashboard;

use Codilar\Task\Model\Dashboard\DashboardFilterParser;
use Codilar\Task\Model\Dashboard\DashboardService;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\InputException;
use Psr\Log\LoggerInterface;

/**
 * JSON data for the dashboard. Same permission as the page; filters are strictly validated
 * and invalid input returns HTTP 400 with readable messages (never SQL or stack traces).
 */
class Data extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Codilar_Task::task_dashboard';

    /**
     * @param Action\Context $context
     * @param JsonFactory $jsonFactory
     * @param DashboardFilterParser $filterParser
     * @param DashboardService $dashboardService
     * @param LoggerInterface $logger
     */
    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly DashboardFilterParser $filterParser,
        private readonly DashboardService $dashboardService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        /** @var Json $result */
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'no-store, max-age=0', true);

        try {
            $filter = $this->filterParser->parse((array) $this->getRequest()->getParams());
            return $result->setData(['success' => true, 'data' => $this->dashboardService->getDashboard($filter)]);
        } catch (InputException $e) {
            $messages = [];
            foreach ($e->getErrors() ?: [$e] as $error) {
                $messages[] = $error->getMessage();
            }
            return $result->setHttpResponseCode(400)->setData(['success' => false, 'errors' => $messages]);
        } catch (\Throwable $e) {
            $this->logger->error($e);
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'errors' => [(string) __('The dashboard data could not be loaded. Please try again.')],
            ]);
        }
    }
}
