<?php

namespace App\Exports;

use App\Models\TeacherAttendance;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class TeacherAttendanceExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function query()
    {
        $query = TeacherAttendance::query()
            ->with(['schedule.subject', 'schedule.class', 'guru', 'guruAsli']);

        // Apply same filters as the controller
        if ($this->request->filled('date_from') && $this->request->filled('date_to')) {
            $query->whereBetween('tanggal', [$this->request->date_from, $this->request->date_to]);
        } elseif ($this->request->filled('date_from')) {
            $query->where('tanggal', '>=', $this->request->date_from);
        } elseif ($this->request->filled('date_to')) {
            $query->where('tanggal', '<=', $this->request->date_to);
        }

        if ($this->request->filled('subject_id')) {
            $subject = \App\Models\Subject::find($this->request->subject_id);
            if ($subject) {
                $query->whereHas('schedule', function ($q) use ($subject) {
                    $q->where('mata_pelajaran', $subject->nama);
                });
            }
        }

        if ($this->request->filled('teacher_id')) {
            $query->where('guru_id', $this->request->teacher_id);
        }

        if ($this->request->filled('status')) {
            if ($this->request->status === 'present') {
                $query->whereIn('status', ['hadir', 'diganti']);
            } elseif ($this->request->status === 'absent') {
                $query->whereIn('status', ['tidak_hadir']);
            } elseif ($this->request->status === 'late') {
                $query->where('status', 'telat');
            } elseif ($this->request->status === 'on_leave') {
                $query->where('status', 'diganti');
            }
        }

        return $query->orderBy('tanggal', 'desc')->orderBy('jam_masuk');
    }

    public function headings(): array
    {
        return [
            'No',
            'Date',
            'Time',
            'Teacher',
            'Subject',
            'Class',
            'Status',
            'Notes'
        ];
    }

    public function map($attendance): array
    {
        static $counter = 0;
        $counter++;

        return [
            $counter,
            \Carbon\Carbon::parse($attendance->tanggal)->format('d/m/Y'),
            $attendance->jam_masuk ? \Carbon\Carbon::parse($attendance->jam_masuk)->format('H:i') : '-',
            $attendance->guru->name ?? 'N/A',
            $attendance->schedule->subject->nama_mapel ?? $attendance->schedule->mata_pelajaran ?? 'N/A',
            $attendance->schedule->class_model->nama_kelas ?? $attendance->schedule->kelas ?? 'N/A',
            ucfirst(str_replace('_', ' ', $attendance->status)),
            $attendance->keterangan ?? '-'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Style the header row
        $sheet->getStyle('A1:H1')->applyFromArray([
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
