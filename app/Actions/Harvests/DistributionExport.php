<?php

namespace App\Actions\Harvests;

use App\Models\DistributionRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class DistributionExport extends StringValueBinder implements FromArray, WithColumnWidths, WithCustomValueBinder, WithStrictNullComparison
{
    public const MAX_ROWS = 1000;

    /** @param array<string, mixed> $filters */
    public function __construct(private int $teamId, private array $filters)
    {
        $this->setNumericConversion(false);
    }

    public function array(): array
    {
        $records = DistributionRecord::query()->where('team_id', $this->teamId)
            ->where(Arr::whereNotNull(Arr::only($this->filters, ['farm_id', 'production_cycle_id', 'investor_agreement_id', 'status'])))
            ->where('calculated_at', '>=', $this->filters['period_start'])
            ->where('calculated_at', '<', CarbonImmutable::parse($this->filters['period_end'])->addDay()->toDateString())
            ->with(['farm:id,name', 'productionCycle:id,name', 'investorAgreement:id,title,amount_funded_minor,currency'])
            ->orderBy('calculated_at')->orderBy('id')->limit(self::MAX_ROWS + 1)->get();

        if ($records->count() > self::MAX_ROWS) {
            throw ValidationException::withMessages(['period_end' => 'More than '.self::MAX_ROWS.' distributions match. Choose a shorter period or narrow the filters.']);
        }

        $rows = [
            ['Distribution register', $this->filters['period_start'], $this->filters['period_end']],
            ['Distribution ID', 'Calculated at', 'Farm', 'Production cycle', 'Agreement ID', 'Agreement', 'Sale ID',
                'Currency', 'Net sale proceeds', 'Previously recovered', 'Capital recovered this sale', 'Unrecovered capital',
                'Gross profit', 'Net profit', 'Investor split (%)', 'Farm split (%)', 'Investor profit share', 'Farm profit share',
                'Investor allocation incl. capital', 'Status at export', 'Acknowledged at', 'Paid at'],
        ];
        foreach ($records as $record) {
            $rows[] = [
                $record->id, $record->calculated_at->toDateTimeString(), $record->farm->name, $record->productionCycle?->name,
                $record->investor_agreement_id, $record->investorAgreement->title, $record->sale_record_id, $record->currency,
                $record->sale_net_amount_minor / 100, $record->previous_capital_recovered_minor / 100,
                $record->capital_recovered_minor / 100, $record->unrecovered_capital_minor / 100,
                $record->gross_profit_minor / 100, $record->net_profit_minor / 100,
                $record->investor_profit_share_percentage, $record->farm_profit_share_percentage,
                $record->investor_share_minor / 100, $record->farm_share_minor / 100,
                ($record->capital_recovered_minor + $record->investor_share_minor) / 100, $record->status->label(),
                $record->acknowledged_at?->toDateTimeString(), $record->paid_at?->toDateTimeString(),
            ];
        }

        $rows[] = ['Totals for selected distributions'];
        $rows[] = ['Agreement ID', 'Agreement', 'Funded capital at export', 'Agreement currency', 'Distribution currency',
            'Capital recovered', 'Net profit', 'Investor profit share', 'Farm profit share', 'Investor allocation incl. capital'];
        $groups = $records->groupBy(fn (DistributionRecord $record) => $record->investor_agreement_id.'-'.$record->currency);
        foreach ($groups as $group) {
            $record = $group->first();
            $agreement = $record->investorAgreement;
            $recovered = $group->sum('capital_recovered_minor');
            $investorShare = $group->sum('investor_share_minor');
            $rows[] = [$agreement->id, $agreement->title, $agreement->amount_funded_minor / 100, $agreement->currency,
                $record->currency, $recovered / 100, $group->sum('net_profit_minor') / 100, $investorShare / 100,
                $group->sum('farm_share_minor') / 100, ($recovered + $investorShare) / 100];
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 32, 'B' => 28, 'C' => 26, 'D' => 24, 'E' => 24, 'F' => 26, 'G' => 16, 'H' => 22,
            'I' => 20, 'J' => 30, 'K' => 26, 'L' => 22, 'M' => 16, 'N' => 16, 'O' => 19, 'P' => 16,
            'Q' => 22, 'R' => 20, 'S' => 32, 'T' => 28, 'U' => 22, 'V' => 22];
    }
}
