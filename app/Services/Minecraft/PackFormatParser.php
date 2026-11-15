<?php

namespace App\Services\Minecraft;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

class PackFormatParser
{
    public const EDITION = 'java_resource_pack';
    public const PARSER_VERSION = '1.0.0';

    public function fetch(?string $apiUrl = null, ?string $pageUrl = null): array
    {
        $apiUrl ??= (string) config('nextrespack.pack_format_api_url', 'https://minecraft.wiki/api.php');
        $pageUrl ??= (string) config('nextrespack.pack_format_source_url', 'https://minecraft.wiki/w/Pack_format');

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'User-Agent' => 'NextRespack/'.config('nextrespack.version').' (pack format synchronizer)',
            'Accept' => 'application/json',
        ])->timeout(45)->retry(2, 1000)->get($apiUrl, [
            'action' => 'parse',
            'page' => 'Pack_format',
            'prop' => 'text',
            'format' => 'json',
            'formatversion' => 2,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Minecraft Wiki returned HTTP '.$response->status().'.');
        }

        $payload = $response->json();
        $html = $payload['parse']['text'] ?? null;

        if (! is_string($html) || strlen($html) < 1000) {
            throw new RuntimeException('Minecraft Wiki returned an unexpected response.');
        }

        $dataset = $this->parseHtml($html, $pageUrl);
        $dataset['raw_payload'] = $html;

        return $dataset;
    }

    public function parseHtml(string $html, ?string $sourceUrl = null, ?string $fetchedAt = null): array
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('Unable to parse the Minecraft Wiki HTML.');
        }

        $xpath = new DOMXPath($dom);
        $table = null;

        foreach ($xpath->query('//table') as $candidate) {
            $headerText = $this->normalizeText($candidate->textContent);
            $headers = [];
            foreach ($xpath->query('.//th', $candidate) as $th) {
                $headers[] = strtolower($this->normalizeText($th->textContent));
            }

            if (in_array('client version', $headers, true) && in_array('resource pack format', $headers, true)) {
                $table = $candidate;
                break;
            }
        }

        if (! $table instanceof DOMElement) {
            throw new RuntimeException('The resource pack format table was not found. The wiki page layout may have changed.');
        }

        $records = $this->parseTable($table);

        if (count($records) < 30) {
            throw new RuntimeException('The parsed resource pack format dataset is too small ('.count($records).' records). Refusing to publish incomplete data.');
        }

        $releaseCount = count(array_filter($records, fn ($r) => $r['version_type'] === 'release'));
        if ($releaseCount < 10) {
            throw new RuntimeException('The parsed dataset contains too few release versions.');
        }

        $records = $this->sortRecords($records);
        $datasetVersion = $this->datasetVersion($records);
        $fetchedAt ??= now()->toIso8601String();

        return [
            'edition' => self::EDITION,
            'source_url' => $sourceUrl ?? (string) config('nextrespack.pack_format_source_url'),
            'fetched_at' => $fetchedAt,
            'parser_version' => self::PARSER_VERSION,
            'dataset_version' => $datasetVersion,
            'records' => $records,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function parseTable(DOMElement $table): array
    {
        $rows = [];
        $pending = [];
        $records = [];
        $seen = [];

        foreach ($table->getElementsByTagName('tr') as $tr) {
            $cells = [];
            $col = 0;
            $cellNodes = [];
            foreach ($tr->childNodes as $child) {
                if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['td', 'th'], true)) {
                    $cellNodes[] = $child;
                }
            }

            // Rebuild the row with rowspan values from previous rows.
            $index = 0;
            while (true) {
                if (isset($pending[$col])) {
                    [$remaining, $value] = $pending[$col];
                    $cells[$col] = $value;
                    if ($remaining <= 1) {
                        unset($pending[$col]);
                    } else {
                        $pending[$col] = [$remaining - 1, $value];
                    }
                    $col++;
                    continue;
                }

                if ($index >= count($cellNodes)) {
                    break;
                }

                /** @var DOMElement $node */
                $node = $cellNodes[$index];
                $index++;
                $rowspan = max(1, (int) ($node->getAttribute('rowspan') ?: 1));
                $colspan = max(1, (int) ($node->getAttribute('colspan') ?: 1));
                $value = $this->normalizeText($node->textContent);

                for ($c = 0; $c < $colspan; $c++) {
                    $cells[$col] = $value;
                    if ($rowspan > 1) {
                        $pending[$col] = [$rowspan - 1, $value];
                    }
                    $col++;
                }
            }

            if (count($cells) < 3) {
                continue;
            }

            $clientVersion = $cells[0] ?? '';
            $formatValue = $cells[1] ?? '';

            if ($clientVersion === '' || strtolower($clientVersion) === 'client version') {
                continue;
            }

            $clientVersion = preg_replace('/\[\d+\]/', '', $clientVersion) ?? $clientVersion;
            $clientVersion = preg_replace('/^Java Edition\s+/i', '', $clientVersion);
            $clientVersion = trim($clientVersion);
            $formatValue = trim($formatValue);

            if (! preg_match('/^(\d+)(?:\.(\d+))?$/', $formatValue, $m)) {
                continue;
            }

            if (isset($seen[$clientVersion])) {
                continue;
            }
            $seen[$clientVersion] = true;

            $major = (int) $m[1];
            $minor = isset($m[2]) ? (int) $m[2] : null;

            $records[] = [
                'version_name' => $clientVersion,
                'version_type' => $this->classify($clientVersion),
                'version_channel' => null,
                'format_major' => $major,
                'format_minor' => $minor,
                'format_value' => $minor === null ? (string) $major : $major.'.'.$minor,
            ];
        }

        return $records;
    }

    public function classify(string $name): string
    {
        $low = strtolower($name);
        $base = preg_replace('/\s+unobfuscated$/i', '', $low) ?? $low;
        $base = trim($base);

        if (str_contains($base, 'combat test')) {
            return 'experimental';
        }
        if (str_contains($base, 'release candidate')) {
            return 'release_candidate';
        }
        if (str_contains($base, 'pre-release') || preg_match('/-pre\d*$/', $base)) {
            return 'prerelease';
        }
        if (str_contains($base, 'snapshot') || preg_match('/^\d+w\d+[a-z]$/', $base)) {
            return 'snapshot';
        }
        if (preg_match('/^\d+\.\d+(\.\d+)?$/', $base)) {
            return 'release';
        }

        return 'experimental';
    }

    public function sortRecords(array $records): array
    {
        usort($records, function ($a, $b) {
            return [$a['format_major'], $a['format_minor'] ?? 0, $a['version_name']]
                <=> [$b['format_major'], $b['format_minor'] ?? 0, $b['version_name']];
        });

        return array_values($records);
    }

    public function datasetVersion(array $records): string
    {
        $canonical = array_map(fn ($r) => [
            'v' => $r['version_name'],
            't' => $r['version_type'],
            'M' => (int) $r['format_major'],
            'm' => isset($r['format_minor']) ? (int) $r['format_minor'] : null,
        ], $records);

        return substr(hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES)), 0, 16);
    }

    public function validateRecords(array $records): array
    {
        $valid = [];
        $seen = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $name = trim((string) ($record['version_name'] ?? ''));
            if ($name === '' || isset($seen[$name])) {
                continue;
            }

            $formatValue = $record['format_value'] ?? null;
            $major = $record['format_major'] ?? null;
            $minor = $record['format_minor'] ?? null;

            if ($formatValue !== null && preg_match('/^(\d+)(?:\.(\d+))?$/', (string) $formatValue, $m)) {
                $major = (int) $m[1];
                $minor = isset($m[2]) ? (int) $m[2] : null;
            } elseif ($major !== null && is_numeric($major)) {
                $major = (int) $major;
                $minor = $minor === null || $minor === '' ? null : (int) $minor;
            } else {
                continue;
            }

            $type = (string) ($record['version_type'] ?? '');
            if (! in_array($type, ['release', 'snapshot', 'prerelease', 'release_candidate', 'experimental'], true)) {
                $type = $this->classify($name);
            }

            $seen[$name] = true;
            $valid[] = [
                'version_name' => $name,
                'version_type' => $type,
                'version_channel' => $record['version_channel'] ?? null,
                'format_major' => $major,
                'format_minor' => $minor,
                'format_value' => $minor === null ? (string) $major : $major.'.'.$minor,
                'release_date' => $record['release_date'] ?? null,
            ];
        }

        if (count($valid) < 30) {
            throw new RuntimeException('The imported dataset contains too few valid records.');
        }

        return $this->sortRecords($valid);
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\xc2\xa0", "\u{00A0}"], ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
