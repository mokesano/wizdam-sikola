<?php

declare(strict_types=1);

namespace Wizdam\Services\SangiaApi;

/**
 * Mengklasifikasikan artikel/riset ke dalam 17 SDG PBB
 * menggunakan Sangia AI Engine melalui SangiaGateway.
 */
class SdgIntegrator
{
    private SangiaGateway $gateway;

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

    public function __construct(?string $userApiKey = null)
    {
        $this->gateway = new SangiaGateway($userApiKey);
    }

    /**
     * Klasifikasikan teks (judul + abstrak) ke SDG.
     * Versi SDG default: v5. Bobot diambil dari WeightConfigService.
     *
     * @return array [['sdg' => 4, 'label' => '...', 'score' => 0.92, 'color' => '#...'], ...]
     */
    public function classify(string $title, string $abstract = '', string $version = 'v5'): array
    {
        $weights = WeightConfigService::forSdg($version);
        $results = $this->gateway->classifySdgByText($title, $abstract, $version, $weights);
        return $this->enrichWithColor($results);
    }

    /**
     * Klasifikasikan semua karya peneliti berdasarkan ORCID.
     * Menggunakan supplied_works dari DB jika tersedia.
     *
     * @param array $suppliedWorks Karya dari DB Wizdam Sicola
     */
    public function classifyByOrcid(string $orcid, array $suppliedWorks = [], string $version = 'v5'): array
    {
        $weights = WeightConfigService::forSdg($version);
        $results = $this->gateway->classifySdgByOrcid($orcid, $suppliedWorks, [], $version, $weights);
        return $this->enrichWithColor($results);
    }

    /** Tambahkan warna UN ke tiap SDG result. */
    private function enrichWithColor(array $sdgs): array
    {
        return array_map(function (array $sdg) {
            $sdg['color'] = $this->sdgColor((int) ($sdg['sdg'] ?? 0));
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
