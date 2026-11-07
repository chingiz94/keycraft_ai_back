<?php

namespace Tests\Feature;

use App\Services\CompatibilityService;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompatibilityTest extends TestCase
{
    private function parts(): array
    {
        return [
            ['component' => ['type' => 'case', 'price' => 10000, 'format' => '65', 'specs' => ['mount' => 'gasket']], 'quantity' => 1],
            ['component' => ['type' => 'pcb', 'price' => 7000, 'format' => '65', 'specs' => ['mount' => 'gasket', 'switch_type' => 'mx', 'layout' => 'ansi', 'key_count' => 68, 'stabilizers' => ['2u' => 3, '6.25u' => 1]]], 'quantity' => 1],
            ['component' => ['type' => 'plate', 'price' => 4000, 'format' => '65', 'specs' => ['mount' => 'gasket', 'switch_type' => 'mx']], 'quantity' => 1],
            ['component' => ['type' => 'stabilizer', 'price' => 2000, 'specs' => ['stabilizers' => ['2u' => 4, '6.25u' => 1]]], 'quantity' => 1],
            ['component' => ['type' => 'switch', 'price' => 100, 'specs' => ['switch_type' => 'mx', 'stem' => 'mx']], 'quantity' => 68],
            ['component' => ['type' => 'keycap', 'price' => 5000, 'specs' => ['stem' => 'mx', 'layout' => 'ansi', 'key_count' => 104]], 'quantity' => 1],
        ];
    }

    public function test_a_complete_compatible_build_is_priced_in_integer_minor_units(): void
    {
        $result = app(CompatibilityService::class)->analyze($this->parts());
        $this->assertTrue($result['complete']);
        $this->assertTrue($result['compatible']);
        $this->assertSame(34800, $result['total']);
        $this->assertSame([], $result['issues']);
        $this->assertSame([], $result['unverified']);
    }

    public static function incompatibilities(): array
    {
        return [
            'case size' => ['0.component.format', '75'],
            'plate mount' => ['2.component.specs.mount', 'tray'],
            'switch type' => ['4.component.specs.switch_type', 'choc'],
            'keycap stem' => ['5.component.specs.stem', 'choc'],
            'layout coverage' => ['5.component.specs.layout', 'iso'],
            'too few switches' => ['4.quantity', 67],
            'too few keycaps' => ['5.component.specs.key_count', 60],
            'negative quantity' => ['4.quantity', -1],
            'noninteger quantity' => ['4.quantity', 1.5],
            'two cases' => ['0.quantity', 2],
            'unsupported type' => ['0.component.type', 'unknown'],
        ];
    }

    #[DataProvider('incompatibilities')]
    public function test_incompatible_parts_are_rejected(string $path, mixed $value): void
    {
        $parts = $this->parts();
        Arr::set($parts, $path, $value);
        $result = app(CompatibilityService::class)->analyze($parts);
        $this->assertFalse($result['compatible']);
        $this->assertNotEmpty($result['issues']);
    }

    public function test_stabilizer_sizes_must_cover_the_actual_layout(): void
    {
        $parts = $this->parts();
        $parts[3]['component']['specs']['stabilizers']['6.25u'] = 0;
        $result = app(CompatibilityService::class)->analyze($parts);
        $this->assertFalse($result['compatible']);
        $this->assertStringContainsString('6.25u', implode(' ', $result['issues']));
    }

    public function test_missing_specs_are_unverified_instead_of_assumed_compatible(): void
    {
        $parts = $this->parts();
        unset($parts[2]['component']['specs']['mount']);
        $result = app(CompatibilityService::class)->analyze($parts);
        $this->assertFalse($result['compatible']);
        $this->assertTrue($result['complete']);
        $this->assertNotEmpty($result['unverified']);
    }

    public function test_missing_components_and_duplicate_types_are_rejected(): void
    {
        $parts = $this->parts();
        array_shift($parts);
        $result = app(CompatibilityService::class)->analyze($parts);
        $this->assertFalse($result['complete']);
        $this->assertFalse($result['compatible']);

        $parts = $this->parts();
        $parts[] = $parts[4];
        $this->assertFalse(app(CompatibilityService::class)->analyze($parts)['compatible']);
    }
}
