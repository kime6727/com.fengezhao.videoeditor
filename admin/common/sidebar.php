<?php
/**
 * 后台侧边栏导航组件
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$baseUrl = '/admin/';
?>
<style>
    .admin-layout {
        display: flex;
        min-height: calc(100vh - 40px);
        background: #f5f5f5;
    }
    .sidebar {
        width: 250px;
        background: #2c3e50;
        color: white;
        padding: 20px 0;
        box-shadow: 2px 0 4px rgba(0,0,0,0.1);
        position: fixed;
        height: calc(100vh - 40px);
        overflow-y: auto;
        z-index: 100;
    }
    .sidebar-header {
        padding: 0 20px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        margin-bottom: 20px;
    }
    .sidebar-header h2 {
        color: white;
        font-size: 20px;
        margin: 0;
    }
    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .sidebar-menu-item {
        margin: 0;
    }
    .sidebar-menu-item a {
        display: block;
        padding: 12px 20px;
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        transition: all 0.3s;
        border-left: 3px solid transparent;
    }
    .sidebar-menu-item a:hover {
        background: rgba(255,255,255,0.1);
        color: white;
    }
    .sidebar-menu-item.active a {
        background: rgba(103, 126, 234, 0.3);
        color: white;
        border-left-color: #667eea;
    }
    .sidebar-menu-section {
        padding: 10px 20px;
        font-size: 12px;
        color: rgba(255,255,255,0.5);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 10px;
    }
    .sidebar-menu-section:first-child {
        margin-top: 0;
    }
    .main-content {
        flex: 1;
        margin-left: 250px;
        padding: 20px;
    }
</style>
<div class="sidebar">
    <div class="sidebar-header">
        <h2>好素材后台</h2>
    </div>
    <ul class="sidebar-menu">
                  <li class="sidebar-menu-item <?php echo ($currentPage == 'index.php' && $currentDir == 'admin') ? 'active' : ''; ?>">
                      <a href="<?php echo $baseUrl; ?>index.php">首页</a>
                  </li>
                  <li class="sidebar-menu-item <?php echo ($currentPage == 'analytics.php' && $currentDir == 'admin') ? 'active' : ''; ?>">
                      <a href="<?php echo $baseUrl; ?>analytics.php">数据分析</a>
                  </li>
                  <li class="sidebar-menu-item <?php echo ($currentPage == 'batch-operations.php' && $currentDir == 'admin') ? 'active' : ''; ?>">
                      <a href="<?php echo $baseUrl; ?>batch-operations.php">批量操作</a>
                  </li>
                  <li class="sidebar-menu-item <?php echo ($currentPage == 'import-guide.php' && $currentDir == 'admin') ? 'active' : ''; ?>">
                      <a href="<?php echo $baseUrl; ?>import-guide.php">导入指南</a>
                  </li>
        <li class="sidebar-menu-section">素材管理</li>
        <li class="sidebar-menu-item <?php echo ($currentDir == 'video' && ($currentPage == 'list.php' || strpos($currentPage, 'video') !== false)) ? 'active' : ''; ?>">
            <a href="<?php echo $baseUrl; ?>video/list.php">单视频素材</a>
        </li>
        <li class="sidebar-menu-item <?php echo ($currentDir == 'image-text' && ($currentPage == 'list.php' || strpos($currentPage, 'image') !== false)) ? 'active' : ''; ?>">
            <a href="<?php echo $baseUrl; ?>image-text/list.php">图片+文案</a>
        </li>
        <li class="sidebar-menu-item <?php echo ($currentDir == 'video-text' && ($currentPage == 'list.php' || strpos($currentPage, 'video-text') !== false)) ? 'active' : ''; ?>">
            <a href="<?php echo $baseUrl; ?>video-text/list.php">视频+文案</a>
        </li>
        <li class="sidebar-menu-item <?php echo ($currentDir == 'text' && ($currentPage == 'list.php' || strpos($currentPage, 'text') !== false)) ? 'active' : ''; ?>">
            <a href="<?php echo $baseUrl; ?>text/list.php">纯文案</a>
        </li>
        <li class="sidebar-menu-section">内容管理</li>
        <li class="sidebar-menu-item <?php echo ($currentDir == 'category' && ($currentPage == 'list.php' || strpos($currentPage, 'category') !== false)) ? 'active' : ''; ?>">
            <a href="<?php echo $baseUrl; ?>category/list.php">分类管理</a>
        </li>
        <li class="sidebar-menu-item <?php echo ($currentDir == 'banner' && ($currentPage == 'list.php' || strpos($currentPage, 'banner') !== false)) ? 'active' : ''; ?>">
            <a href="<?php echo $baseUrl; ?>banner/list.php">Banner管理</a>
        </li>
        <li class="sidebar-menu-item <?php echo ($currentDir == 'agreement' && ($currentPage == 'list.php' || strpos($currentPage, 'agreement') !== false)) ? 'active' : ''; ?>">
            <a href="<?php echo $baseUrl; ?>agreement/list.php">链接管理</a>
        </li>
        <li class="sidebar-menu-section">用户管理</li>
        <li class="sidebar-menu-item <?php echo ($currentDir == 'user' && ($currentPage == 'list.php' || strpos($currentPage, 'user') !== false || strpos($currentPage, 'detail') !== false)) ? 'active' : ''; ?>">
            <a href="<?php echo $baseUrl; ?>user/list.php">用户列表</a>
        </li>
        <li class="sidebar-menu-item <?php echo ($currentDir == 'user' && strpos($currentPage, 'batch-create') !== false) ? 'active' : ''; ?>">
            <a href="<?php echo $baseUrl; ?>user/batch-create.php">批量创建用户</a>
        </li>
        <li class="sidebar-menu-item <?php echo ($currentDir == 'favorite' && ($currentPage == 'list.php' || strpos($currentPage, 'favorite') !== false)) ? 'active' : ''; ?>">
            <a href="<?php echo $baseUrl; ?>favorite/list.php">收藏管理</a>
        </li>
        <li class="sidebar-menu-item <?php echo ($currentDir == 'report' && ($currentPage == 'list.php' || strpos($currentPage, 'report') !== false || strpos($currentPage, 'detail') !== false)) ? 'active' : ''; ?>">
            <a href="<?php echo $baseUrl; ?>report/list.php">举报管理</a>
        </li>
        <li class="sidebar-menu-section">系统设置</li>
        <li class="sidebar-menu-item <?php echo ($currentDir == 'admin' && ($currentPage == 'list.php' || $currentPage == 'add.php')) ? 'active' : ''; ?>">
            <a href="<?php echo $baseUrl; ?>admin/list.php">管理员管理</a>
        </li>
        <li class="sidebar-menu-item <?php echo ($currentPage == 'system-config.php' && $currentDir == 'admin') ? 'active' : ''; ?>">
            <a href="<?php echo $baseUrl; ?>system-config.php">系统配置</a>
        </li>
        <li class="sidebar-menu-item <?php echo ($currentPage == 'change-password.php' && $currentDir == 'admin') ? 'active' : ''; ?>">
            <a href="<?php echo $baseUrl; ?>change-password.php">修改密码</a>
        </li>
        <li class="sidebar-menu-item">
            <a href="<?php echo $baseUrl; ?>logout.php">退出登录</a>
        </li>
    </ul>
</div>

