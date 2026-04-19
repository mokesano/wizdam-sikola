<?php

declare(strict_types=1);

namespace Wizdam\Handlers\Tools;

use Wizdam\Database\DBConnector;
use Wizdam\Services\Core\AuthManager;

class ImageResizerHandler
{
    private const MAX_FILE_SIZE  = 10 * 1024 * 1024; // 10 MB
    private const ALLOWED_TYPES  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const OUTPUT_DIR     = BASE_PATH . '/public/assets/images/resized/';

    public function __construct(
        private DBConnector       $db,
        private \Twig\Environment $twig,
        private AuthManager       $auth
    ) {}

    public function handle(string $method): void
    {
        if ($method === 'POST') {
            $this->process();
            return;
        }

        echo $this->twig->render('pages/tools/image_resizer.twig', [
            'pageTitle' => 'Image Resizer – Wizdam Tools',
        ]);
    }

    private function process(): void
    {
        header('Content-Type: application/json');

        $file = $_FILES['image'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'File tidak valid atau tidak diunggah.']);
            return;
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            http_response_code(413);
            echo json_encode(['error' => 'Ukuran file melebihi 10 MB.']);
            return;
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_TYPES, true)) {
            http_response_code(415);
            echo json_encode(['error' => 'Tipe file tidak didukung. Gunakan JPEG, PNG, WebP, atau GIF.']);
            return;
        }

        $width   = max(1, min(5000, (int) ($_POST['width']  ?? 800)));
        $height  = max(1, min(5000, (int) ($_POST['height'] ?? 0)));
        $quality = max(10, min(100, (int) ($_POST['quality'] ?? 85)));

        $srcImage = $this->loadImage($file['tmp_name'], $mime);
        if (!$srcImage) {
            http_response_code(422);
            echo json_encode(['error' => 'Gagal membaca gambar.']);
            return;
        }

        $origW = imagesx($srcImage);
        $origH = imagesy($srcImage);

        // Hitung tinggi proporsional jika height = 0
        if ($height === 0) {
            $height = (int) round($origH * ($width / $origW));
        }

        $dstImage = imagecreatetruecolor($width, $height);

        // Pertahankan transparansi untuk PNG/WebP
        if (in_array($mime, ['image/png', 'image/webp', 'image/gif'], true)) {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 0, 0, 0, 127);
            imagefilledrectangle($dstImage, 0, 0, $width, $height, $transparent);
        }

        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $width, $height, $origW, $origH);

        if (!is_dir(self::OUTPUT_DIR)) {
            mkdir(self::OUTPUT_DIR, 0755, true);
        }

        $filename = uniqid('img_', true) . '.jpg';
        $outPath  = self::OUTPUT_DIR . $filename;

        imagejpeg($dstImage, $outPath, $quality);
        imagedestroy($srcImage);
        imagedestroy($dstImage);

        echo json_encode([
            'url'      => '/assets/images/resized/' . $filename,
            'width'    => $width,
            'height'   => $height,
            'size_kb'  => round(filesize($outPath) / 1024, 1),
        ]);
    }

    private function loadImage(string $path, string $mime): \GdImage|false
    {
        return match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            'image/gif'  => imagecreatefromgif($path),
            default      => false,
        };
    }
}
