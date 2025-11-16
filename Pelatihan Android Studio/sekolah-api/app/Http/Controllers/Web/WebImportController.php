<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WebImportController extends Controller
{
    public function index()
    {
        return view('import.index');
    }

    public function downloadTemplate(string $type)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_'.$type.'.csv"',
        ];
        $columns = match ($type) {
            'users' => ['nama','email','password','role'],
            'teachers' => ['user_email','nip','teacher_code','position','department'],
            'subjects' => ['name','code','category','credit_hours','semester'],
            'classes' => ['name','level','major','academic_year','capacity'],
            'classrooms' => ['name','code','type','capacity','building','floor'],
            'schedules' => ['class_id','subject_id','teacher_id','classroom_id','day_of_week','period_number','start_time','end_time','semester','academic_year'],
            default => ['column1','column2']
        };

        $callback = function() use ($columns) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, $columns);
            fclose($fh);
        };
        return new StreamedResponse($callback, 200, $headers);
    }

    public function import(Request $request)
    {
        $request->validate([
            'type' => 'required|in:users,teachers,subjects,classes,classrooms,schedules',
            'file' => 'required|mimes:xlsx,csv,txt|max:2048',
        ]);

        $type = $request->input('type');
        $file = $request->file('file');

        // Arsipkan file ke storage dan baca dari real path sementara (stabil di Windows)
        $path = $file->store('imports');
        $realPath = $file->getRealPath();
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        $mime = strtolower($file->getMimeType() ?: '');

        // Tolak XLSX agar tidak perlu dependency tambahan
        if ($ext === 'xlsx' || str_contains($mime, 'spreadsheetml')) {
            return back()->with('error', 'Format XLSX belum didukung tanpa paket tambahan. Silakan Save As CSV lalu upload ulang.');
        }

        // Tentukan file sumber yang bisa dibaca
        $source = is_string($realPath) && is_file($realPath)
            ? $realPath
            : storage_path('app/'.$path);
        if (!is_file($source)) {
            return back()->with('error', 'File upload tidak ditemukan. Coba ulangi proses import.');
        }

        // Parse CSV/TXT: deteksi delimiter (comma/semicolon/tab), handle BOM
        $rows = [];
        $handle = @fopen($source, 'r');
        if ($handle === false) {
            return back()->with('error', 'Gagal membuka file upload untuk dibaca.');
        }
        $first = fgets($handle);
        if ($first === false) {
            fclose($handle);
            return back()->with('error', 'File kosong atau tidak dapat dibaca.');
        }
        // Hapus BOM jika ada
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $counts = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")];
        arsort($counts);
        $delimiter = key($counts);
        if (($counts[$delimiter] ?? 0) === 0) { $delimiter = ','; }
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            fclose($handle);
            return back()->with('error', 'Header CSV tidak valid. Gunakan template yang disediakan.');
        }
        // Normalisasi header
        $header = array_map(function($h){
            $h = preg_replace('/^\xEF\xBB\xBF/', '', (string)$h);
            return trim($h, " \"'\t\n\r");
        }, $header);

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($data) !== count($header)) { continue; }
            $clean = array_map(function($v){ return is_string($v) ? trim($v, " \"'\t\n\r") : $v; }, $data);
            $rows[] = array_combine($header, $clean);
        }
        fclose($handle);

        $inserted = 0; $failed = 0; $messages = [];
        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                try {
                    match ($type) {
                        'users' => $this->importUser($row),
                        'teachers' => $this->importTeacher($row),
                        'subjects' => $this->importSubject($row),
                        'classes' => $this->importClass($row),
                        'classrooms' => $this->importClassroom($row),
                        'schedules' => $this->importSchedule($row),
                    };
                    $inserted++;
                } catch (\Throwable $e) {
                    $failed++;
                    $messages[] = 'Row '.($i+2).': '.$e->getMessage();
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Import gagal: '.$e->getMessage());
        }

        // Redirect ke halaman yang diminta jika ada
        $redirectTo = $request->input('redirect_to');
        if ($redirectTo) {
            return redirect()->to($redirectTo)
                ->with('success', "Import selesai. Berhasil: $inserted, Gagal: $failed")
                ->with('details', $messages);
        }
        return back()->with('success', "Import selesai. Berhasil: $inserted, Gagal: $failed")->with('details', $messages);
    }

    private function importUser(array $row): void
    {
        $data = Validator::make($row, [
            'nama' => 'required|string|max:100',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,kurikulum,kepala-sekolah,siswa',
        ])->validate();

        \App\Models\User::updateOrCreate(
            ['email' => $data['email']],
            [
                'nama' => $data['nama'],
                'password' => bcrypt($data['password']),
                'role' => $data['role'],
                'status' => 'active',
            ]
        );
    }

    private function importTeacher(array $row): void
    {
        $data = Validator::make($row, [
            'user_email' => 'required|email',
            'nip' => 'nullable|string|max:50',
            'teacher_code' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
        ])->validate();

        $user = \App\Models\User::where('email', $data['user_email'])->firstOrFail();
        \App\Models\Teacher::updateOrCreate(
            ['user_id' => $user->id],
            [
                'nip' => $data['nip'] ?? '',
                'teacher_code' => $data['teacher_code'] ?? '',
                'position' => $data['position'] ?? '',
                'department' => $data['department'] ?? '',
                'status' => 'active',
            ]
        );
    }

    private function importSubject(array $row): void
    {
        $data = Validator::make($row, [
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20',
            'category' => 'nullable|string|max:50',
            'credit_hours' => 'nullable|integer|min:1|max:12',
            'semester' => 'nullable|integer|min:1|max:12',
        ])->validate();

        \App\Models\Subject::updateOrCreate(
            ['code' => $data['code']],
            [
                'name' => $data['name'],
                'category' => $data['category'] ?? '',
                'credit_hours' => $data['credit_hours'] ?? 2,
                'semester' => $data['semester'] ?? 1,
                'status' => 'active',
            ]
        );
    }

    private function importClass(array $row): void
    {
        $data = Validator::make($row, [
            'name' => 'required|string|max:100',
            'level' => 'required|integer|min:10|max:12',
            'major' => 'required|string|max:50',
            'academic_year' => 'required|string|max:20',
            'capacity' => 'nullable|integer|min:1|max:60',
        ])->validate();

        \App\Models\ClassModel::updateOrCreate(
            ['name' => $data['name'], 'level' => $data['level'], 'major' => $data['major']],
            [
                'academic_year' => $data['academic_year'],
                'capacity' => $data['capacity'] ?? 36,
                'status' => 'active',
            ]
        );
    }

    private function importClassroom(array $row): void
    {
        $data = Validator::make($row, [
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20',
            'type' => 'required|string|max:50',
            'capacity' => 'nullable|integer|min:1|max:100',
            'building' => 'nullable|string|max:50',
            'floor' => 'nullable|integer|min:0|max:10',
        ])->validate();

        \App\Models\Classroom::updateOrCreate(
            ['code' => $data['code']],
            [
                'name' => $data['name'],
                'type' => $data['type'],
                'capacity' => $data['capacity'] ?? 36,
                'building' => $data['building'] ?? '',
                'floor' => $data['floor'] ?? 1,
                'status' => 'available',
            ]
        );
    }

    private function importSchedule(array $row): void
    {
        $data = Validator::make($row, [
            'class_id' => 'required|integer|min:1',
            'subject_id' => 'required|integer|min:1',
            'teacher_id' => 'required|integer|min:1',
            'classroom_id' => 'nullable|integer|min:1',
            'day_of_week' => 'required|string|max:20',
            'period_number' => 'required|integer|min:1|max:12',
            'start_time' => 'required',
            'end_time' => 'required',
            'semester' => 'required|string|max:20',
            'academic_year' => 'required|string|max:20',
        ])->validate();

        \App\Models\Schedule::create([
            'class_id' => $data['class_id'],
            'subject_id' => $data['subject_id'],
            'teacher_id' => $data['teacher_id'],
            'classroom_id' => $data['classroom_id'] ?? null,
            'day_of_week' => strtolower($data['day_of_week']),
            'period_number' => $data['period_number'],
            'start_time' => date('H:i:s', strtotime($data['start_time'])),
            'end_time' => date('H:i:s', strtotime($data['end_time'])),
            'semester' => $data['semester'],
            'academic_year' => $data['academic_year'],
            'status' => 'active',
        ]);
    }
}




}    }        ]);            'status' => 'active',            'academic_year' => $data['academic_year'],            'semester' => $data['semester'],            'end_time' => date('H:i:s', strtotime($data['end_time'])),            'start_time' => date('H:i:s', strtotime($data['start_time'])),            'period_number' => $data['period_number'],            'day_of_week' => strtolower($data['day_of_week']),            'classroom_id' => $data['classroom_id'] ?? null,            'teacher_id' => $data['teacher_id'],            'subject_id' => $data['subject_id'],            'class_id' => $data['class_id'],        \App\Models\Schedule::create([        ])->validate();            'academic_year' => 'required|string|max:20',            'semester' => 'required|string|max:20',            'end_time' => 'required',            'start_time' => 'required',            'period_number' => 'required|integer|min:1|max:12',            'day_of_week' => 'required|string|max:20',            'classroom_id' => 'nullable|integer|min:1',            'teacher_id' => 'required|integer|min:1',            'subject_id' => 'required|integer|min:1',            'class_id' => 'required|integer|min:1',        $data = Validator::make($row, [    {    private function importSchedule(array $row): void    }        );            ]                'status' => 'available',                'floor' => $data['floor'] ?? 1,                'building' => $data['building'] ?? '',    }

    private function importSchedule(array $row): void
    {
        $data = Validator::make($row, [
            'class_id' => 'required|integer|min:1',
            'subject_id' => 'required|integer|min:1',
            'teacher_id' => 'required|integer|min:1',
            'classroom_id' => 'nullable|integer|min:1',
            'day_of_week' => 'required|string|max:20',
            'period_number' => 'required|integer|min:1|max:12',
            'start_time' => 'required',
            'end_time' => 'required',
            'semester' => 'required|string|max:20',
            'academic_year' => 'required|string|max:20',
        ])->validate();

        \App\Models\Schedule::create([
            'class_id' => $data['class_id'],
            'subject_id' => $data['subject_id'],
            'teacher_id' => $data['teacher_id'],
            'classroom_id' => $data['classroom_id'] ?? null,
            'day_of_week' => strtolower($data['day_of_week']),
            'period_number' => $data['period_number'],
            'start_time' => date('H:i:s', strtotime($data['start_time'])),
            'end_time' => date('H:i:s', strtotime($data['end_time'])),
            'semester' => $data['semester'],
            'academic_year' => $data['academic_year'],
            'status' => 'active',
        ]);
    }
}
            'academic_year' => $data['academic_year'],
            'status' => 'active',
        ]);
    }
}























}    }        ]);            'status' => 'active',            'academic_year' => $data['academic_year'],            'semester' => $data['semester'],            'end_time' => date('H:i:s', strtotime($data['end_time'])),            'start_time' => date('H:i:s', strtotime($data['start_time'])),            'period_number' => $data['period_number'],            'day_of_week' => strtolower($data['day_of_week']),            'classroom_id' => $data['classroom_id'] ?? null,            'teacher_id' => $data['teacher_id'],            'subject_id' => $data['subject_id'],            'class_id' => $data['class_id'],        \App\Models\Schedule::create([        ])->validate();            'academic_year' => 'required|string|max:20',            'semester' => 'required|string|max:20',            'end_time' => 'required',            'start_time' => 'required',            'period_number' => 'required|integer|min:1|max:12',            'day_of_week' => 'required|string|max:20',            'classroom_id' => 'nullable|integer|min:1',            'teacher_id' => 'required|integer|min:1',            'subject_id' => 'required|integer|min:1',            'class_id' => 'required|integer|min:1',        $data = Validator::make($row, [    {    private function importSchedule(array $row): void    }        );            ]            'status' => 'active',
        ]);
    }
}
