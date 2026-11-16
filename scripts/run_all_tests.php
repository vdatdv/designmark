<?php
// scripts/run_all_tests.php - Unified Test Runner for DesignMark Test Suites

$phpBin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : '/usr/local/bin/php';

$testFiles = [
    'tests/test_topic_15_mandatory.php',
    'tests/test_account_standardization.php',
    'tests/test_account_system_v2.php',
    'tests/test_admin_observer.php',
    'tests/test_trash_and_password.php',
    'tests/test_large_scale_annotations.php',
    'tests/test_reactions_and_notifications.php',
    'tests/test_numbered_pagination.php',
    'tests/test_new_features.php'
];

echo "====================================================================\n";
echo "🚀 DESIGNMARK AUTOMATED TEST SUITE RUNNER — KIỂM THỬ ĐỘ TIN CẬY CAO\n";
echo "====================================================================\n\n";

$summary = [];

foreach ($testFiles as $idx => $test) {
    echo "▶ [" . ($idx + 1) . "/" . count($testFiles) . "] CHẠY BỘ KIỂM THỬ: {$test}\n";
    $startTime = microtime(true);
    
    $filePath = __DIR__ . '/../' . $test;
    if (!file_exists($filePath)) {
        echo "❌ Tệp kiểm thử không tồn tại: {$filePath}\n\n";
        $summary[] = [
            'file' => $test,
            'passed' => false,
            'exitCode' => 127,
            'time' => 0
        ];
        continue;
    }

    $cmd = escapeshellcmd($phpBin) . ' ' . escapeshellarg($filePath);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];
    $process = proc_open($cmd, $descriptors, $pipes);
    
    $output = '';
    $errOutput = '';
    $exitCode = -1;
    
    if (is_resource($process)) {
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $errOutput = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
    }
    
    $elapsed = round(microtime(true) - $startTime, 2);
    
    echo $output;
    if ($errOutput) {
        echo "\n[STDERR]:\n" . $errOutput . "\n";
    }
    echo "\n";
    
    $hasFailureString = (strpos($output, 'SOME TESTS FAILED') !== false || 
                         strpos($output, 'SOME TEST CASES FAILED') !== false || 
                         strpos($output, 'FAILED:') !== false ||
                         strpos($output, '✘ CHƯA ĐẠT') !== false);
    
    $passed = ($exitCode === 0 && !$hasFailureString && trim($output) !== '');
    
    $summary[] = [
        'file' => $test,
        'passed' => $passed,
        'exitCode' => $exitCode,
        'time' => $elapsed
    ];
}

echo "\n====================================================================\n";
echo "📊 BẢNG TỔNG HỢP KẾT QUẢ KIỂM THỬ TOÀN DIỆN\n";
echo "====================================================================\n";

$totalPass = 0;
foreach ($summary as $s) {
    $statusText = $s['passed'] ? "\033[32m✔ ĐẠT (PASSED)\033[0m" : "\033[31m✘ KHÔNG ĐẠT (FAILED - Exit: " . $s['exitCode'] . ")\033[0m";
    echo sprintf(" - %-45s: %s (Thời gian: %.2fs)\n", $s['file'], $statusText, $s['time']);
    if ($s['passed']) $totalPass++;
}

echo "====================================================================\n";
if ($totalPass === count($summary)) {
    echo "\033[32m🎉 TẤT CẢ CÁC BỘ TEST ĐỀU VƯỢT QUA 100% THÀNH CÔNG RỰC RỠ!\033[0m\n";
    echo "====================================================================\n";
    exit(0);
} else {
    echo "\033[31m⚠️ VẪN CÒN BỘ TEST CHƯA ĐẠT.\033[0m\n";
    echo "====================================================================\n";
    exit(1);
}

