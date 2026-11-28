<?php

namespace App\Actions\FarmOperations;

use App\Models\Expense;
use App\Models\FarmActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * @implements FromCollection<int, FarmActivity>
 * @implements WithMapping<FarmActivity>
 */
class ActivityLogExport extends StringValueBinder implements FromCollection, WithCustomValueBinder, WithHeadings, WithMapping, WithStrictNullComparison
{
    public const MAX_ROWS = 1000;

    /** @param array<string, mixed> $filters */
    public function __construct(private int $teamId, private array $filters, private string $currency)
    {
        $this->setNumericConversion(false);
    }

    /** @return Collection<int, FarmActivity> */
    public function collection(): Collection
    {
        $filters = $this->filters;
        $teamId = $this->teamId;
        $query = FarmActivity::query()->where('team_id', $teamId)
            ->where(Arr::whereNotNull(Arr::only($filters, ['farm_id', 'commodity_id', 'production_unit_id', 'activity_type'])))
            ->where('activity_date', '>=', $filters['period_start'])
            ->where('activity_date', '<', CarbonImmutable::parse($filters['period_end'])->addDay()->toDateString())
            ->with(['farm:id,name', 'commodity:id,name', 'productionUnit:id,name'])
            ->withCount(['media as evidence_count' => fn (Builder $media) => $media->where('collection_name', FarmActivity::EvidenceCollection)])
            ->orderBy('activity_date')->orderBy('id');

        if (isset($filters['funding_phase_id'])) {
            $query->whereIn('id', Expense::query()->select('farm_activity_id')
                ->where('team_id', $teamId)->where('farm_id', $filters['farm_id'])
                ->where('funding_phase_id', $filters['funding_phase_id']));
        }

        $rows = $query->limit(self::MAX_ROWS + 1)->get();
        if ($rows->count() > self::MAX_ROWS) {
            throw ValidationException::withMessages(['period_end' => 'More than '.self::MAX_ROWS.' activities match. Shorten the period or narrow the filters.']);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Date', 'Farm', 'Production unit', 'Commodity', 'Activity type', 'Description',
            'Inputs', 'Labour', 'Cost ('.$this->currency.')', 'Remarks', 'Next activity', 'Status', 'Evidence files'];
    }

    public function map(mixed $row): array
    {
        return [$row->activity_date->toDateString(), $row->farm->name, $row->productionUnit?->name,
            $row->commodity?->name, $row->activity_type, $row->description, $row->inputs_used,
            $row->labour_used, $row->cost === null ? null : (float) $row->cost, $row->remarks,
            $row->next_activity, $row->status->label(), (int) $row->getAttribute('evidence_count')];
    }
}
