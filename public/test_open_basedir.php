<?php
echo "<h2>PHP配置测试</h2>";
echo "<p><strong>open_basedir:</strong> " . ini_get('open_basedir') . "</p>";
echo "<p><strong>临时目录:</strong> " . sys_get_temp_dir() . "</p>";
echo "<hr>";

// 测试写入临时文件
$tempFile = tempnam(sys_get_temp_dir(), 'test');
if ($tempFile && file_exists($tempFile)) {
    echo "<p style='color:green;'>✓ 临时文件创建成功: $tempFile</p>";
    echo "<p style='color:green;'>✓ open_basedir配置已生效！</p>";
    unlink($tempFile);
} else {
    echo "<p style='color:red;'>✗ 临时文件创建失败</p>";
    echo "<p style='color:red;'>✗ open_basedir配置未生效或仍有问题</p>";
}

echo "<hr>";
echo "<p><strong>下一步：</strong></p>";
echo "<ol>";
echo "<li>如果显示✓，请清除浏览器缓存并测试图片上传</li>";
echo "<li>如果显示✗，请完全重启PHP服务（先停止再启动）</li>";
echo "</ol>";
?>
