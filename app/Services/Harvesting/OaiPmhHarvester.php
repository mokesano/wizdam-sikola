<?php

declare(strict_types=1);

namespace Wizdam\Services\Harvesting;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Wizdam\Database\Models\JournalModel;
use Wizdam\Database\Models\ResearcherModel;

/**
 * Mengambil metadata artikel dari endpoint OAI-PMH jurnal.
 *
 * Protokol: Open Archives Initiative – Protocol for Metadata Harvesting (v2.0)
 * Format metadata default: oai_dc (Dublin Core)
 */
class OaiPmhHarvester
{
    private Client $http;
    private JournalModel $journalModel;
    private ResearcherModel $researcherModel;

    public function __construct()
    {
        $this->http            = new Client(['timeout' => 60]);
        $this->journalModel    = new JournalModel();
        $this->researcherModel = new ResearcherModel();
    }

    /**
     * Tarik semua record dari endpoint OAI-PMH.
     *
     * @return array<int, array> Daftar artikel yang berhasil diparsing.
     */
    public function harvest(string $baseUrl, string $set = '', string $from = ''): array
    {
        $articles      = [];
        $resumptionToken = null;

        do {
            $params = ['verb' => 'ListRecords'];

            if ($resumptionToken) {
                $params['resumptionToken'] = $resumptionToken;
            } else {
                $params['metadataPrefix'] = 'oai_dc';
                if ($set) {
                    $params['set'] = $set;
                }
                if ($from) {
                    $params['from'] = $from;
                }
            }

            try {
                $response = $this->http->get($baseUrl, ['query' => $params]);
                $xml      = new \SimpleXMLElement((string) $response->getBody());
            } catch (GuzzleException $e) {
                error_log("[OaiPmhHarvester] HTTP error: " . $e->getMessage());
                break;
            } catch (\Exception $e) {
                error_log("[OaiPmhHarvester] XML parse error: " . $e->getMessage());
                break;
            }

            $xml->registerXPathNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
            $xml->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');

            foreach ($xml->xpath('//oai:record') as $record) {
                $parsed = $this->parseRecord($record);
                if ($parsed) {
                    $articles[] = $parsed;
                }
            }

            $token           = $xml->xpath('//oai:resumptionToken');
            $resumptionToken = (!empty($token) && (string) $token[0] !== '') ? (string) $token[0] : null;

        } while ($resumptionToken !== null);

        return $articles;
    }

    private function parseRecord(\SimpleXMLElement $record): ?array
    {
        $record->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        $metadata = $record->xpath('.//dc:*');

        if (!$metadata) {
            return null;
        }

        $data = [];
        foreach ($metadata as $el) {
            $key          = $el->getName();
            $data[$key][] = (string) $el;
        }

        return [
            'title'       => $data['title'][0]   ?? '',
            'authors'     => $data['creator']     ?? [],
            'description' => $data['description'][0] ?? '',
            'subject'     => $data['subject']     ?? [],
            'date'        => $data['date'][0]     ?? '',
            'identifier'  => $data['identifier']  ?? [],
            'source'      => $data['source'][0]   ?? '',
            'language'    => $data['language'][0] ?? '',
            'type'        => $data['type'][0]     ?? '',
        ];
    }
}
