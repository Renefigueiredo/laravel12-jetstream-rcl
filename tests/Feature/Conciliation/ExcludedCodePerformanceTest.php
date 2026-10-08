<?php

namespace Tests\Feature\Conciliation;

use App\Enums\ExcludedCodeImportStatus;
use App\Livewire\ExcludedCodes\Index;
use App\Models\ExcludedOperationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ImportsExcludedCodes;
use Tests\TestCase;

class ExcludedCodePerformanceTest extends TestCase
{
    use ImportsExcludedCodes;
    use RefreshDatabase;

    public function test_ten_thousand_codes_are_imported_within_a_minute_and_listed_within_two_seconds(): void
    {
        $administrator = User::factory()->administrador()->create();
        $upload = $this->codesCsv($this->codePairs(10000));

        $startedAt = microtime(true);

        $import = $this->importCodes($administrator, $upload);

        $importSeconds = microtime(true) - $startedAt;

        $this->assertSame(ExcludedCodeImportStatus::Completed, $import->status);
        $this->assertSame(10000, ExcludedOperationCode::query()->count());
        $this->assertLessThan(60, $importSeconds, 'The import took '.round($importSeconds, 1).' seconds.');

        $startedAt = microtime(true);

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->searchTable('999')
            ->sortTable('description', 'desc')
            ->assertCountTableRecords(ExcludedOperationCode::query()->where('code', 'like', '%999%')->count());

        $listSeconds = microtime(true) - $startedAt;

        $this->assertLessThan(2, $listSeconds, 'The list took '.round($listSeconds, 2).' seconds.');
    }
}
