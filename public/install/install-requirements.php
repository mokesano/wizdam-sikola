<?php
$requirements = $checker->check();
?>

<h2 class="text-2xl font-bold text-gray-800 mb-6">
    <i class="fas fa-clipboard-check mr-2"></i>Cek Persyaratan Sistem
</h2>

<?php if ($requirements['passed']): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <div class="flex items-center">
            <i class="fas fa-check-circle text-green-500 text-2xl mr-3"></i>
            <div>
                <h3 class="font-semibold text-green-800">Semua persyaratan terpenuhi!</h3>
                <p class="text-green-700 text-sm">Sistem Anda siap untuk instalasi Wizdam AI-Sikola.</p>
            </div>
        </div>
    </div>

    <a href="?step=database" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
        <i class="fas fa-arrow-right mr-2"></i>Lanjut ke Konfigurasi Database
    </a>
<?php else: ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle text-red-500 text-2xl mr-3"></i>
            <div>
                <h3 class="font-semibold text-red-800">Ada persyaratan yang belum terpenuhi</h3>
                <p class="text-red-700 text-sm">Silakan perbaiki masalah di bawah ini sebelum melanjutkan.</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- PHP Version -->
<div class="mb-6">
    <h3 class="font-semibold text-gray-700 mb-2">Versi PHP</h3>
    <div class="flex items-center">
        <span class="text-gray-600 mr-2">PHP <?= PHP_VERSION ?></span>
        <?php if ($requirements['php_version']): ?>
            <span class="text-green-600"><i class="fas fa-check"></i> (>= 7.4)</span>
        <?php else: ?>
            <span class="text-red-600"><i class="fas fa-times"></i> (Minimal 7.4)</span>
        <?php endif; ?>
    </div>
</div>

<!-- Extensions -->
<div class="mb-6">
    <h3 class="font-semibold text-gray-700 mb-2">Ekstensi PHP</h3>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
        <?php foreach ($requirements['extensions'] as $ext => $loaded): ?>
            <div class="flex items-center">
                <i class="fas fa-<?= $loaded ? 'check' : 'times' ?> mr-2 <?= $loaded ? 'text-green-600' : 'text-red-600' ?>"></i>
                <span class="<?= $loaded ? 'text-gray-700' : 'text-red-600 font-semibold' ?>"><?= $ext ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Permissions -->
<div class="mb-6">
    <h3 class="font-semibold text-gray-700 mb-2">Izin Folder</h3>
    <div class="space-y-2">
        <?php foreach ($requirements['permissions'] as $dir => $writable): ?>
            <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                <span class="text-gray-700 font-mono text-sm">/<?= $dir ?></span>
                <?php if ($writable): ?>
                    <span class="text-green-600"><i class="fas fa-check"></i>Writable</span>
                <?php else: ?>
                    <span class="text-red-600"><i class="fas fa-times"></i>Not Writable</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if (!$requirements['passed'] && empty(array_filter($requirements['permissions']))): ?>
        <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded p-3">
            <p class="text-yellow-800 text-sm">
                <strong>Solusi:</strong> Hubungi administrator hosting Anda untuk memberikan izin tulis (chmod 755 atau 777) pada folder-folder di atas.
            </p>
        </div>
    <?php endif; ?>
</div>

<?php if (!$requirements['passed']): ?>
    <button onclick="location.reload()" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
        <i class="fas fa-redo mr-2"></i>Cek Ulang
    </button>
<?php endif; ?>
