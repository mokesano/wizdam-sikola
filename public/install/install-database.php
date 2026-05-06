<?php
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi input
    $required = ['db_host', 'db_port', 'db_name', 'db_user', 'db_pass'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $error = "Field {$field} harus diisi";
            break;
        }
    }

    if (empty($error)) {
        // Test koneksi database
        try {
            $dsn = "mysql:host={$_POST['db_host']};port={$_POST['db_port']};charset=utf8mb4";
            $pdo = new PDO($dsn, $_POST['db_user'], $_POST['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Cek apakah database ada
            $stmt = $pdo->query("SHOW DATABASES LIKE '{$_POST['db_name']}'");
            if ($stmt->rowCount() === 0) {
                // Buat database jika belum ada
                $pdo->exec("CREATE DATABASE `{$_POST['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            // Koneksi ke database yang dipilih
            $pdo->exec("USE `{$_POST['db_name']}`");

            // Simpan konfigurasi ke session untuk step berikutnya
            $_SESSION['db_config'] = [
                'db_host' => $_POST['db_host'],
                'db_port' => $_POST['db_port'],
                'db_name' => $_POST['db_name'],
                'db_user' => $_POST['db_user'],
                'db_pass' => $_POST['db_pass'],
                'app_name' => $_POST['app_name'] ?? 'Wizdam Scola',
                'app_env' => $_POST['app_env'] ?? 'production',
                'app_debug' => isset($_POST['app_debug']) ? 'true' : 'false',
                'app_url' => $_POST['app_url'] ?? 'https://www.sangia.org',
                'api_url' => $_POST['api_url'] ?? 'https://api.sangia.org',
                'api_key' => $_POST['api_key'] ?? ''
            ];

            header('Location: ?step=admin');
            exit;
        } catch (PDOException $e) {
            $error = "Koneksi database gagal: " . $e->getMessage();
        }
    }
}

// Pre-fill values
$config = $_SESSION['db_config'] ?? [];
?>

<h2 class="text-2xl font-bold text-gray-800 mb-6">
    <i class="fas fa-database mr-2"></i>Konfigurasi Database
</h2>

<?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <p class="text-red-700"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?></p>
    </div>
<?php endif; ?>

<form method="POST" class="space-y-6">
    <!-- App Settings -->
    <div class="border-b pb-4">
        <h3 class="font-semibold text-gray-700 mb-3">Pengaturan Aplikasi</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Aplikasi</label>
                <input type="text" name="app_name" value="<?= htmlspecialchars($config['app_name'] ?? 'Wizdam Scola') ?>" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">URL Aplikasi</label>
                <input type="url" name="app_url" value="<?= htmlspecialchars($config['app_url'] ?? 'https://www.sangia.org') ?>" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Environment</label>
                <select name="app_env" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="production" <?= ($config['app_env'] ?? '') === 'production' ? 'selected' : '' ?>>Production</option>
                    <option value="development" <?= ($config['app_env'] ?? '') === 'development' ? 'selected' : '' ?>>Development</option>
                </select>
            </div>
            <div class="flex items-center mt-6">
                <input type="checkbox" name="app_debug" id="app_debug" value="1" 
                    <?= ($config['app_debug'] ?? '') === 'true' ? 'checked' : '' ?>
                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                <label for="app_debug" class="ml-2 text-sm text-gray-700">Debug Mode</label>
            </div>
        </div>
    </div>

    <!-- Database Settings -->
    <div class="border-b pb-4">
        <h3 class="font-semibold text-gray-700 mb-3">Pengaturan Database MariaDB/MySQL</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Host</label>
                <input type="text" name="db_host" value="<?= htmlspecialchars($config['db_host'] ?? 'localhost') ?>" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                <input type="number" name="db_port" value="<?= htmlspecialchars($config['db_port'] ?? '3306') ?>" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Database</label>
                <input type="text" name="db_name" value="<?= htmlspecialchars($config['db_name'] ?? '') ?>" required
                    placeholder="wizdam_db"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input type="text" name="db_user" value="<?= htmlspecialchars($config['db_user'] ?? '') ?>" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="db_pass" value="<?= htmlspecialchars($config['db_pass'] ?? '') ?>" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
        </div>
    </div>

    <!-- API Settings -->
    <div>
        <h3 class="font-semibold text-gray-700 mb-3">Pengaturan Wizdam API</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">API Endpoint URL</label>
                <input type="url" name="api_url" value="<?= htmlspecialchars($config['api_url'] ?? 'https://api.sangia.org') ?>" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">API Key (Opsional)</label>
                <input type="text" name="api_key" value="<?= htmlspecialchars($config['api_key'] ?? '') ?>" 
                    placeholder="Akan dibuat otomatis jika kosong"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
        </div>
        <p class="mt-2 text-sm text-gray-600">
            <i class="fas fa-info-circle mr-1"></i>
            API Key dapat dibuat nanti melalui dashboard admin atau developers.sangia.org
        </p>
    </div>

    <div class="flex justify-between pt-4">
        <a href="?step=requirements" class="inline-block bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
            <i class="fas fa-arrow-left mr-2"></i>Kembali
        </a>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
            <i class="fas fa-arrow-right mr-2"></i>Test & Lanjut
        </button>
    </div>
</form>
