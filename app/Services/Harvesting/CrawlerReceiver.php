<?php

declare(strict_types=1);

namespace Wizdam\Services\Harvesting;

use Wizdam\Database\DBConnector;
use Wizdam\Database\Models\ResearcherModel;
use Wizdam\Database\Models\JournalModel;
use Wizdam\Http\Request;
use Wizdam\Http\Response;

/**
 * Endpoint penerima data dari WizdamCrawler (agen eksternal).
 *
 * Crawler mengirim payload JSON via POST ke /api/crawler
 * dengan Authorization: Bearer {CRAWLER_RECEIVER_TOKEN}
 */
class CrawlerReceiver
{
    private DBConnector $db;
    private ResearcherModel $researcherModel;
    private JournalModel $journalModel;

    public function __construct()
    {
        $this->db              = DBConnector::getInstance();
        $this->researcherModel = new ResearcherModel();
        $this->journalModel    = new JournalModel();
    }

    public function receiveWithResponse(Request $request): Response
    {
        if ($request->method !== 'POST') {
            return Response::json(['error' => 'Method Not Allowed'], 405);
        }

        if (!$this->isAuthorized()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $raw     = file_get_contents('php://input');
        $payload = json_decode($raw, true);

        if (!$payload || !isset($payload['type'])) {
            return Response::json(['error' => 'Payload tidak valid atau tipe tidak dikenali.'], 400);
        }

        try {
            $result = match ($payload['type']) {
                'researcher' => $this->processResearcher($payload['data'] ?? []),
                'article'    => $this->processArticle($payload['data'] ?? []),
                'journal'    => $this->processJournal($payload['data'] ?? []),
                default      => throw new \InvalidArgumentException("Tipe '{$payload['type']}' tidak dikenali."),
            };

            return Response::json(['status' => 'ok', 'result' => $result]);

        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }


    private function isAuthorized(): bool
    {
        $apiCfg = require BASE_PATH . '/config/api.php';
        $token  = $apiCfg['crawler_token'] ?? '';

        if (!$token) {
            return false;
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        return hash_equals('Bearer ' . $token, $authHeader);
    }

    private function processResearcher(array $data): array
    {
        // Accept both legacy ('orcid'/'name') and schema_full ('orcid_id'/'full_name') keys
        if (isset($data['orcid']) && !isset($data['orcid_id'])) {
            $data['orcid_id'] = $data['orcid'];
            unset($data['orcid']);
        }
        if (isset($data['name']) && !isset($data['full_name'])) {
            $data['full_name'] = $data['name'];
            unset($data['name']);
        }

        foreach (['orcid_id', 'full_name'] as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Field '$field' wajib ada.");
            }
        }

        $id = $this->researcherModel->upsert($data);
        return ['id' => $id, 'orcid_id' => $data['orcid_id']];
    }

    private function processArticle(array $data): array
    {
        $required = ['title', 'journal_id', 'year'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Field '$field' wajib ada.");
            }
        }

        // Cek duplikat berdasarkan DOI
        if (!empty($data['doi'])) {
            $existing = $this->db->fetchOne(
                'SELECT id FROM articles WHERE doi = ?',
                [$data['doi']]
            );
            if ($existing) {
                $sets   = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
                $params = array_merge(array_values($data), [$existing['id']]);
                $this->db->query("UPDATE articles SET $sets, updated_at = NOW() WHERE id = ?", $params);
                return ['id' => $existing['id'], 'action' => 'updated'];
            }
        }

        $id = $this->db->insert('articles', array_merge($data, ['created_at' => date('Y-m-d H:i:s')]));
        return ['id' => $id, 'action' => 'inserted'];
    }

    private function processJournal(array $data): array
    {
        $required = ['title', 'issn'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Field '$field' wajib ada.");
            }
        }

        $existing = $this->journalModel->findByIssn($data['issn']);
        if ($existing) {
            $sets   = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
            $params = array_merge(array_values($data), [$existing['id']]);
            $this->db->query("UPDATE journals SET $sets, updated_at = NOW() WHERE id = ?", $params);
            return ['id' => $existing['id'], 'action' => 'updated'];
        }

        $id = $this->db->insert('journals', array_merge($data, ['created_at' => date('Y-m-d H:i:s')]));
        return ['id' => $id, 'action' => 'inserted'];
    }
}
