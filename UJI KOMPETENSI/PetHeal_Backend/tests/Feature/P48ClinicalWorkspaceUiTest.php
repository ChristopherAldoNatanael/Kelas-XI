<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\ClinicalTemplate;
use App\Models\ConsentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P4.10 — ruang klinis web admin (lab, rawat inap, rujukan, template, dll).
 */
class P48ClinicalWorkspaceUiTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'P48 ' . $suffix,
            'slug' => 'p48-' . $suffix,
            'address' => 'Jl. P48',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin P48',
            'email' => 'admin' . $suffix . '@p48.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinic->id,
        ]);
    }

    public function test_clinical_workspace_renders_all_sections(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.clinical.index'))
            ->assertOk()
            ->assertSee('Ruang Klinis')
            ->assertSee('Order laboratorium')
            ->assertSee('Rawat inap')
            ->assertSee('Rujukan')
            ->assertSee('Laporan penyakit menular')
            ->assertSee('Template klinis')
            ->assertSee('Ekspor rekam medis')
            ->assertSee('Kesejahteraan hewan')
            ->assertSee('role="tab"', false)
            ->assertSee('aria-selected="true"', false)
            ->assertSee('role="tabpanel"', false);
    }

    public function test_publish_clinical_and_consent_templates(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.clinical.templates.clinical'), [
                'template_key' => 'pemeriksaan_umum',
                'name' => 'Pemeriksaan Umum',
                'species' => 'Kucing',
                'schema' => json_encode([
                    'fields' => [
                        ['key' => 'suhu', 'label' => 'Suhu', 'type' => 'number'],
                    ],
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(
            ClinicalTemplate::where('clinic_id', $this->clinic->id)
                ->where('template_key', 'pemeriksaan_umum')
                ->where('is_current', true)
                ->where('version', 1)
                ->exists()
        );

        $this->actingAs($this->admin)
            ->post(route('admin.clinical.templates.consent'), [
                'template_key' => 'tindakan_umum',
                'name' => 'Persetujuan Tindakan',
                'consent_type' => 'tindakan',
                'body' => 'Saya menyetujui tindakan yang dijelaskan dokter.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $template = ConsentTemplate::where('clinic_id', $this->clinic->id)
            ->where('template_key', 'tindakan_umum')
            ->where('is_current', true)
            ->firstOrFail();

        $this->assertSame(1, $template->version);
        $this->assertSame(hash('sha256', 'Saya menyetujui tindakan yang dijelaskan dokter.'), $template->body_hash);
    }

    public function test_template_requires_valid_schema(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.clinical.templates.clinical'), [
                'template_key' => 'rusak',
                'name' => 'Rusak',
                'schema' => '{"fields":[]}',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertFalse(
            ClinicalTemplate::where('clinic_id', $this->clinic->id)->exists()
        );
    }
}
