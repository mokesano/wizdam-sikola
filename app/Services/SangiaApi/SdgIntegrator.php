<?php

declare(strict_types=1);

namespace Wizdam\Services\SangiaApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Mengklasifikasikan artikel/riset ke dalam 17 SDG PBB
 * menggunakan Sangia AI Engine.
 */
class SdgIntegrator
{
    private Client $http;
    private array $apiCfg;

    private const SDG_LABELS = [
        1  => 'Tanpa Kemiskinan',
        2  => 'Tanpa Kelaparan',
        3  => 'Kehidupan Sehat dan Sejahtera',
        4  => 'Pendidikan Berkualitas',
        5  => 'Kesetaraan Gender',
        6  => 'Air Bersih dan Sanitasi',
        7  => 'Energi Bersih dan Terjangkau',
        8  => 'Pekerjaan Layak dan Pertumbuhan Ekonomi',
        9  => 'Industri, Inovasi, dan Infrastruktur',
        10 => 'Berkurangnya Kesenjangan',
        11 => 'Kota dan Komunitas Berkelanjutan',
        12 => 'Konsumsi dan Produksi yang Bertanggung Jawab',
        13 => 'Penanganan Perubahan Iklim',
        14 => 'Ekosistem Lautan',
        15 => 'Ekosistem Daratan',
        16 => 'Perdamaian, Keadilan, dan Kelembagaan yang Tangguh',
        17 => 'Kemitraan untuk Mencapai Tujuan',
    ];

    public function __construct()
    {
        $this->apiCfg = require BASE_PATH . '/config/api.php';
        $this->http   = new Client([
            'base_uri' => $this->apiCfg['sangia']['base_url'],
            'timeout'  => $this->apiCfg['sangia']['timeout'],
            'headers'  => [
                'Authorization' => 'Bearer ' . $this->apiCfg['sangia']['api_key'],
                'Accept'        => 'application/json',
            ],
        ]);
    }

    /**
     * Klasifikasikan teks (judul + abstrak) ke SDG.
     *
     * @return array [['sdg' => 4, 'label' => '...', 'score' => 0.92], ...]
     */
    public function classify(string $title, string $abstract = ''): array
    {
        try {
            $response = $this->http->post('/v1/sdg/classify', [
                'json' => [
                    'title'    => $title,
                    'abstract' => $abstract,
                ],
            ]);

            $result = json_decode((string) $response->getBody(), true);
            return $this->enrichWithLabels($result['sdgs'] ?? []);

        } catch (GuzzleException $e) {
            error_log("[SdgIntegrator] API error: " . $e->getMessage());
            return [];
        }
    }

    /** Tambahkan label Bahasa Indonesia ke tiap SDG. */
    private function enrichWithLabels(array $sdgs): array
    {
        return array_map(function (array $sdg) {
            $sdg['label'] = self::SDG_LABELS[$sdg['sdg']] ?? 'SDG ' . $sdg['sdg'];
            $sdg['color'] = $this->sdgColor((int) $sdg['sdg']);
            return $sdg;
        }, $sdgs);
    }

    public static function getLabel(int $sdgNumber): string
    {
        return self::SDG_LABELS[$sdgNumber] ?? 'SDG ' . $sdgNumber;
    }

    private function sdgColor(int $sdg): string
    {
        $colors = [
            1 => '#E5243B', 2 => '#DDA63A', 3 => '#4C9F38',  4 => '#C5192D',
            5 => '#FF3A21', 6 => '#26BDE2', 7 => '#FCC30B',  8 => '#A21942',
            9 => '#FD6925', 10 => '#DD1367', 11 => '#FD9D24', 12 => '#BF8B2E',
            13 => '#3F7E44', 14 => '#0A97D9', 15 => '#56C02B', 16 => '#00689D',
            17 => '#19486A',
        ];
        return $colors[$sdg] ?? '#888888';
    }
}
