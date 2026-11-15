<?php

namespace Tests\Unit;

use App\Services\Minecraft\PackFormatParser;
use DOMDocument;
use RuntimeException;
use Tests\TestCase;

class PackFormatParserTest extends TestCase
{
    public function test_version_names_are_classified(): void
    {
        $parser = app(PackFormatParser::class);

        $this->assertSame('release', $parser->classify('1.20.1'));
        $this->assertSame('snapshot', $parser->classify('23w31a'));
        $this->assertSame('snapshot', $parser->classify('Snapshot 23w31a'));
        $this->assertSame('prerelease', $parser->classify('1.20-pre1'));
        $this->assertSame('release_candidate', $parser->classify('1.20 Release Candidate 1'));
        $this->assertSame('experimental', $parser->classify('Combat Test 8c'));
    }

    public function test_validate_records_normalizes_and_deduplicates(): void
    {
        $parser = app(PackFormatParser::class);

        $records = [];
        for ($i = 1; $i <= 30; $i++) {
            $records[] = ['version_name' => '1.'.$i, 'format_value' => (string) $i, 'version_type' => 'release'];
        }
        $records[] = ['version_name' => '1.1', 'format_value' => '20', 'version_type' => 'snapshot'];

        $validated = $parser->validateRecords($records);

        $this->assertCount(30, $validated);
        $this->assertSame('1', $validated[0]['format_value']);
        $this->assertSame('release', $validated[0]['version_type']);
    }

    public function test_validate_records_refuses_tiny_datasets(): void
    {
        $parser = app(PackFormatParser::class);

        $this->expectException(RuntimeException::class);

        $parser->validateRecords([
            ['version_name' => '1.20.1', 'format_value' => '15'],
        ]);
    }

    public function test_dataset_version_is_stable_and_changes_with_data(): void
    {
        $parser = app(PackFormatParser::class);

        $records = [];
        for ($i = 1; $i <= 30; $i++) {
            $records[] = ['version_name' => '1.'.$i, 'version_type' => 'release', 'format_major' => $i, 'format_minor' => null];
        }

        $one = $parser->datasetVersion($records);
        $two = $parser->datasetVersion($records);

        $this->assertSame($one, $two);

        $records[0]['format_major'] = 999;
        $this->assertNotSame($one, $parser->datasetVersion($records));
    }

    public function test_table_parsing_handles_rowspans(): void
    {
        $html = <<<'HTML'
<table>
  <tr><th>Client version</th><th>Resource pack format</th><th>Notes</th></tr>
  <tr><td rowspan="2">1.20.1</td><td>15</td><td></td></tr>
  <tr><td colspan="2">1.20</td></tr>
  <tr><td>23w31a</td><td>16</td><td>snapshot</td></tr>
  <tr><td>1.20-pre1</td><td>15</td><td></td></tr>
</table>
HTML;

        $dom = new DOMDocument;
        $dom->loadHTML($html);
        $table = $dom->getElementsByTagName('table')->item(0);

        $records = app(PackFormatParser::class)->parseTable($table);

        $this->assertCount(3, $records);
        $this->assertSame('1.20.1', $records[0]['version_name']);
        $this->assertSame('15', $records[0]['format_value']);
        $this->assertSame('snapshot', $records[1]['version_type']);
        $this->assertSame('prerelease', $records[2]['version_type']);
    }

    public function test_html_without_the_format_table_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        app(PackFormatParser::class)->parseHtml('<html><body><p>Nothing here</p></body></html>');
    }
}
