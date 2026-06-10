<?php
/**
 * 批量导入指南
 */
require_once __DIR__ . '/common/session.php';
require_once __DIR__ . '/../common/db.php';
require_once __DIR__ . '/../common/functions.php';

checkAdminLogin();

global $pdo;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批量导入指南</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .admin-layout {
            display: flex;
            min-height: calc(100vh - 40px);
        }
        .main-content {
            flex: 1;
            margin-left: 250px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 30px;
        }
        .section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .section h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .download-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 8px 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .highlight {
            background: #fff3cd;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
        }
        .step h3 {
            color: #667eea;
            margin-bottom: 10px;
        }
        code {
            background: #f1f3f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'common/sidebar.php'; ?>
        <div class="main-content">
            <div class="container">
                <h1 class="page-title">批量导入指南</h1>

                <div class="section">
                    <h2>📋 支持的导入类型</h2>
                    <p>当前系统支持以下类型的素材批量导入：</p>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>单视频素材</li>
                        <li>图片+文案素材</li>
                        <li>视频+文案素材</li>
                        <li>纯文案素材</li>
                    </ul>
                </div>

                <div class="section">
                    <h2>📥 下载导入模板</h2>
                    <p>下载对应的Excel模板，填写素材信息后上传：</p>

                    <a href="download-template.php?type=video" class="download-btn">下载单视频模板</a>
                    <a href="download-template.php?type=image_text" class="download-btn">下载图文模板</a>
                    <a href="download-template.php?type=video_text" class="download-btn">下载视频文案模板</a>
                    <a href="download-template.php?type=text" class="download-btn">下载文案模板</a>
                </div>

                <div class="section">
                    <h2>📝 模板格式说明</h2>

                    <h3>单视频素材模板</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>字段名</th>
                                <th>必填</th>
                                <th>说明</th>
                                <th>示例</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>name</td>
                                <td>是</td>
                                <td>视频名称</td>
                                <td>示例视频标题</td>
                            </tr>
                            <tr>
                                <td>video_url</td>
                                <td>是</td>
                                <td>视频文件URL</td>
                                <td>https://example.com/video.mp4</td>
                            </tr>
                            <tr>
                                <td>thumbnail_url</td>
                                <td>否</td>
                                <td>缩略图URL</td>
                                <td>https://example.com/thumb.jpg</td>
                            </tr>
                            <tr>
                                <td>category_names</td>
                                <td>否</td>
                                <td>分类名称（多个用逗号分隔）</td>
                                <td>风景,旅行</td>
                            </tr>
                            <tr>
                                <td>status</td>
                                <td>否</td>
                                <td>状态（1=上架，0=下架）</td>
                                <td>1</td>
                            </tr>
                        </tbody>
                    </table>

                    <h3>图片+文案素材模板</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>字段名</th>
                                <th>必填</th>
                                <th>说明</th>
                                <th>示例</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>content</td>
                                <td>是</td>
                                <td>文案内容</td>
                                <td>这是一个美妙的风景图片</td>
                            </tr>
                            <tr>
                                <td>image_urls</td>
                                <td>是</td>
                                <td>图片URL（多个用逗号分隔）</td>
                                <td>https://example.com/img1.jpg,https://example.com/img2.jpg</td>
                            </tr>
                            <tr>
                                <td>category_names</td>
                                <td>否</td>
                                <td>分类名称（多个用逗号分隔）</td>
                                <td>风景,旅行</td>
                            </tr>
                            <tr>
                                <td>status</td>
                                <td>否</td>
                                <td>状态（1=上架，0=下架）</td>
                                <td>1</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="section">
                    <h2>🚀 导入步骤</h2>

                    <div class="step">
                        <h3>步骤1：下载模板</h3>
                        <p>点击上方按钮下载对应类型的Excel模板文件。</p>
                    </div>

                    <div class="step">
                        <h3>步骤2：填写数据</h3>
                        <p>在Excel文件中填写素材信息，注意：</p>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li>必填字段不能为空</li>
                            <li>URL字段必须为完整的http/https链接</li>
                            <li>多个分类或图片用英文逗号分隔</li>
                            <li>状态字段只能填0或1</li>
                        </ul>
                    </div>

                    <div class="step">
                        <h3>步骤3：上传文件</h3>
                        <p>前往对应的素材管理页面，点击"批量导入"按钮，选择填写好的Excel文件上传。</p>
                    </div>

                    <div class="step">
                        <h3>步骤4：验证和确认</h3>
                        <p>系统会验证数据格式并显示预览，确认无误后点击确认导入。</p>
                    </div>
                </div>

                <div class="section">
                    <h2>⚠️ 注意事项</h2>
                    <div class="highlight">
                        <ul style="margin: 0; padding-left: 20px;">
                            <li>导入前请确保所有URL链接可正常访问</li>
                            <li>分类名称必须已存在于系统中，否则将自动创建</li>
                            <li>导入大量数据时请耐心等待，不要关闭浏览器</li>
                            <li>建议先导入少量数据测试，确认无误后再大量导入</li>
                            <li>导入过程中如有错误，系统会显示详细错误信息</li>
                        </ul>
                    </div>
                </div>

                <div class="section">
                    <h2>📞 技术支持</h2>
                    <p>如果在使用过程中遇到问题，请：</p>
                    <ol style="margin: 10px 0; padding-left: 20px;">
                        <li>查看浏览器控制台的错误信息</li>
                        <li>检查Excel文件格式是否符合要求</li>
                        <li>确认网络连接正常</li>
                        <li>联系技术支持团队</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
