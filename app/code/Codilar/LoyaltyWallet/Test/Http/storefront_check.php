<?php
/**
 * Live storefront check (AC-07 and request security). Run against a dev store with the full page cache enabled:
 *
 *   php app/code/Codilar/LoyaltyWallet/Test/Http/storefront_check.php http://magento.local
 *
 * It creates two throw-away customers, drives the real site over HTTP, prints PASS/FAIL lines, and removes
 * everything it created. Exit code 1 if any check fails. Do not run against production.
 */
declare(strict_types=1);

use Codilar\LoyaltyWallet\Api\PointsManagementInterface;
use Codilar\LoyaltyWallet\Model\Wallet\QuoteWalletManager;
use Codilar\LoyaltyWallet\Model\Wallet\WalletMovement;
use Codilar\LoyaltyWallet\Model\Wallet\WalletService;
use Magento\Framework\App\Bootstrap;

require __DIR__ . '/../../../../../../app/bootstrap.php';
$base = rtrim($argv[1] ?? 'http://magento.local', '/');
$om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('frontend');
$conn = $om->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
$failures = 0;
$check = function (string $name, bool $ok, string $detail = '') use (&$failures) {
    $ok || $failures++;
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $name . ($ok || $detail === '' ? '' : "  [$detail]") . "\n";
};

/** Minimal cookie-keeping HTTP client. */
$http = function (array &$jar, string $method, string $path, array $post = [], array $headers = []) use ($base) {
    $ch = curl_init($base . $path);
    $cookie = implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($jar), $jar));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 120,
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => array_merge(['Cookie: ' . $cookie], $headers)]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($post) ? http_build_query($post) : $post);
    }
    $raw = (string)curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $head = substr($raw, 0, $headerSize);
    preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]*)/mi', $head, $m, PREG_SET_ORDER);
    foreach ($m as $c) {
        $jar[$c[1]] = $c[2];
    }
    preg_match('/^Location:\s*(\S+)/mi', $head, $loc);
    return ['status' => $status, 'body' => substr($raw, $headerSize), 'location' => $loc[1] ?? ''];
};

$ids = [];
$cleanup = function () use (&$ids, $conn) {
    foreach ($ids as $id) {
        $conn->delete('quote', ['customer_id = ?' => $id]);
        foreach (['codilar_loyalty_points_ledger', 'codilar_loyalty_points_lot', 'codilar_loyalty_points_balance',
                     'codilar_wallet_ledger', 'codilar_wallet_balance'] as $t) {
            $conn->delete($t, ['customer_id = ?' => $id]);
        }
        $conn->delete('customer_entity', ['entity_id = ?' => $id]);
    }
};
register_shutdown_function($cleanup);

try {
    // ---- two customers with recognisable balances
    $accounts = [];
    foreach (['a' => 'check-a', 'b' => 'check-b'] as $k => $name) {
        $pw = 'Ck!' . bin2hex(random_bytes(8)) . 'x1';
        $c = $om->get(\Magento\Customer\Api\Data\CustomerInterfaceFactory::class)->create()
            ->setEmail("loyalty-$name@example.com")->setFirstname('Check')->setLastname(strtoupper($k))
            ->setWebsiteId(1)->setStoreId(1)->setGroupId(1);
        $c = $om->get(\Magento\Customer\Api\AccountManagementInterface::class)->createAccount($c, $pw);
        $accounts[$k] = ['id' => (int)$c->getId(), 'email' => "loyalty-$name@example.com", 'pw' => $pw];
        $ids[] = (int)$c->getId();
    }
    $om->get(PointsManagementInterface::class)->accrue($accounts['a']['id'], $accounts['a']['email'], 991001, 'CHK-A', '777');
    $om->get(WalletService::class)->credit(new WalletMovement(
        $accounts['b']['id'], $accounts['b']['email'], 'CREDIT_MANUAL', '123.45', 'check', 'check:b', null, null, null, 'check'
    ));

    $login = function (array $acct) use ($http) {
        $jar = ['form_key' => 'CheckFormKey12345'];
        $http($jar, 'GET', '/customer/account/login/');
        $r = $http($jar, 'POST', '/customer/account/loginPost/', [
            'form_key' => 'CheckFormKey12345', 'login[username]' => $acct['email'], 'login[password]' => $acct['pw'],
        ]);
        return [$jar, $r];
    };
    [$jarA, $loginA] = $login($accounts['a']);
    [$jarB] = $login($accounts['b']);
    $guest = [];
    $section = fn (array &$jar) => json_decode($http(
        $jar, 'GET', '/customer/section/load/?sections=codilar_loyalty&force_new_section_timestamp=true', [],
        ['X-Requested-With: XMLHttpRequest']
    )['body'], true)['codilar_loyalty'] ?? [];

    // ---- AC-07: private sections are per customer; cacheable pages carry nobody's balance
    $a = $section($jarA);
    $b = $section($jarB);
    $check('customer A sees their own 777 points and no wallet', ($a['points'] ?? 0) === 777 && ($a['wallet'] ?? '') === '₹0.00', json_encode($a));
    $check('customer B sees their own ₹123.45 and no points', ($b['points'] ?? -1) === 0 && ($b['wallet'] ?? '') === '₹123.45', json_encode($b));
    $check('a guest gets no balance section', ($section($guest)['is_enabled'] ?? null) === false);

    // This module's data: no balance may ever be in a cacheable page. (Emails are checked separately below.)
    foreach (['/' => 'home', '/customer/account/login/' => 'login page'] as $path => $label) {
        foreach (['guest' => &$guest, 'A' => &$jarA, 'B' => &$jarB] as $who => &$jar) {
            $body = $http($jar, 'GET', $path)['body'];
            $check("$label as $who contains no wallet balance or points of A/B", !str_contains($body, '123.45') && !str_contains($body, '>777<'));
        }
        unset($jar);
    }
    // Another customer's identity must never appear in a page (full page cache + customer-specific markup).
    foreach (['/' => 'home'] as $path => $label) {
        $pa = $http($jarA, 'GET', $path)['body'];
        $pb = $http($jarB, 'GET', $path)['body'];
        $pg = $http($guest, 'GET', $path)['body'];
        $check("$label seen by B does not contain A's email", !str_contains($pb, $accounts['a']['email']));
        $check("$label seen by A does not contain B's email", !str_contains($pa, $accounts['b']['email']));
        $check("$label seen by a guest contains no customer email", !str_contains($pg, $accounts['a']['email']) && !str_contains($pg, $accounts['b']['email']));
    }
    // the same cached URL fetched twice by different sessions returns the same shell
    $h1 = md5(preg_replace('/form_key[^"\']*|nonce="[^"]*"/', '', $http($jarA, 'GET', '/')['body']));
    $h2 = md5(preg_replace('/form_key[^"\']*|nonce="[^"]*"/', '', $http($jarB, 'GET', '/')['body']));
    $check('home page HTML is identical for A and B (nothing personal is cached in it)', $h1 === $h2);

    // ---- access control
    $r = $http($guest, 'GET', '/loyalty/account/');
    $check('guest is redirected to login from the account page', $r['status'] === 302 && str_contains($r['location'], 'customer/account/login'), $r['location']);
    $pageA = $http($jarA, 'GET', '/loyalty/account/')['body'];
    $check("A's account page shows A's balance and not B's", str_contains($pageA, '777') && !str_contains($pageA, '123.45'));
    $r = $http($guest, 'POST', '/loyalty/account/convert/', ['points' => '10', 'request_key' => str_repeat('a', 32)]);
    $check('guest cannot post a conversion', in_array($r['status'], [302, 301], true));

    // ---- request security
    $fk = $jarA['form_key'];
    $key = bin2hex(random_bytes(16));
    $http($jarA, 'POST', '/loyalty/account/convert/', ['points' => '50', 'request_key' => $key, 'form_key' => 'wrong']);
    $check('conversion without a valid form key is refused', ($section($jarA)['points'] ?? 0) === 777);

    $http($jarA, 'POST', '/loyalty/account/convert/', [
        'points' => '50', 'request_key' => $key, 'form_key' => $fk, 'customer_id' => $accounts['b']['id'],
        'email' => $accounts['b']['email'],
    ]);
    $after = $section($jarA);
    $check('conversion by A uses A\'s account even when the request names B', ($after['points'] ?? 0) === 727 && ($after['wallet'] ?? '') === '₹5.00', json_encode($after));
    $check("B's balances are untouched by A's request", ($section($jarB)['wallet'] ?? '') === '₹123.45');
    $http($jarA, 'POST', '/loyalty/account/convert/', ['points' => '50', 'request_key' => $key, 'form_key' => $fk]);
    $check('re-posting the same form converts only once', ($section($jarA)['points'] ?? 0) === 727);
    $http($jarA, 'POST', '/loyalty/account/convert/', ['points' => '-5', 'request_key' => bin2hex(random_bytes(16)), 'form_key' => $fk]);
    $http($jarA, 'POST', '/loyalty/account/convert/', ['points' => '99999', 'request_key' => bin2hex(random_bytes(16)), 'form_key' => $fk]);
    $check('negative and over-balance conversions change nothing', ($section($jarA)['points'] ?? 0) === 727);

    // ---- wallet on a cart over HTTP and GraphQL
    $product = $om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class)->get('24-MB01');
    $http($jarB, 'POST', '/checkout/cart/add/', ['product' => (string)$product->getId(), 'qty' => '2', 'form_key' => $jarB['form_key']]);
    $http($jarB, 'POST', '/loyalty/wallet/apply/', ['wallet_mode' => 'all', 'form_key' => 'wrong']);
    $walletOnCart = fn () => (float)$conn->fetchOne(
        'SELECT codilar_wallet_amount FROM quote WHERE customer_id = ? AND is_active = 1',
        [$accounts['b']['id']]
    );
    $check('apply-wallet without a valid form key is refused', $walletOnCart() === 0.0);
    $http($jarB, 'POST', '/loyalty/wallet/apply/', ['wallet_mode' => 'all', 'form_key' => $jarB['form_key']]);
    $applied = $walletOnCart();
    $subtotal = (float)$conn->fetchOne(
        'SELECT subtotal FROM quote WHERE customer_id = ? AND is_active = 1',
        [$accounts['b']['id']]
    );
    $check('apply-wallet with a valid form key applies the capped amount', $applied > 0 && abs($applied - $subtotal / 2) < 0.01, "applied=$applied subtotal=$subtotal");

    $tokenCall = curl_init($base . '/rest/V1/integration/customer/token');
    curl_setopt_array($tokenCall, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['username' => $accounts['b']['email'], 'password' => $accounts['b']['pw']])]);
    $token = json_decode((string)curl_exec($tokenCall));
    if (is_string($token)) {
        $gq = curl_init($base . '/graphql');
        curl_setopt_array($gq, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
            CURLOPT_POSTFIELDS => json_encode(['query' => '{ customerCart { prices { subtotal_excluding_tax { value } grand_total { value } } } }'])]);
        $g = json_decode((string)curl_exec($gq), true);
        $prices = $g['data']['customerCart']['prices'] ?? null;
        $check(
            'GraphQL (used by the React checkout) reports the grand total reduced by the wallet',
            $prices !== null && $prices['grand_total']['value'] < $prices['subtotal_excluding_tax']['value'],
            json_encode($g)
        );
        echo '      GraphQL prices: ' . json_encode($prices) . "\n";
    } else {
        echo "SKIP  GraphQL check (could not obtain a customer token)\n";
    }
} finally {
    $cleanup();
    $ids = [];
}
echo $failures === 0 ? "\nAll checks passed.\n" : "\n$failures check(s) FAILED.\n";
exit($failures === 0 ? 0 : 1);
