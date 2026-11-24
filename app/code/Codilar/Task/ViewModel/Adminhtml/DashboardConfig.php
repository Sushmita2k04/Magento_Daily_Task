<?php
declare(strict_types=1);

namespace Codilar\Task\ViewModel\Adminhtml;

use Codilar\Task\Api\Data\TaskInterface;
use Codilar\Task\Model\Dashboard\DashboardFilter;
use Codilar\Task\Model\Dashboard\DashboardFilterParser;
use Codilar\Task\Model\Dashboard\DashboardService;
use Codilar\Task\Model\Source\Priority;
use Codilar\Task\Model\Source\Status;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Everything the dashboard page needs: filter options, colours, texts, URLs and the first data set.
 * The template only prints the JSON this class returns, so no logic lives in the template.
 */
class DashboardConfig implements ArgumentInterface
{
    /**
     * Single source of the dashboard colours (the CSS uses the same values).
     */
    public const STATUS_COLORS = [
        TaskInterface::STATUS_PENDING => '#f59e0b',
        TaskInterface::STATUS_IN_PROGRESS => '#6366f1',
        TaskInterface::STATUS_COMPLETED => '#22c55e',
    ];
    public const PRIORITY_COLORS = [
        TaskInterface::PRIORITY_LOW => '#94a3b8',
        TaskInterface::PRIORITY_MEDIUM => '#3b82f6',
        TaskInterface::PRIORITY_HIGH => '#ef4444',
    ];
    public const OVERDUE_COLOR = '#ef4444';
    public const JS_COMPONENT = 'Codilar_Task/js/dashboard';

    /**
     * @param RequestInterface $request
     * @param DashboardFilterParser $filterParser
     * @param DashboardService $dashboardService
     * @param Status $statusSource
     * @param Priority $prioritySource
     * @param UrlInterface $urlBuilder
     * @param AuthorizationInterface $authorization
     * @param Json $json
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly DashboardFilterParser $filterParser,
        private readonly DashboardService $dashboardService,
        private readonly Status $statusSource,
        private readonly Priority $prioritySource,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization,
        private readonly Json $json
    ) {
    }

    /**
     * JSON string for the page container (escape it with escapeHtmlAttr when printing).
     *
     * @return string
     */
    public function getConfigJson(): string
    {
        return (string) $this->json->serialize($this->getConfig());
    }

    /**
     * Value for the container's data-mage-init attribute: starts the dashboard script with the page config.
     *
     * @return string
     */
    public function getMageInitJson(): string
    {
        return (string) $this->json->serialize([self::JS_COMPONENT => $this->getConfig()]);
    }

    /**
     * Page configuration including the first data set (so the page needs no extra request to paint).
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $initialError = null;
        try {
            $filter = $this->filterParser->parse((array) $this->request->getParams());
        } catch (InputException $e) {
            // A bad bookmarked URL falls back to "no filter" and tells the user why.
            $filter = new DashboardFilter();
            $messages = [];
            foreach ($e->getErrors() ?: [$e] as $error) {
                $messages[] = $error->getMessage();
            }
            $initialError = implode(' ', $messages);
        }

        return [
            'dataUrl' => $this->urlBuilder->getUrl('codilar_task/dashboard/data'),
            'gridUrl' => $this->authorization->isAllowed('Codilar_Task::task_view')
                ? $this->urlBuilder->getUrl('codilar_task/task')
                : null,
            'initialError' => $initialError,
            'options' => [
                'status' => $this->options($this->statusSource->getOptionArray(), self::STATUS_COLORS),
                'priority' => $this->options($this->prioritySource->getOptionArray(), self::PRIORITY_COLORS),
                'dateField' => [
                    ['key' => DashboardFilter::FIELD_CREATED, 'label' => (string) __('Created date')],
                    ['key' => DashboardFilter::FIELD_DUE, 'label' => (string) __('Due date')],
                ],
            ],
            'colors' => ['overdue' => self::OVERDUE_COLOR],
            'texts' => $this->getTexts(),
            'data' => $this->dashboardService->getDashboard($filter),
        ];
    }

    /**
     * Filter options with labels and colours.
     *
     * @param array $labels value => label
     * @param array $colors value => colour
     * @return array<int, array{key: int, label: string, color: string}>
     */
    private function options(array $labels, array $colors): array
    {
        $options = [];
        foreach ($labels as $key => $label) {
            $options[] = ['key' => (int) $key, 'label' => (string) $label, 'color' => $colors[$key]];
        }
        return $options;
    }

    /**
     * Texts used by the browser code (translated here, so the JS needs no translation files).
     *
     * @return array<string, string>
     */
    private function getTexts(): array
    {
        return [
            'tasks' => (string) __('tasks'),
            'noTasks' => (string) __('No tasks match the selected filters.'),
            'noOverdue' => (string) __('No overdue tasks. Nice work!'),
            'noRecent' => (string) __('No tasks to show yet.'),
            'overdue' => (string) __('Overdue'),
            'due' => (string) __('Due'),
            'created' => (string) __('Created'),
            'loadError' => (string) __('The dashboard data could not be loaded. Please try again.'),
            'chartError' => (string) __('The chart could not be displayed.'),
            'allTime' => (string) __('All time'),
            'last7' => (string) __('Last 7 days'),
            'last30' => (string) __('Last 30 days'),
            'thisMonth' => (string) __('This month'),
            'ofTotal' => (string) __('of total'),
            'dayLate' => (string) __('day late'),
            'daysLate' => (string) __('days late'),
            'badRange' => (string) __('The start date must not be later than the end date.'),
        ];
    }
}
