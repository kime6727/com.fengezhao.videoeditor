<?php
/**
 * 后台管理Session管理
 */

session_start();
require_once __DIR__ . '/../../common/db.php';

// 简单的管理员认证
function checkAdminLogin() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        // 获取当前脚本的目录，构建相对路径
        $loginPath = dirname($_SERVER['PHP_SELF']) . '/login.php';
        if (strpos($loginPath, '/admin/') === false) {
            $loginPath = '/admin/login.php';
        }
        header('Location: ' . $loginPath);
        exit;
    }
}

/**
 * 管理员登录验证
 * @param string $username 用户名
 * @param string $password 密码
 * @return bool 登录是否成功
 */
function adminLogin($username, $password) {
    global $pdo;
    $adminConfigFile = __DIR__ . '/admin_config.php';
    $configHash = null;

    if (file_exists($adminConfigFile)) {
        $config = include $adminConfigFile;
        if (is_array($config) && isset($config[$username])) {
            $configHash = $config[$username];
        }
    }

    try {
        if (isset($pdo)) {
            $checkTable = $pdo->query("SHOW TABLES LIKE 'admins'");
            $tableExists = $checkTable && $checkTable->rowCount() > 0;

            if ($tableExists) {
                $stmt = $pdo->prepare("SELECT `id`, `username`, `password`, `status` FROM `admins` WHERE `username` = ? AND `status` = 1");
                $stmt->execute([$username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin) {
                    $dbMatch = password_verify($password, $admin['password']);
                    $configMatch = $configHash && password_verify($password, $configHash);

                    if ($dbMatch || $configMatch) {
                        $lastLoginIp = $_SERVER['REMOTE_ADDR'] ?? '';
                        try {
                            $updateStmt = $pdo->prepare("UPDATE `admins` SET `last_login_time` = NOW(), `last_login_ip` = ? WHERE `id` = ?");
                            $updateStmt->execute([$lastLoginIp, $admin['id']]);
                        } catch (Exception $e) {
                        }

                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_username'] = $admin['username'];
                        $_SESSION['admin_id'] = $admin['id'];
                        return true;
                    }
                    return false;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Admin login database error: " . $e->getMessage());
    } catch (PDOException $e) {
        error_log("Admin login PDO error: " . $e->getMessage());
    }

    if ($configHash && password_verify($password, $configHash)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_id'] = null;
        return true;
    }

    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_id'] = null;
        return true;
    }

    return false;
}

/**
 * 向后兼容：如果数据库表不存在，使用文件方式
 */
function adminLoginFallback($username, $password) {
    $adminConfigFile = __DIR__ . '/admin_config.php';

    // 如果配置文件存在，使用配置文件
    if (file_exists($adminConfigFile)) {
        $config = include $adminConfigFile;
        if (is_array($config) && isset($config[$username])) {
            // 如果文件中有密码记录，必须使用文件中的密码验证
            if (password_verify($password, $config[$username])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;
                return true;
            }
            // 如果文件中的密码验证失败，不再接受默认密码（密码已被修改）
            return false;
        }
    }

    // 只有在配置文件不存在或配置文件中没有该用户时，才允许使用默认密码（admin123）
    // 这是向后兼容，允许首次登录时使用默认密码
    // 注意：一旦密码被修改（文件或数据库中有记录），就不再接受默认密码
    if ($username === 'admin' && $password === 'admin123') {
        // 直接验证默认密码（向后兼容）
        // 如果验证通过，创建配置文件
        $finalHash = password_hash('admin123', PASSWORD_DEFAULT);
        $defaultConfig = [
            'admin' => $finalHash,
        ];
        $content = "<?php\n";
        $content .= "/**\n";
        $content .= " * 管理员配置文件（自动生成，请勿手动编辑）\n";
        $content .= " */\n\n";
        $content .= "return [\n";
        $content .= "    'admin' => '" . $finalHash . "',\n";
        $content .= "];\n";
        @file_put_contents($adminConfigFile, $content);

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        return true;
    }

    return false;
}

/**
 * 更新管理员密码
 * @param string $username 用户名
 * @param string $newPasswordHash 新密码哈希
 * @return bool 是否更新成功
 */
function updateAdminPassword($username, $newPasswordHash) {
    global $pdo;
    $adminConfigFile = __DIR__ . '/admin_config.php';
    $dbSuccess = false;
    $fileSuccess = false;

    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'admins'");
        $tableExists = $checkTable && $checkTable->rowCount() > 0;

        if ($tableExists) {
            $stmt = $pdo->prepare("UPDATE `admins` SET `password` = ?, `updated_at` = NOW() WHERE `username` = ?");
            $result = $stmt->execute([$newPasswordHash, $username]);
            if ($result && $stmt->rowCount() > 0) {
                $dbSuccess = true;
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `admins` WHERE `username` = ?");
                $stmt->execute([$username]);
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("INSERT INTO `admins` (`username`, `password`, `status`) VALUES (?, ?, 1)");
                    if ($stmt->execute([$username, $newPasswordHash])) {
                        $dbSuccess = true;
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Update password error: " . $e->getMessage());
    } catch (PDOException $e) {
        error_log("Update password PDO error: " . $e->getMessage());
    }

    if (file_exists($adminConfigFile)) {
        $config = include $adminConfigFile;
        if (is_array($config)) {
            $config[$username] = $newPasswordHash;

            $content = "<?php\n";
            $content .= "/**\n";
            $content .= " * 管理员配置文件（自动生成，请勿手动编辑）\n";
            $content .= " */\n\n";
            $content .= "return [\n";
            foreach ($config as $user => $hash) {
                $content .= "    '" . addslashes($user) . "' => '" . addslashes($hash) . "',\n";
            }
            $content .= "];\n";

            if (file_put_contents($adminConfigFile, $content) !== false) {
                $fileSuccess = true;
            }
        }
    }

    if ($dbSuccess || $fileSuccess) {
        return true;
    }

    return updateAdminPasswordFallback($username, $newPasswordHash);
}

/**
 * 向后兼容：如果数据库表不存在，使用文件方式
 */
function updateAdminPasswordFallback($username, $newPasswordHash) {
    $adminConfigFile = __DIR__ . '/admin_config.php';

    if (file_exists($adminConfigFile)) {
        $config = include $adminConfigFile;
        if (is_array($config) && isset($config[$username])) {
            $config[$username] = $newPasswordHash;

            $content = "<?php\n";
            $content .= "/**\n";
            $content .= " * 管理员配置文件（自动生成，请勿手动编辑）\n";
            $content .= " */\n\n";
            $content .= "return [\n";
            foreach ($config as $user => $hash) {
                $content .= "    '" . addslashes($user) . "' => '" . addslashes($hash) . "',\n";
            }
            $content .= "];\n";

            return file_put_contents($adminConfigFile, $content) !== false;
        }
    }

    return false;
}

/**
 * 验证当前密码
 * @param string $username 用户名
 * @param string $password 密码
 * @return bool 密码是否正确
 */
function verifyAdminPassword($username, $password) {
    global $pdo;

    $adminConfigFile = __DIR__ . '/admin_config.php';
    $configExists = file_exists($adminConfigFile);
    $configHash = null;

    if ($configExists) {
        $config = include $adminConfigFile;
        if (is_array($config) && isset($config[$username])) {
            $configHash = $config[$username];
        }
    }

    try {
        if (isset($pdo)) {
            $checkTable = $pdo->query("SHOW TABLES LIKE 'admins'");
            $tableExists = $checkTable && $checkTable->rowCount() > 0;

            if ($tableExists) {
                $stmt = $pdo->prepare("SELECT `password` FROM `admins` WHERE `username` = ? AND `status` = 1");
                $stmt->execute([$username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin && !empty($admin['password'])) {
                    if (password_verify($password, $admin['password'])) {
                        return true;
                    }
                    if ($configHash && password_verify($password, $configHash)) {
                        return true;
                    }
                    return false;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Verify password error: " . $e->getMessage());
    } catch (PDOException $e) {
        error_log("Verify password PDO error: " . $e->getMessage());
    }

    if ($configHash) {
        return password_verify($password, $configHash);
    }

    if ($username === 'admin' && $password === 'admin123') {
        return true;
    }

    return false;
}

/**
 * 向后兼容：如果数据库表不存在，使用文件方式
 */
function verifyAdminPasswordFallback($username, $password) {
    $adminConfigFile = __DIR__ . '/admin_config.php';

    // 如果配置文件存在，使用配置文件
    if (file_exists($adminConfigFile)) {
        $config = include $adminConfigFile;
        if (is_array($config) && isset($config[$username])) {
            // 使用 password_verify 验证密码
            // 如果文件中有密码记录，必须使用文件中的密码，不再接受默认密码
            return password_verify($password, $config[$username]);
        }
    }

    // 只有在配置文件不存在或配置文件中没有该用户时，才允许使用默认密码（admin123）
    // 这是向后兼容，允许首次登录时使用默认密码
    // 注意：一旦密码被修改（文件或数据库中有记录），就不再接受默认密码
    if ($username === 'admin' && $password === 'admin123') {
        return true;
    }

    return false;
}

function adminLogout() {
    session_destroy();
    // 获取当前脚本的目录，构建相对路径
    $loginPath = dirname($_SERVER['PHP_SELF']) . '/login.php';
    if (strpos($loginPath, '/admin/') === false) {
        $loginPath = '/admin/login.php';
    }
    header('Location: ' . $loginPath);
    exit;
}
