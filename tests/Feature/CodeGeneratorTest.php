<?php

namespace Tests\Feature;

use App\Models\CodeSequence;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\Product;
use App\Support\Code\CodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 業務コードの自動採番の検証。
 */
class CodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_numbers_each_master_with_its_own_prefix(): void
    {
        $this->assertSame('EMP-0001', Employee::factory()->create()->code);
        $this->assertSame('PTR-0001', Partner::factory()->create()->code);
        $this->assertSame('PRD-0001', Product::factory()->create()->code);
        $this->assertSame('DEP-0001', Department::factory()->create()->code);
    }

    #[Test]
    public function it_increments_the_number_for_each_record(): void
    {
        $codes = Employee::factory()->count(3)->create()->pluck('code')->all();

        $this->assertSame(['EMP-0001', 'EMP-0002', 'EMP-0003'], $codes);
    }

    #[Test]
    public function each_master_has_an_independent_sequence(): void
    {
        Employee::factory()->count(3)->create();
        Partner::factory()->create();

        $this->assertSame('PTR-0001', Partner::query()->orderBy('id')->first()?->code);
        $this->assertSame(4, CodeSequence::query()->find('employees')?->next_number);
        $this->assertSame(2, CodeSequence::query()->find('partners')?->next_number);
    }

    #[Test]
    public function deleting_a_record_does_not_reuse_its_code(): void
    {
        $first = Employee::factory()->create();
        $first->delete();

        $second = Employee::factory()->create();

        $this->assertSame('EMP-0001', $first->code);
        $this->assertSame('EMP-0002', $second->code, '削除しても番号は再利用しない。');
    }

    #[Test]
    public function an_explicitly_given_code_is_kept(): void
    {
        // データ移行や外部システムからの取り込みを想定
        $employee = Employee::factory()->create(['code' => 'EMP-9999']);

        $this->assertSame('EMP-9999', $employee->code);
        $this->assertNull(CodeSequence::query()->find('employees'), '自動採番は行われない。');
    }

    #[Test]
    public function the_sequence_can_be_realigned_after_a_data_import(): void
    {
        $generator = app(CodeGenerator::class);

        $generator->syncTo('employees', 500);

        $this->assertSame('EMP-0501', Employee::factory()->create()->code);
    }

    #[Test]
    public function codes_are_unique_even_when_generated_in_a_tight_loop(): void
    {
        $codes = collect(range(1, 25))
            ->map(fn (): string => Employee::generateCode())
            ->all();

        $this->assertCount(25, array_unique($codes));
        $this->assertSame('EMP-0025', end($codes));
    }
}
