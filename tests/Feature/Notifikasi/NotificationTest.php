<?php

namespace Tests\Feature\Notifikasi;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function notif(int $userId, bool $read = false): Notification
    {
        return Notification::create([
            'recipient_type' => 'user',
            'recipient_id' => $userId,
            'title' => 'Pendaftaran Baru',
            'message' => 'Ada siswa baru.',
            'type' => 'pendaftaran_baru',
            'is_read' => $read,
        ]);
    }

    public function test_list_notifikasi_milik_sendiri(): void
    {
        $me = User::create(['name' => 'AK', 'email' => 'ak@r.id', 'password' => bcrypt('x'), 'role' => 'admin_keuangan', 'is_active' => true]);
        $other = User::create(['name' => 'X', 'email' => 'x@r.id', 'password' => bcrypt('x'), 'role' => 'admin', 'is_active' => true]);
        $this->notif($me->id);
        $this->notif($me->id);
        $this->notif($other->id); // milik orang lain

        Sanctum::actingAs($me);
        $this->getJson('/api/v1/notifikasi')->assertOk()->assertJsonCount(2, 'data.data');
    }

    public function test_unread_count(): void
    {
        $me = User::create(['name' => 'AK', 'email' => 'ak@r.id', 'password' => bcrypt('x'), 'role' => 'admin_keuangan', 'is_active' => true]);
        $this->notif($me->id, read: false);
        $this->notif($me->id, read: true);

        Sanctum::actingAs($me);
        $this->getJson('/api/v1/notifikasi/unread-count')->assertOk()->assertJsonPath('data.unread', 1);
    }

    public function test_mark_read(): void
    {
        $me = User::create(['name' => 'AK', 'email' => 'ak@r.id', 'password' => bcrypt('x'), 'role' => 'admin_keuangan', 'is_active' => true]);
        $n = $this->notif($me->id);

        Sanctum::actingAs($me);
        $this->patchJson("/api/v1/notifikasi/{$n->id}/read")->assertOk();
        $this->assertDatabaseHas('notifications', ['id' => $n->id, 'is_read' => true]);
    }

    public function test_mark_all_read(): void
    {
        $me = User::create(['name' => 'AK', 'email' => 'ak@r.id', 'password' => bcrypt('x'), 'role' => 'admin_keuangan', 'is_active' => true]);
        $this->notif($me->id);
        $this->notif($me->id);

        Sanctum::actingAs($me);
        $this->patchJson('/api/v1/notifikasi/read-all')->assertOk();
        $this->assertSame(0, Notification::where('recipient_id', $me->id)->where('is_read', false)->count());
    }

    public function test_tidak_bisa_mark_notifikasi_orang_lain(): void
    {
        $me = User::create(['name' => 'AK', 'email' => 'ak@r.id', 'password' => bcrypt('x'), 'role' => 'admin_keuangan', 'is_active' => true]);
        $other = User::create(['name' => 'X', 'email' => 'x@r.id', 'password' => bcrypt('x'), 'role' => 'admin', 'is_active' => true]);
        $n = $this->notif($other->id);

        Sanctum::actingAs($me);
        $this->patchJson("/api/v1/notifikasi/{$n->id}/read")->assertStatus(403);
    }
}
