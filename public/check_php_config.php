<?php
echo "<h1>PHP配置检查</h1>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><td><strong>open_basedir</strong></td><td>" . ini_get('open_basedir') . "</td></tr>";
echo "<tr><td><strong>临时目录</strong></td><td>" . sys_get_temp_dir() . "</td></tr>";
echo "<tr><td><strong>PHP版本</strong></td><td>" . phpversion() . "</td></tr>";
echo "<tr><td><strong>上传目录可写</strong></td><td>" . (is_writable('D:/phpEnv/www/xsy.duodao/public/uploads') ? '是' : '否') . "</td></tr>";
echo "</table>";
echo "<hr>";

// 测试创建临时文件
$tempDir = sys_get_temp_dir();
echo "<h2>测试临时文件</h2>";
echo "<p>临时目录: $tempDir</p>";

$tempFile = tempnam($tempDir, 'test_upload_');
if ($tempFile && file_exists($tempFile)) {
    echo "<p style='color:green;'>✓ 临时文件创建成功: $tempFile</p>";
    
    // 测试写入内容
    if (file_put_contents($tempFile, 'test content') !== false) {
        echo "<p style='color:green;'>✓ 文件写入成功</p>";
    } else {
        echo "<p style='color:red;'>✗ 文件写入失败</p>";
    }
    
    unlink($tempFile);
    echo "<p style='color:green;'>✓ open_basedir配置正确！</p>";
} else {
    echo "<p style='color:red;'>✗ 临时文件创建失败</p>";
    echo "<p style='color:red;'>✗ 错误: open_basedir限制生效</p>";
    echo "<p><strong>解决方案：</strong></p>";
    echo "<ol>";
    echo "<li>打开phpenv界面</li>";
    echo "<li>点击'停止服务'</li>";
    echo "<li>等待5秒</li>";
    echo "<li>点击'启动服务'</li>";
    echo "</ol>";
}
?>
