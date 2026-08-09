<?php
// 诊断脚本 - 检查所有可能导致上传500错误的因素

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 图片上传500错误诊断</h1>";
echo "<hr>";

// 1. 检查PHP版本
echo "<h2>1. PHP版本</h2>";
echo "<p>PHP " . phpversion() . "</p>";

// 2. 检查open_basedir
echo "<h2>2. open_basedir配置</h2>";
$openBasedir = ini_get('open_basedir');
echo "<p>当前值: <code>" . ($openBasedir ?: '未设置') . "</code></p>";

if ($openBasedir) {
    $paths = explode(';', $openBasedir);
    echo "<ul>";
    foreach ($paths as $path) {
        $path = trim($path);
        if ($path) {
            $exists = is_dir($path) ? '✓ 存在' : '✗ 不存在';
            $writable = is_writable($path) ? '✓ 可写' : '✗ 不可写';
            echo "<li>$path - $exists, $writable</li>";
        }
    }
    echo "</ul>";
}

// 3. 检查临时目录
echo "<h2>3. 临时目录</h2>";
$tempDir = sys_get_temp_dir();
echo "<p>临时目录: <code>$tempDir</code></p>";
echo "<p>存在: " . (is_dir($tempDir) ? '✓' : '✗') . "</p>";
echo "<p>可写: " . (is_writable($tempDir) ? '✓' : '✗') . "</p>";

// 4. 测试创建临时文件
echo "<h2>4. 测试临时文件操作</h2>";
$testFile = tempnam($tempDir, 'upload_test_');
if ($testFile && file_exists($testFile)) {
    echo "<p style='color:green;'>✓ 临时文件创建成功: $testFile</p>";
    
    // 测试写入
    if (file_put_contents($testFile, 'test content') !== false) {
        echo "<p style='color:green;'>✓ 文件写入成功</p>";
    } else {
        echo "<p style='color:red;'>✗ 文件写入失败</p>";
    }
    
    // 测试读取
    $content = @file_get_contents($testFile);
    if ($content === 'test content') {
        echo "<p style='color:green;'>✓ 文件读取成功</p>";
    } else {
        echo "<p style='color:red;'>✗ 文件读取失败</p>";
    }
    
    // 测试删除
    if (@unlink($testFile)) {
        echo "<p style='color:green;'>✓ 文件删除成功</p>";
    } else {
        echo "<p style='color:red;'>✗ 文件删除失败</p>";
    }
} else {
    echo "<p style='color:red;'>✗ 临时文件创建失败</p>";
    echo "<p><strong>原因：</strong>open_basedir限制了临时目录访问</p>";
}

// 5. 检查uploads目录
echo "<h2>5. uploads目录</h2>";
$uploadDir = 'D:/phpEnv/www/xsy.duodao/public/uploads';
echo "<p>路径: <code>$uploadDir</code></p>";
echo "<p>存在: " . (is_dir($uploadDir) ? '✓' : '✗') . "</p>";
echo "<p>可写: " . (is_writable($uploadDir) ? '✓' : '✗') . "</p>";

// 6. 检查PHP扩展
echo "<h2>6. 文件上传相关配置</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>配置项</th><th>值</th></tr>";
echo "<tr><td>file_uploads</td><td>" . ini_get('file_uploads') . "</td></tr>";
echo "<tr><td>upload_max_filesize</td><td>" . ini_get('upload_max_filesize') . "</td></tr>";
echo "<tr><td>post_max_size</td><td>" . ini_get('post_max_size') . "</td></tr>";
echo "<tr><td>max_file_uploads</td><td>" . ini_get('max_file_uploads') . "</td></tr>";
echo "</table>";

// 7. 测试模拟上传
echo "<h2>7. 模拟文件上传测试</h2>";
$testUploadDir = $uploadDir . '/test_' . date('Ymd');
if (!is_dir($testUploadDir)) {
    @mkdir($testUploadDir, 0755, true);
}

$testSourceFile = tempnam($tempDir, 'sim_upload_');
file_put_contents($testSourceFile, 'simulated upload content');

$testTargetFile = $testUploadDir . '/test.txt';
if (@copy($testSourceFile, $testTargetFile)) {
    echo "<p style='color:green;'>✓ 文件复制成功（模拟上传）</p>";
    @unlink($testTargetFile);
} else {
    echo "<p style='color:red;'>✗ 文件复制失败</p>";
    echo "<p>错误信息: " . error_get_last()['message'] . "</p>";
}
@unlink($testSourceFile);

// 8. 总结
echo "<hr>";
echo "<h2>8. 诊断总结</h2>";

$allOk = true;
$checks = [
    '临时目录可写' => is_writable($tempDir),
    'uploads目录可写' => is_writable($uploadDir),
    'open_basedir包含临时目录' => (strpos($openBasedir, $tempDir) !== false)
];

foreach ($checks as $check => $result) {
    if ($result) {
        echo "<p style='color:green;'>✓ $check</p>";
    } else {
        echo "<p style='color:red;'>✗ $check</p>";
        $allOk = false;
    }
}

if ($allOk) {
    echo "<p style='color:green; font-size:18px;'><strong>✅ 所有检查通过！配置正确。</strong></p>";
    echo "<p>如果图片上传仍然500，请查看PHP错误日志获取详细错误信息。</p>";
} else {
    echo "<p style='color:red; font-size:18px;'><strong>❌ 发现问题！请修复上述标红的项。</strong></p>";
}

echo "<hr>";
echo "<p><a href='/shop/'>返回Shop后台</a></p>";
?>
