<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalasi Wizdam Scola</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-indigo-900 mb-2">
                <i class="fas fa-flask mr-3"></i>Instalasi Wizdam Scola
            </h1>
            <p class="text-gray-600">Panduan instalasi aplikasi tanpa terminal</p>
        </div>

        <?php
        // Inisialisasi
        session_start();
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        
        $step = $_GET['step'] ?? 'requirements';
        $checker = new \App\Install\RequirementsChecker();
        
        // Tampilkan progress bar
        $steps = ['requirements', 'database', 'admin', 'complete'];
        $currentStep = array_search($step, $steps);
        ?>

        <!-- Progress Bar -->
        <div class="mb-8">
            <div class="flex justify-between items-center">
                <?php foreach ($steps as $i => $s): ?>
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center 
                            <?= $i <= $currentStep ? 'bg-indigo-600 text-white' : 'bg-gray-300 text-gray-600' ?>">
                            <i class="fas fa-<?= $i == 0 ? 'check' : ($i == 1 ? 'database' : ($i == 2 ? 'user' : 'flag')) ?>"></i>
                        </div>
                        <span class="text-xs mt-2 font-medium <?= $i <= $currentStep ? 'text-indigo-600' : 'text-gray-400' ?>">
                            <?= ucfirst($s) ?>
                        </span>
                    </div>
                    <?php if ($i < count($steps) - 1): ?>
                        <div class="flex-1 h-1 mx-2 <?= $i < $currentStep ? 'bg-indigo-600' : 'bg-gray-300' ?>"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Content -->
        <div class="bg-white rounded-xl shadow-lg p-8">
            <?php
            switch ($step) {
                case 'requirements':
                    include __DIR__ . '/install-requirements.php';
                    break;
                case 'database':
                    include __DIR__ . '/install-database.php';
                    break;
                case 'admin':
                    include __DIR__ . '/install-admin.php';
                    break;
                case 'complete':
                    include __DIR__ . '/install-complete.php';
                    break;
                default:
                    echo '<p class="text-red-600">Step tidak valid</p>';
            }
            ?>
        </div>

        <div class="text-center mt-8 text-gray-500 text-sm">
            <p>&copy; <?= date('Y') ?> Wizdam Scola. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
