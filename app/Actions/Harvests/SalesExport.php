<?php

namespace App\Actions\Harvests;

use App\Models\SaleRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class SalesExport extends StringValueBinder implements FromArray, WithColumnWidths, WithCustomValueBinder, WithStrictNullComparison
{
    public const MAX_ROWS = 1000;

    /** @param array<string, mixed> $filters */
    public function __construct(private int $teamId, private array $filters)
    {
        $this->setNumericConversion(false);
    }

    public function array(): array
    {
        $sales = SaleRecord::query()->where('team_id', $this->teamId)
            ->where(Arr::whereNotNull(Arr::only($this->filters, ['farm_id', 'production_cycle_id', 'commodity_id', 'payment_status'])))
            ->where('sold_on', '>=', $this->filters['period_start'])
            ->where('sold_on', '<', CarbonImmutable::parse($this->filters['period_end'])->addDay()->toDateString())
            ->with(['farm:id,name', 'commodity:id,name', 'productionCycle:id,name'])
            ->orderBy('sold_on')->orderBy('id')->limit(self::MAX_ROWS + 1)->get();

        if ($sales->count() > self::MAX_ROWS) {
            throw ValidationException::withMessages(['period_end' => 'More than '.self::MAX_ROWS.' sales match. Choose a shorter period or narrow the filters.']);
        }

        $rows = [
            ['Sales register', $this->filters['period_start'], $this->filters['period_end']],
            ['Sale ID', 'Sale date', 'Farm', 'Production cycle', 'Harvest ID', 'Commodity', 'Buyer',
                'Quantity', 'Unit', 'Unit price', 'Gross sales', 'Deductions', 'Net sales', 'Currency', 'Payment status at export', 'Reference'],
        ];
        foreach ($sales as $sale) {
            $rows[] = [
                $sale->id, $sale->sold_on->toDateString(), $sale->farm->name, $sale->productionCycle?->name,
                $sale->harvest_record_id, $sale->commodity->name, $sale->buyer_name, (float) $sale->quantity, $sale->quantity_unit,
                $sale->unit_price_minor / 100, $sale->gross_amount_minor / 100, $sale->deduction_amount_minor / 100,
                $sale->net_amount_minor / 100, $sale->currency, $sale->payment_status->label(), $sale->reference,
            ];
        }

        $rows[] = ['Totals by commodity, unit and currency'];
        $rows[] = ['Commodity', 'Unit', 'Currency', 'Quantity', 'Gross sales', 'Deductions', 'Net sales'];
        $groups = $sales->groupBy(fn (SaleRecord $sale) => json_encode([$sale->commodity_id, $sale->quantity_unit, $sale->currency], JSON_THROW_ON_ERROR));
        foreach ($groups as $group) {
            $sale = $group->first();
            $rows[] = [$sale->commodity->name, $sale->quantity_unit, $sale->currency,
                $group->sum(fn (SaleRecord $record) => (int) round((float) $record->quantity * 100)) / 100, $group->sum('gross_amount_minor') / 100,
                $group->sum('deduction_amount_minor') / 100, $group->sum('net_amount_minor') / 100];
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 32, 'B' => 16, 'C' => 25, 'D' => 24, 'E' => 16, 'F' => 22, 'G' => 30, 'H' => 14,
            'I' => 12, 'J' => 16, 'K' => 16, 'L' => 16, 'M' => 16, 'N' => 12, 'O' => 25, 'P' => 25];
    }
}
