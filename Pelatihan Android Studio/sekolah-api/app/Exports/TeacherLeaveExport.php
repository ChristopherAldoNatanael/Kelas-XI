<?php

namespace App\Exports;

use App\Models\Leave;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class TeacherLeaveExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function query()
    {
        $query = Leave::leftJoin('teachers as t', 'leaves.teacher_id', '=', 't.id')
            ->leftJoin('teachers as st', 'leaves.substitute_teacher_id', '=', 'st.id')
            ->leftJoin('users as ab', 'leaves.approved_by', '=', 'ab.id')
            ->select('leaves.*', 't.nama as teacher_nama', 'st.nama as substitute_teacher_nama', 'ab.name as approved_by_name');

        // Apply same filters as the controller
        if ($this->request->filled('status')) {
            $query->where('status', $this->request->status);
        }

        if ($this->request->filled('teacher_id')) {
            $query->where('teacher_id', $this->request->teacher_id);
        }

        if ($this->request->filled('date_from') && $this->request->filled('date_to')) {
            $query->where(function ($q) {
                $q->whereBetween('start_date', [$this->request->date_from, $this->request->date_to])
                    ->orWhereBetween('end_date', [$this->request->date_from, $this->request->date_to])
                    ->orWhere(function ($subQ) {
                        $subQ->where('start_date', '<=', $this->request->date_from)
                            ->where('end_date', '>=', $this->request->date_to);
                    });
            });
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'No',
            'Teacher',
            'Reason',
            'Custom Reason',
            'Start Date',
            'End Date',
            'Duration (Days)',
            'Substitute Teacher',
            'Status',
            'Approved By',
            'Approved At',
            'Rejection Reason',
            'Created At'
        ];
    }

    public function map($leave): array
    {
        static $counter = 0;
        $counter++;

        // Calculate duration
        $startDate = \Carbon\Carbon::parse($leave->start_date);
        $endDate = \Carbon\Carbon::parse($leave->end_date);
        $duration = $startDate->diffInDays($endDate) + 1;

        // Format reason
        $reason = $leave->reason;
        if ($leave->reason === 'lainnya' && $leave->custom_reason) {
            $reason = $leave->custom_reason;
        } elseif ($leave->reason !== 'lainnya') {
            $reason = ucwords(str_replace('_', ' ', $leave->reason));
        }

        return [
            $counter,
            $leave->teacher_nama ?? 'N/A',
            $reason,
            $leave->custom_reason ?? '-',
            \Carbon\Carbon::parse($leave->start_date)->format('d/m/Y'),
            \Carbon\Carbon::parse($leave->end_date)->format('d/m/Y'),
            $duration,
            $leave->substitute_teacher_nama ?? '-',
            ucfirst($leave->status),
            $leave->approved_by_name ?? '-',
            $leave->approved_at ? \Carbon\Carbon::parse($leave->approved_at)->format('d/m/Y H:i') : '-',
            $leave->rejection_reason ?? '-',
            \Carbon\Carbon::parse($leave->created_at)->format('d/m/Y H:i')
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Style the header row
        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '007BFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Set header row height
        $sheet->getRowDimension(1)->setRowHeight(20);

        return [
            // Style the entire sheet
            1 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
