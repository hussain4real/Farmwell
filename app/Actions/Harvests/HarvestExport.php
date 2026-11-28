<?php

namespace App\Actions\Harvests;

use App\Models\HarvestRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * @implements FromCollection<int, HarvestRecord>
 * @implements WithMapping<HarvestRecord>
 */
class HarvestExport extends StringValueBinder implements FromCollection, WithColumnWidths, WithCustomValueBinder, WithHeadings, WithMapping, WithStrictNullComparison
{
    public const MAX_ROWS = 1000;

    /** @param array<string, mixed> $filters */
    public function __construct(private int $teamId, private array $filters)
    {
        $this->setNumericConversion(false);
    }

    /** @return Collection<int, HarvestRecord> */
    public function collection(): Collection
    {
        $until = CarbonImmutable::parse($this->filters['period_end'])->addDay()->toDateString();
        $records = HarvestRecord::query()
            ->where('team_id', $this->teamId)
            ->where(Arr::whereNotNull(Arr::only($this->filters, ['farm_id', 'production_cycle_id', 'production_unit_id', 'commodity_id'])))
            ->where('harvested_on', '>=', $this->filters['period_start'])->where('harvested_on', '<', $until)
            ->with(['farm:id,name', 'commodity:id,name', 'productionCycle:id,name', 'productionUnit:id,name'])
            ->withSum(['sales as sold_quantity' => fn (Builder $sales) => $sales
                ->where('team_id', $this->teamId)->where('farm_id', $this->filters['farm_id'])
                ->whereColumn('sale_records.quantity_unit', 'harvest_records.quantity_unit')
                ->where('sold_on', '<', $until)], 'quantity')
            ->orderBy('harvested_on')->orderBy('id')->limit(self::MAX_ROWS + 1)->get();

        if ($records->count() > self::MAX_ROWS) {
            throw ValidationException::withMessages(['period_end' => 'More than '.self::MAX_ROWS.' harvest records match. Choose a shorter period or narrow the filters.']);
        }

        return $records;
    }

    public function headings(): array
    {
        return ['Harvest ID', 'Harvest date', 'Farm', 'Production cycle', 'Production unit', 'Commodity',
            'Stage', 'Sequence', 'Recorded quantity', 'Quantity unit', 'Sold through '.$this->filters['period_end'],
            'Unsold through '.$this->filters['period_end'], 'Status at export', 'Quality notes', 'Labour cost', 'Currency'];
    }

    public function map(mixed $row): array
    {
        $quantity = (float) $row->quantity;
        $sold = round((float) $row->getAttribute('sold_quantity'), 2);

        return [
            $row->id, $row->harvested_on->toDateString(), $row->farm->name, $row->productionCycle?->name,
            $row->productionUnit?->name, $row->commodity->name, $row->stage->label(), $row->sequence_number,
            $quantity, $row->quantity_unit, $sold, max(0, round($quantity - $sold, 2)),
            $row->status->label(), $row->quality_notes, $row->labour_cost_minor / 100, $row->currency,
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 13, 'B' => 16, 'C' => 26, 'D' => 24, 'E' => 24, 'F' => 22, 'G' => 16, 'H' => 12,
            'I' => 19, 'J' => 16, 'K' => 26, 'L' => 28, 'M' => 22, 'N' => 45, 'O' => 15, 'P' => 12];
    }
}
