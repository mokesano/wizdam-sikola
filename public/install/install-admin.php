<?php
session_start();

$error = '';
$success = '';
$installComplete = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi input
    if (empty($_POST['admin_name']) || empty($_POST['admin_email']) || empty($_POST['admin_password'])) {
        $error = "Semua field admin harus diisi";
    } elseif (!filter_var($_POST['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $error = "Format email admin tidak valid";
    } elseif (strlen($_POST['admin_password']) < 6) {
        $error = "Password admin minimal 6 karakter";
    } else {
        // Koneksi ke database
        try {
            $dbConfig = $_SESSION['db_config'];
            $dsn = "mysql:host={$dbConfig['db_host']};port={$dbConfig['db_port']};dbname={$dbConfig['db_name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbConfig['db_user'], $dbConfig['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Jalankan instalasi
            $installer = new \App\Install\DatabaseInstaller($pdo);
            $config = array_merge($dbConfig, [
                'admin_name' => $_POST['admin_name'],
                'admin_email' => $_POST['admin_email'],
                'admin_password' => $_POST['admin_password']
            ]);

            $result = $installer->install($config);

            if ($result['success']) {
                $installComplete = true;
                $success = $result['message'];
                // Hapus session config
                unset($_SESSION['db_config']);
            } else {
                $error = $result['message'];
            }
        } catch (Exception $e) {
            $error = "Error instalasi: " . $e->getMessage();
        }
    }
}

if ($installComplete):
?>
    <div class="text-center py-8">
        <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-6">
            <i class="fas fa-check text-green-600 text-4xl"></i>
        </div>
        <h2 class="text-3xl font-bold text-gray-800 mb-4">Instalasi Berhasil!</h2>
        <p class="text-gray-600 mb-8">Aplikasi Wizdam Sicola telah siap digunakan.</p>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8 text-left">
            <h3 class="font-semibold text-blue-800 mb-3">Informasi Login Admin:</h3>
            <div class="space-y-2 text-sm">
                <p><strong>Email:</strong> <?= htmlspecialchars($_POST['admin_email']) ?></p>
                <p><strong>Password:</strong> <?= htmlspecialchars($_POST['admin_password']) ?></p>
                <p class="text-red-600 mt-2"><i class="fas fa-exclamation-triangle mr-1"></i>Simpan informasi ini dan segera ubah password setelah login!</p>
            </div>
        </div>

        <div class="flex justify-center space-x-4">
            <a href="../login.php" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                <i class="fas fa-sign-in-alt mr-2"></i>Login ke Dashboard
            </a>
            <a href="https://developers.sangia.org" target="_blank" class="inline-block bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                <i class="fas fa-book mr-2"></i>Dokumentasi API
            </a>
        </div>

        <div class="mt-8 pt-6 border-t">
            <p class="text-sm text-gray-500">
                <i class="fas fa-info-circle mr-1"></i>
                File instalasi dapat dihapus dari folder <code>/public/install/</code> untuk keamanan.
            </p>
        </div>
    </div>
<?php else: ?>
    <h2 class="text-2xl font-bold text-gray-800 mb-6">
        <i class="fas fa-user-shield mr-2"></i>Buat Akun Administrator
    </h2>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-red-700"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                <input type="text" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input type="password" name="admin_password" required minlength="6"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            <p class="mt-1 text-xs text-gray-500">Minimal 6 karakter</p>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <p class="text-yellow-800 text-sm">
                <i class="fas fa-info-circle mr-2"></i>
                Akun administrator akan memiliki akses penuh untuk mengelola pengguna, API keys, dan konfigurasi aplikasi.
            </p>
        </div>

        <div class="flex justify-between pt-4">
            <a href="?step=database" class="inline-block bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                <i class="fas fa-arrow-left mr-2"></i>Kembali
            </a>
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                <i class="fas fa-check mr-2"></i>Selesaikan Instalasi
            </button>
        </div>
    </form>
<?php endif; ?>
