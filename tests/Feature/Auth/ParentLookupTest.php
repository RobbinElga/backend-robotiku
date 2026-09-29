<?php

namespace Tests\Feature\Auth;

use App\Models\Student;
use App\Models\StudentParent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentLookupTest extends TestCase
{
    use RefreshDatabase;

    private function makeParentWithChildren(int $count, string $phone = '081200000001'): StudentParent
    {
        $parent = StudentParent::create(['name' => 'Budi', 'phone' => $phone]);

        for ($i = 1; $i <= $count; $i++) {
            Student::create([
                'student_code'      => 'ROBO-T' . $i . $phone,
                'name'              => "Anak $i Budi",
                'gender'            => 'L',
                'parent_id'         => $parent->id,
                'status'            => 'aktif',
                'registration_type' => 'mandiri',
            ]);
        }

        return $parent;
    }

    public function test_lookup_dengan_nomor_hp_satu_anak(): void
    {
        $this->makeParentWithChildren(1);

        $this->postJson('/api/v1/auth/parent/lookup', ['phone' => '081200000001'])
            ->assertOk()
            ->assertJsonPath('data.multiple', false)
            ->assertJsonCount(1, 'data.students');
    }

    public function test_lookup_hp_banyak_anak_mengembalikan_pilihan(): void
    {
        $this->makeParentWithChildren(2);

        $this->postJson('/api/v1/auth/parent/lookup', ['phone' => '6281200000001'])
            ->assertOk()
            ->assertJsonPath('data.multiple', true)
            ->assertJsonCount(2, 'data.students');
    }

    public function test_lookup_dengan_nama_anak(): void
    {
        $this->makeParentWithChildren(1);

        $this->postJson('/api/v1/auth/parent/lookup', ['name' => 'Anak 1'])
            ->assertOk()->assertJsonPath('status', true);
    }

    public function test_lookup_tanpa_input_gagal_validasi(): void
    {
        $this->postJson('/api/v1/auth/parent/lookup', [])
            ->assertStatus(422);
    }

    public function test_lookup_tidak_ketemu(): void
    {
        $this->postJson('/api/v1/auth/parent/lookup', ['phone' => '089999999999'])
            ->assertStatus(404);
    }

    public function test_ortu_via_instansi_tidak_muncul(): void
    {
        Student::create([
            'student_code'      => 'ROBO-INST1',
            'name'              => 'Anak Instansi',
            'gender'            => 'P',
            'registration_type' => 'instansi', // tanpa parent_id
            'status'            => 'aktif',
        ]);

        $this->postJson('/api/v1/auth/parent/lookup', ['name' => 'Anak Instansi'])
            ->assertStatus(404);
    }

    public function test_lookup_mengembalikan_konteks_sekolah(): void
    {
        $parent = StudentParent::create(['name' => 'Siti', 'phone' => '081233334444']);
        $school = \App\Models\School::create([
            'name' => 'SDIT Robotika Pintar',
            'pipeline_status' => 'sudah_mou',
            'self_managed' => true,
        ]);

        Student::create([
            'student_code'      => 'ROBO-INST-001',
            'name'              => 'Anak Binaan',
            'gender'            => 'L',
            'parent_id'         => $parent->id,
            'school_id'         => $school->id,
            'status'            => 'aktif',
            'registration_type' => 'instansi',
        ]);

        $res = $this->postJson('/api/v1/auth/parent/lookup', ['phone' => '081233334444'])
            ->assertOk();

        $this->assertSame('SDIT Robotika Pintar', $res->json('data.students.0.school'));
    }
}
