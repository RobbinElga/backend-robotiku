<?php

namespace Tests\Feature\Murid;

use App\Models\Kelas;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManualSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_trainer_can_create_manual_session_with_null_geo_and_photo(): void
    {
        $trainer = User::create([
            'name' => 'Trainer 1',
            'email' => 'trainer' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'trainer',
            'is_active' => true,
        ]);

        $kelas = Kelas::create([
            'name' => 'Kelas Robotik A',
            'trainer_id' => $trainer->id,
            'meetings_per_period' => 4,
        ]);

        Sanctum::actingAs($trainer);

        $response = $this->postJson('/api/v1/sesi/manual', [
            'class_id' => $kelas->id,
            'date' => '2026-09-28',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.class_id', $kelas->id)
            ->assertJsonPath('data.is_manual', true)
            ->assertJsonPath('data.start_latitude', null)
            ->assertJsonPath('data.start_longitude', null)
            ->assertJsonPath('data.start_photo', null);

        $this->assertDatabaseHas('class_sessions', [
            'class_id' => $kelas->id,
            'trainer_id' => $trainer->id,
            'is_manual' => 1,
            'start_latitude' => null,
            'start_longitude' => null,
            'start_photo' => null,
            'status' => 'started',
        ]);
    }

    public function test_unauthorized_trainer_cannot_create_manual_session(): void
    {
        $trainerOwner = User::create([
            'name' => 'Owner Trainer',
            'email' => 'owner' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'trainer',
            'is_active' => true,
        ]);

        $otherTrainer = User::create([
            'name' => 'Other Trainer',
            'email' => 'other' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'trainer',
            'is_active' => true,
        ]);

        $kelas = Kelas::create([
            'name' => 'Kelas Khusus',
            'trainer_id' => $trainerOwner->id,
        ]);

        Sanctum::actingAs($otherTrainer);

        $response = $this->postJson('/api/v1/sesi/manual', [
            'class_id' => $kelas->id,
            'date' => '2026-09-28',
        ]);

        $response->assertStatus(403);
    }
}
