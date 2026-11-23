<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Unit\Controller;

use Codilar\LoyaltyWallet\Api\PointsManagementInterface;
use Codilar\LoyaltyWallet\Controller\Account\Convert;
use Codilar\LoyaltyWallet\Model\Config;
use Codilar\LoyaltyWallet\Model\Points\ConversionResult;
use Codilar\LoyaltyWallet\Model\Storefront\Money;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Security properties of the convert endpoint: it only ever acts for the logged-in customer, validates its input
 * and never trusts the request for identity.
 */
class ConvertTest extends TestCase
{
    private const KEY = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private array $params = [];
    private MockObject $session;
    private MockObject $points;
    private MockObject $messages;
    private MockObject $validator;
    private MockObject $logger;
    private Convert $controller;
    private array $redirects = [];

    protected function setUp(): void
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(fn ($k) => $this->params[$k] ?? null);
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturnCallback(function () {
            $redirect = $this->createStub(Redirect::class);
            $redirect->method('setPath')->willReturnCallback(function ($path) use ($redirect) {
                $this->redirects[] = $path;
                return $redirect;
            });
            return $redirect;
        });
        $this->session = $this->createMock(Session::class);
        $this->points = $this->createMock(PointsManagementInterface::class);
        $this->messages = $this->createMock(ManagerInterface::class);
        $this->validator = $this->createMock(Validator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $money = $this->createStub(Money::class);
        $money->method('format')->willReturnCallback(fn ($a) => '₹' . $a);

        $this->controller = new Convert(
            $request,
            $redirectFactory,
            $this->messages,
            $this->session,
            $this->validator,
            $this->points,
            $config,
            $money,
            $this->logger
        );
    }

    private function loggedInAs(int $id, string $email = 'me@example.com'): void
    {
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getId')->willReturn((string)$id);
        $customer->method('getEmail')->willReturn($email);
        $this->session->method('isLoggedIn')->willReturn(true);
        $this->session->method('getCustomerDataObject')->willReturn($customer);
    }

    public function testGuestsAreSentToLoginAndNothingIsConverted(): void
    {
        $this->session->method('isLoggedIn')->willReturn(false);
        $this->points->expects($this->never())->method('convertToWallet');
        $this->params = ['points' => '10', 'request_key' => self::KEY];
        $this->controller->execute();
        $this->assertSame(['customer/account/login'], $this->redirects);
    }

    public function testTheCustomerIsTakenFromTheSessionNeverFromTheRequest(): void
    {
        $this->loggedInAs(5);
        $this->params = ['points' => '10', 'request_key' => self::KEY, 'customer_id' => '999', 'email' => 'x@evil.test'];
        $this->points->expects($this->once())->method('convertToWallet')
            ->with(5, 'me@example.com', 10, 'c5-' . self::KEY)
            ->willReturn(new ConversionResult(10, '1.0000', 0, '1.0000'));
        $this->controller->execute();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badPoints')]
    public function testBadPointValuesNeverReachTheService(mixed $value): void
    {
        $this->loggedInAs(5);
        $this->params = ['points' => $value, 'request_key' => self::KEY];
        $this->points->expects($this->never())->method('convertToWallet');
        $this->messages->expects($this->once())->method('addErrorMessage');
        $this->controller->execute();
    }

    public static function badPoints(): array
    {
        return [[''], ['0'], ['-5'], ['abc'], ['1.5'], ['1e9'], ['9999999999'], [' 5'], ['5; DROP TABLE x'], [null]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badKeys')]
    public function testBadRequestKeysNeverReachTheService(mixed $key): void
    {
        $this->loggedInAs(5);
        $this->params = ['points' => '10', 'request_key' => $key];
        $this->points->expects($this->never())->method('convertToWallet');
        $this->controller->execute();
        $this->assertSame(['loyalty/account'], $this->redirects);
    }

    public static function badKeys(): array
    {
        return [[''], [null], ['short'], [str_repeat('z', 32)], [self::KEY . 'ff'], ['../../etc/passwd']];
    }

    public function testBusinessErrorsAreShownAndUnexpectedOnesAreLoggedNotLeaked(): void
    {
        $this->loggedInAs(5);
        $this->params = ['points' => '10', 'request_key' => self::KEY];
        $this->points->method('convertToWallet')->willReturnOnConsecutiveCalls(
            $this->throwException(new LocalizedException(__('You only have 3 points.'))),
            $this->throwException(new \RuntimeException('SQLSTATE[HY000] secret table detail'))
        );
        $shown = [];
        $this->messages->method('addErrorMessage')->willReturnCallback(function ($m) use (&$shown) {
            $shown[] = (string)$m;
            return $this->messages;
        });
        $this->logger->expects($this->once())->method('error');

        $this->controller->execute();
        $this->controller->execute();

        $this->assertSame('You only have 3 points.', $shown[0]);
        $this->assertStringNotContainsString('SQLSTATE', $shown[1]);
    }

    public function testCsrfIsDecidedByTheFormKeyValidator(): void
    {
        $request = $this->createStub(RequestInterface::class);
        $this->validator->expects($this->exactly(2))->method('validate')->willReturnOnConsecutiveCalls(true, false);
        $this->assertTrue($this->controller->validateForCsrf($request));
        $this->assertFalse($this->controller->validateForCsrf($request));
    }
}
