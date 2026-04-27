<?php

declare(strict_types=1);

namespace Wizdam\Services\SangiaApi;

use Wizdam\Database\Models\ImpactScoreModel;

/**
 * Fasad untuk kalkulasi Wizdam Impact Score melalui SangiaGateway.
 *
 * Pola kerja:
 *   1. Cek author_profiles_cache → kirim sebagai supplied_data (skip external fetch)
 *   2. SangiaGateway::calculateImpact() dengan batch loop
 *   3. Simpan raw_data ke cache jika API fetch dari sumber eksternal
 *   4. Simpan hasil skor ke tabel impact_scores
 *
 * Formula: Composite = Academic×40% + Social×25% + Economic×20% + SDG×15%
 */
class ImpactScoreClient
{
    private SangiaGateway    $gateway;
    private ImpactScoreModel $scoreModel;

    public function __construct(?string $userApiKey = null)
    {
        $this->gateway    = new SangiaGateway($userApiKey);
        $this->scoreModel = new ImpactScoreModel();
    }

    /**
     * Hitung impact score untuk peneliti berdasarkan ORCID.
     *
     * @param array $social   ['media_mentions' => 0–100, 'policy_citations' => ..., ...]
     * @param array $economic ['industry_adoption' => 0–100, 'patents' => ..., ...]
     */
    public function calculateByOrcid(
        string  $orcid,
        int     $researcherId,
        ?string $scopusId = null,
        array   $social   = [],
        array   $economic = []
    ): array {
        $t0 = microtime(true);

        // Load cache → supplied_data agar tidak fetch ulang ke ORCID/Scopus
        $cached         = RawDataPersister::loadAuthorProfile($orcid);
        $suppliedWorks  = $cached['supplied_works']  ?? [];
        $suppliedPerson = $cached['supplied_person'] ? [$cached['supplied_person']] : [];
        $suppliedScopus = $cached['supplied_scopus'] ?? [];
        $weights        = WeightConfigService::forImpact();

        $result = $this->gateway->calculateImpact(
            orcid:          $orcid,
            scopusId:       $scopusId,
            suppliedWorks:  $suppliedWorks,
            suppliedPerson: $suppliedPerson,
            suppliedScopus: $suppliedScopus,
            social:         $social,
            economic:       $economic,
            weights:        $weights,
        );

        $durationMs = (int) ((microtime(true) - $t0) * 1000);

        if (($result['status'] ?? '') !== 'success') {
            RawDataPersister::logApiCall(null, '/api/v1/impact/calculate', ['orcid' => $orcid], 'error', $durationMs);
            return $this->scoreModel->findLatest('researcher', $researcherId)
                ?: ['error' => $result['message'] ?? 'Sangia API error'];
        }

        // Simpan raw_data ke cache jika data baru dari API eksternal
        if (!empty($result['raw_data'])) {
            RawDataPersister::saveAuthorProfile($orcid, $result['raw_data']);
        }

        RawDataPersister::saveAnalysis($orcid, 'impact', $result);

        // Simpan skor 4 pilar ke DB
        $pillars = $result['pillars'] ?? [];
        $this->scoreModel->saveCalculation(
            entityType: 'researcher',
            entityId:   $researcherId,
            academic:   (float) ($pillars['academic'] ?? 0),
            social:     (float) ($pillars['social']   ?? 0),
            economic:   (float) ($pillars['economic'] ?? 0),
            sdg:        (float) ($pillars['sdg']      ?? 0),
            sdgTags:    $result['sdg_tags'] ?? [],
        );

        RawDataPersister::logApiCall(
            null, '/api/v1/impact/calculate', ['orcid' => $orcid],
            'success', $durationMs,
            $result['data_sources']['orcid'] ?? ''
        );

        return $this->scoreModel->findLatest('researcher', $researcherId) ?: $result;
    }

    /**
     * Trigger kalkulasi untuk entitas selain peneliti (artikel, institusi, jurnal).
     * Gunakan skor yang sudah ada di DB atau fallback ke DB langsung.
     */
    public function calculate(string $entityType, int $entityId): array
    {
        $cached = $this->scoreModel->findLatest($entityType, $entityId);
        return $cached ?: ['error' => "Skor {$entityType} #{$entityId} belum tersedia."];
    }

    /** Ambil skor terkini dari DB tanpa trigger kalkulasi. */
    public function getLatest(string $entityType, int $entityId): array|false
    {
        return $this->scoreModel->findLatest($entityType, $entityId);
    }
}
