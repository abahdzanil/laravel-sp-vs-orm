<?php
/**
 * Sequential timing benchmark: ORM vs SP.
 * Usage: php benchmark.php <base_url> <app_label> <db_name> <output_json_path>
 */

if ($argc < 5) {
    fwrite(STDERR, "Usage: php benchmark.php <base_url> <app_label> <db_name> <output_json_path>\n");
    exit(1);
}

[$_, $baseUrl, $label, $dbName, $outputPath] = $argv;

define('WARMUP', isset($argv[5]) ? (int) $argv[5] : 10);
define('ITERATIONS', isset($argv[6]) ? (int) $argv[6] : 100);

$pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname={$dbName};charset=utf8mb4", 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_COOKIEFILE => '', // enable in-memory cookie engine, no disk I/O
    CURLOPT_TIMEOUT => 15,
]);

function req($ch, string $method, string $url, ?array $fields = null): array
{
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPGET, false);
    if ($fields !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, null);
    }
    if ($method === 'GET') {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }

    $start = hrtime(true);
    $body = curl_exec($ch);
    $elapsedMs = (hrtime(true) - $start) / 1e6;
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($body === false) {
        fwrite(STDERR, "curl error on $method $url: " . curl_error($ch) . "\n");
        exit(1);
    }

    return ['body' => $body, 'code' => $code, 'ms' => $elapsedMs];
}

function extractToken(string $html): string
{
    if (preg_match('/name="_token" value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    fwrite(STDERR, "CSRF token not found in response\n");
    exit(1);
}

function stats(array $timings): array
{
    sort($timings);
    $n = count($timings);
    $mean = array_sum($timings) / $n;
    $variance = array_sum(array_map(fn($x) => ($x - $mean) ** 2, $timings)) / $n;
    $median = $n % 2 === 0
        ? ($timings[$n / 2 - 1] + $timings[$n / 2]) / 2
        : $timings[intdiv($n, 2)];
    $p95Index = (int) floor(0.95 * ($n - 1));

    return [
        'n' => $n,
        'mean_ms' => round($mean, 3),
        'median_ms' => round($median, 3),
        'stddev_ms' => round(sqrt($variance), 3),
        'min_ms' => round($timings[0], 3),
        'max_ms' => round($timings[$n - 1], 3),
        'p95_ms' => round($timings[$p95Index], 3),
    ];
}

function timedLoop($ch, int $warmup, int $iterations, callable $makeRequest): array
{
    for ($i = 0; $i < $warmup; $i++) {
        $makeRequest($i, true);
    }
    $timings = [];
    for ($i = 0; $i < $iterations; $i++) {
        $r = $makeRequest($i, false);
        $timings[] = $r['ms'];
        if ($r['code'] >= 500) {
            fwrite(STDERR, "Server error (HTTP {$r['code']}) on iteration $i\n");
        }
    }
    return stats($timings);
}

fwrite(STDERR, "[$label] Logging in...\n");
$loginPage = req($ch, 'GET', "$baseUrl/login");
$token = extractToken($loginPage['body']);
$loginResp = req($ch, 'POST', "$baseUrl/login", [
    '_token' => $token,
    'email' => 'parity.tester@example.com',
    'password' => 'password123',
]);
if ($loginResp['code'] !== 302) {
    fwrite(STDERR, "[$label] Login failed, HTTP {$loginResp['code']}\n");
    exit(1);
}

// Grab a durable CSRF token from an authenticated page for reuse in all subsequent mutations.
$createPage = req($ch, 'GET', "$baseUrl/products/create");
$formToken = extractToken($createPage['body']);

$results = [];
$runId = uniqid();

// ===================== INDEX =====================
fwrite(STDERR, "[$label] Benchmarking product index...\n");
$results['product_index'] = timedLoop($ch, WARMUP, ITERATIONS, function () use ($ch, $baseUrl) {
    return req($ch, 'GET', "$baseUrl/products");
});

fwrite(STDERR, "[$label] Benchmarking employee index...\n");
$results['employee_index'] = timedLoop($ch, WARMUP, ITERATIONS, function () use ($ch, $baseUrl) {
    return req($ch, 'GET', "$baseUrl/employees");
});

// ===================== CREATE =====================
fwrite(STDERR, "[$label] Benchmarking product create...\n");
$results['product_create'] = timedLoop($ch, WARMUP, ITERATIONS, function ($i, $isWarmup) use ($ch, $baseUrl, $formToken, $runId) {
    $sku = "BENCH-{$runId}-P-" . ($isWarmup ? "W$i" : $i);
    return req($ch, 'POST', "$baseUrl/products", [
        '_token' => $formToken,
        'name' => 'Benchmark Product',
        'sku' => $sku,
        'category' => 'Elektronik',
        'price' => 10000,
        'stock' => 1,
    ]);
});

fwrite(STDERR, "[$label] Benchmarking employee create...\n");
$results['employee_create'] = timedLoop($ch, WARMUP, ITERATIONS, function ($i, $isWarmup) use ($ch, $baseUrl, $formToken, $runId) {
    $email = "bench-{$runId}-e-" . ($isWarmup ? "w$i" : $i) . '@benchmark.test';
    return req($ch, 'POST', "$baseUrl/employees", [
        '_token' => $formToken,
        'name' => 'Benchmark Employee',
        'email' => $email,
        'position' => 'Staff',
        'department' => 'IT',
        'salary' => 5000000,
        'hire_date' => '2026-01-01',
    ]);
});

// ===================== EDIT / FIND =====================
fwrite(STDERR, "[$label] Benchmarking product edit (find)...\n");
$productId = (int) $pdo->query("SELECT id FROM products ORDER BY id ASC LIMIT 1")->fetchColumn();
$results['product_edit'] = timedLoop($ch, WARMUP, ITERATIONS, function () use ($ch, $baseUrl, $productId) {
    return req($ch, 'GET', "$baseUrl/products/{$productId}/edit");
});

fwrite(STDERR, "[$label] Benchmarking employee edit (find)...\n");
$employeeId = (int) $pdo->query("SELECT id FROM employees ORDER BY id ASC LIMIT 1")->fetchColumn();
$results['employee_edit'] = timedLoop($ch, WARMUP, ITERATIONS, function () use ($ch, $baseUrl, $employeeId) {
    return req($ch, 'GET', "$baseUrl/employees/{$employeeId}/edit");
});

// ===================== UPDATE =====================
fwrite(STDERR, "[$label] Benchmarking product update...\n");
$updateProductSku = "BENCH-{$runId}-UPDATE-TARGET-P";
req($ch, 'POST', "$baseUrl/products", [
    '_token' => $formToken, 'name' => 'Update Target', 'sku' => $updateProductSku,
    'category' => 'Elektronik', 'price' => 10000, 'stock' => 1,
]);
$updateProductId = (int) $pdo->query("SELECT id FROM products WHERE sku = " . $pdo->quote($updateProductSku))->fetchColumn();
$results['product_update'] = timedLoop($ch, WARMUP, ITERATIONS, function ($i) use ($ch, $baseUrl, $formToken, $updateProductId, $updateProductSku) {
    return req($ch, 'PUT', "$baseUrl/products/{$updateProductId}", [
        '_token' => $formToken,
        'name' => "Update Target $i",
        'sku' => $updateProductSku,
        'category' => 'Elektronik',
        'price' => 10000 + $i,
        'stock' => $i,
    ]);
});

fwrite(STDERR, "[$label] Benchmarking employee update...\n");
$updateEmployeeEmail = "bench-{$runId}-update-target@benchmark.test";
req($ch, 'POST', "$baseUrl/employees", [
    '_token' => $formToken, 'name' => 'Update Target', 'email' => $updateEmployeeEmail,
    'position' => 'Staff', 'department' => 'IT', 'salary' => 5000000, 'hire_date' => '2026-01-01',
]);
$updateEmployeeId = (int) $pdo->query("SELECT id FROM employees WHERE email = " . $pdo->quote($updateEmployeeEmail))->fetchColumn();
$results['employee_update'] = timedLoop($ch, WARMUP, ITERATIONS, function ($i) use ($ch, $baseUrl, $formToken, $updateEmployeeId, $updateEmployeeEmail) {
    return req($ch, 'PUT', "$baseUrl/employees/{$updateEmployeeId}", [
        '_token' => $formToken,
        'name' => "Update Target $i",
        'email' => $updateEmployeeEmail,
        'position' => 'Staff',
        'department' => 'IT',
        'salary' => 5000000 + $i,
        'hire_date' => '2026-01-01',
    ]);
});

// ===================== DESTROY =====================
fwrite(STDERR, "[$label] Seeding disposable rows for destroy benchmark...\n");
$destroyProductIds = [];
$stmt = $pdo->prepare("INSERT INTO products (name, sku, category, price, stock, created_at, updated_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
for ($i = 0; $i < WARMUP + ITERATIONS; $i++) {
    $sku = "BENCH-{$runId}-DESTROY-P-$i";
    $stmt->execute(['Destroy Target', $sku, 'Elektronik', 10000, 1]);
    $destroyProductIds[] = (int) $pdo->lastInsertId();
}
$results['product_destroy'] = timedLoop($ch, WARMUP, ITERATIONS, function ($i, $isWarmup) use ($ch, $baseUrl, $formToken, $destroyProductIds) {
    static $cursor = 0;
    $id = $destroyProductIds[$cursor++];
    return req($ch, 'DELETE', "$baseUrl/products/{$id}", ['_token' => $formToken]);
});

$destroyEmployeeIds = [];
$stmt = $pdo->prepare("INSERT INTO employees (name, email, position, department, salary, hire_date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
for ($i = 0; $i < WARMUP + ITERATIONS; $i++) {
    $email = "bench-{$runId}-destroy-e-$i@benchmark.test";
    $stmt->execute(['Destroy Target', $email, 'Staff', 'IT', 5000000, '2026-01-01']);
    $destroyEmployeeIds[] = (int) $pdo->lastInsertId();
}
$results['employee_destroy'] = timedLoop($ch, WARMUP, ITERATIONS, function ($i, $isWarmup) use ($ch, $baseUrl, $formToken, $destroyEmployeeIds) {
    static $cursor = 0;
    $id = $destroyEmployeeIds[$cursor++];
    return req($ch, 'DELETE', "$baseUrl/employees/{$id}", ['_token' => $formToken]);
});

// ===================== CLEANUP =====================
fwrite(STDERR, "[$label] Cleaning up benchmark rows...\n");
$pdo->exec("DELETE FROM products WHERE sku LIKE 'BENCH-{$runId}-%'");
$pdo->exec("DELETE FROM employees WHERE email LIKE 'bench-{$runId}-%'");

curl_close($ch);

file_put_contents($outputPath, json_encode($results, JSON_PRETTY_PRINT));
fwrite(STDERR, "[$label] Done. Results written to $outputPath\n");
