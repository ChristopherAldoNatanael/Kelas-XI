# 🔄 DATABASE STRUCTURE REPLACEMENT GUIDE

## 📋 OVERVIEW

This guide will help you replace your existing database structure with the new structure you provided. The process includes automatic backup creation and Laravel model compatibility.

## 🎯 WHAT THIS REPLACEMENT DOES

- **Creates automatic backup** of your current database
- **Drops all existing tables** and recreates with new structure
- **Preserves data** (limited - see limitations below)
- **Updates Laravel caches** automatically
- **Provides rollback capability** via backup file

## 📊 NEW DATABASE STRUCTURE

The new structure includes these main tables:

| Table Name | Description | Key Changes |
|------------|-------------|-------------|
| `users` | User accounts with roles | New role enum: admin, siswa, kurikulum, kepala_sekolah |
| `classes` | Class information | Simple structure with nama_kelas, kode_kelas |
| `schedules` | Class schedules | Uses Indonesian day names (Senin, Selasa, etc.) |
| `teachers` | Teacher accounts | Separate table from users |
| `subjects` | Subject information | Simple structure with nama, kode |
| `teacher_attendances` | Attendance tracking | Linked to schedules and teachers |
| **Plus Laravel system tables** | Cache, sessions, jobs, etc. | Standard Laravel structure |

## 🚀 QUICK START

### Option 1: Windows Batch File (Easiest)
1. Double-click `replace-database.bat`
2. Follow the prompts
3. Type `yes` when asked to confirm

### Option 2: Command Line
```bash
cd sekolah-api
php replace-database-structure.php
```

### Option 3: Manual SQL Execution
1. Open phpMyAdmin or MySQL client
2. Import `database_schema_new.sql`

## ⚠️ IMPORTANT CONSIDERATIONS

### Data Loss
- **All existing data will be lost** during replacement
- Only user accounts with specific credentials will be preserved
- This is a **complete structure replacement**

### Backward Compatibility
- **API endpoints may need updates** due to structure changes
- **Controller code may need adjustments** for field names
- **Laravel models may need updates** for new table relationships

### Preserved Data
Based on your SQL dump, these accounts will be preserved:
- User ID 1: zupa.admin@sekolah.com (siswa role)
- User ID 2: siti.kurikulum@sekolah.com (kurikulum role)

## 🛠️ LARAVEL MODEL UPDATES NEEDED

After replacement, you'll need to update these Laravel files:

### 1. User Model (app/Models/User.php)
```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'mata_pelajaran', 'is_banned'
    ];

    protected $hidden = [
        'password', 'remember_token'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_banned' => 'boolean'
    ];

    protected $attributes = [
        'role' => 'siswa',
        'is_banned' => false
    ];
}
```

### 2. Schedule Model (app/Models/Schedule.php)
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'hari', 'kelas', 'mata_pelajaran', 'guru_id', 
        'jam_mulai', 'jam_selesai', 'ruang'
    ];

    protected $casts = [
        'jam_mulai' => 'datetime:H:i',
        'jam_selesai' => 'datetime:H:i'
    ];

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }
}
```

### 3. Class Model (app/Models/ClassModel.php)
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassModel extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'nama_kelas', 'kode_kelas'
    ];
}
```

### 4. Teacher Model (app/Models/Teacher.php)
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Teacher extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'mata_pelajaran', 'is_banned'
    ];

    protected $hidden = [
        'password', 'remember_token'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_banned' => 'boolean'
    ];
}
```

### 5. Subject Model (app/Models/Subject.php)
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama', 'kode'
    ];
}
```

### 6. Teacher Attendance Model (app/Models/TeacherAttendance.php)
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeacherAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id', 'guru_id', 'guru_asli_id', 'tanggal',
        'jam_masuk', 'status', 'keterangan', 'created_by', 'assigned_by'
    ];

    protected $casts = [
        'tanggal' => 'date',
        'jam_masuk' => 'datetime:H:i'
    ];

    protected $attributes = [
        'status' => 'tidak_hadir'
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

## 🔄 CONTROLLER UPDATES NEEDED

### API Routes Changes

Update `routes/api.php` for new structure:
```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TeacherAttendanceController;
use App\Http\Controllers\Api\DropdownController;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    
    // Dropdown data
    Route::get('/dropdown/classes', [DropdownController::class, 'classes']);
    Route::get('/dropdown/subjects', [DropdownController::class, 'subjects']);
    Route::get('/dropdown/teachers', [DropdownController::class, 'teachers']);
    Route::get('/dropdown/schedules', [DropdownController::class, 'schedules']);
    
    // User management
    Route::apiResource('users', UserController::class);
    
    // Schedule management
    Route::apiResource('schedules', ScheduleController::class);
    
    // Teacher attendance
    Route::apiResource('teacher-attendances', TeacherAttendanceController::class);
    
    // Attendance tracking
    Route::get('/attendance/schedule/{scheduleId}', [TeacherAttendanceController::class, 'bySchedule']);
    Route::get('/attendance/teacher/{teacherId}', [TeacherAttendanceController::class, 'byTeacher']);
    Route::get('/attendance/date/{date}', [TeacherAttendanceController::class, 'byDate']);
});
```

## 🎛️ LARAVEL CONFIG UPDATES

### 1. Update config/auth.php
```php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
],
```

### 2. Update config/sanctum.php
```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
))),
```

## 🔍 TESTING AFTER REPLACEMENT

### 1. Database Connection Test
```bash
php artisan tinker
>>> DB::connection()->getPdo();
>>> exit
```

### 2. Basic Model Test
```bash
php artisan tinker
>>> App\Models\User::count();
>>> App\Models\Schedule::count();
>>> App\Models\ClassModel::count();
>>> exit
```

### 3. API Endpoint Test
```bash
# Test login
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"zupa.admin@sekolah.com","password":"password"}'

# Test getting users
curl -X GET http://localhost:8000/api/users \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## 🔙 ROLLBACK PROCEDURE

If something goes wrong, you can restore from backup:

```bash
# 1. Stop Laravel server
# Ctrl+C in the terminal

# 2. Restore from backup
mysql -u root -p db_sekolah < backup_db_sekolah_TIMESTAMP.sql

# 3. Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 4. Restart server
php artisan serve
```

## 📝 POST-REPLACEMENT CHECKLIST

- [ ] Database connection works
- [ ] All Laravel models updated
- [ ] API routes updated
- [ ] Controllers updated for new field names
- [ ] Login functionality works
- [ ] Schedule creation/editing works
- [ ] Teacher attendance tracking works
- [ ] Dropdown data loads correctly
- [ ] Tests pass

## 🚨 TROUBLESHOOTING

### Common Issues

**1. "Table doesn't exist" errors**
```bash
# Run migrations to create missing Laravel tables
php artisan migrate:fresh
```

**2. Model relationship errors**
- Check that foreign key relationships match new structure
- Verify column names in model relationships

**3. API authentication errors**
- Verify Sanctum is configured correctly
- Check API token generation

**4. Data validation errors**
- Update validation rules to match new field names
- Check enum values for role field

## 📞 SUPPORT

If you encounter issues:

1. Check the backup file timestamp
2. Verify all Laravel model updates are complete
3. Test each API endpoint individually
4. Review Laravel logs: `storage/logs/laravel.log`

---

**Created:** November 16, 2025  
**Status:** Ready for Use  
**Version:** 1.0
