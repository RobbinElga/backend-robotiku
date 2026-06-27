<?php

namespace Tests\Feature\Daftar;

use App\Models\BillingSetting;
use App\Models\Kelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramListTest extends TestCase
{
    use RefreshDatabase;

    public function test_hanya_kelas_berharga_yang_muncul(): void
    {
        $k = Kelas::create(['name' => 'Robo Kids']);
        BillingSetting::create(['class_id' => $k->id, 'registration_fee' => 150000, 'price_per_cycle' => 200000]);
        Kelas::create(['name' => 'Belum Ada Harga']); // tanpa billing → tidak muncul

        $this->getJson('/api/v1/programs')->assertOk()->assertJsonCount(1, 'data');
    }
}
