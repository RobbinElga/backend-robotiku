<?php

namespace App\Http\Controllers\Api\V1\Bayar;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\SchoolAdmin;
use App\Models\Setting;
use App\Services\WhatsappService;
use App\Support\Phone;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\School;

class BillingReminderController extends Controller
{
    use ApiResponse;

    public function __construct(private WhatsappService $wa) {}

    /** Daftar tagihan belum lunas (belum_bayar / menunggu verifikasi). */
    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::with([
            'student:id,name,student_code,registration_type,school_id,parent_id',
            'student.parent:id,name,phone',
            'student.school:id,name',
        ])
            ->whereIn('status', ['belum_bayar', 'menunggu_verifikasi'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn($q) => $q->whereHas('student', fn($s) => $s->where('registration_type', $request->type)))
            ->when($request->filled('search'), fn($q) => $q->whereHas('student', fn($s) =>
            $s->where('name', 'like', '%' . $request->search . '%')->orWhere('student_code', 'like', '%' . $request->search . '%')))
            ->orderByRaw('due_date is null, due_date asc')
            ->paginate($request->integer('per_page', 20));

        return $this->success($invoices, 'Daftar tagihan.');
    }

    /** Link wa.me (kirim manual). */
    public function waLink(Invoice $invoice): JsonResponse
    {
        [$phone, $name, $type] = $this->recipient($invoice);
        if (! $phone) return $this->error('Nomor tujuan tidak ditemukan.', 422);

        $message = $this->buildMessage($invoice, $name, $type);
        $url = 'https://wa.me/' . Phone::toWa($phone) . '?text=' . rawurlencode($message);

        return $this->success(['url' => $url, 'message' => $message, 'phone' => $phone, 'recipient' => $name], 'Link tagihan.');
    }

    /** Kirim otomatis via Fonnte. */
    public function send(Invoice $invoice): JsonResponse
    {
        [$phone, $name, $type] = $this->recipient($invoice);
        if (! $phone) return $this->error('Nomor tujuan tidak ditemukan.', 422);

        try {
            $this->wa->send($phone, $this->buildMessage($invoice, $name, $type));   // ← WhatsappService (Fonnte)
        } catch (\Throwable $e) {
            return $this->error('Gagal mengirim WhatsApp: ' . $e->getMessage(), 502);
        }

        return $this->success(['phone' => $phone, 'recipient' => $name], 'Tagihan terkirim ke ' . $name . '.');
    }

    /** Tentukan penerima: mandiri → ortu; instansi → admin sekolah. */
    private function recipient(Invoice $invoice): array
    {
        $invoice->loadMissing('student.parent', 'student.school');
        $s = $invoice->student;

        if ($s && $s->registration_type === 'instansi' && $s->school_id) {
            $admin = SchoolAdmin::where('school_id', $s->school_id)->where('is_active', true)->whereNotNull('phone')->first();
            $phone = $admin->phone ?? optional($s->school)->contact;               // fallback ke kontak sekolah
            $name  = $admin->name ?? ('Admin ' . optional($s->school)->name);
            return [$phone, $name, 'school'];
        }

        return [optional($s->parent)->phone, optional($s->parent)->name ?? 'Bapak/Ibu', 'parent'];
    }

    private function buildMessage(Invoice $invoice, string $name, string $type): string
    {
        $key = $type === 'school' ? 'wa_message_template_instansi' : 'wa_message_template';
        $template = Setting::where('key', $key)->value('value')
            ?? Setting::where('key', 'wa_message_template')->value('value')
            ?? 'Halo {nama}, tagihan {invoice} a.n. {nama_anak} sebesar {total} (jatuh tempo {jatuh_tempo}). Mohon diselesaikan. Terima kasih.';

        return strtr($template, [
            '{nama}'        => $name,
            '{nama_anak}'   => $invoice->student->name,
            '{total}'       => 'Rp' . number_format((float) $invoice->total_amount, 0, ',', '.'),
            '{invoice}'     => $invoice->invoice_number,
            '{jatuh_tempo}' => $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->translatedFormat('j F Y') : '-',
        ]);
    }

    /** Instansi: daftar sekolah dengan dana belum disetor (lunas, belum masuk setoran). */
    public function instansiSchools(Request $request): JsonResponse
    {
        $invoices = Invoice::with('student:id,school_id,registration_type', 'student.school:id,name,commission_percent')
            ->where('status', 'lunas')
            ->whereHas('student', fn($s) => $s->where('registration_type', 'instansi')->whereNotNull('school_id'))
            ->whereDoesntHave('settlements', fn($q) => $q->whereIn('school_settlements.status', ['menunggu_verifikasi', 'diverifikasi']))
            ->get(['id', 'student_id', 'total_amount']);

        $data = $invoices->groupBy(fn($inv) => optional($inv->student)->school_id)
            ->map(function ($group) {
                $school = optional($group->first()->student)->school;
                if (! $school) return null;
                $gross = (int) $group->sum('total_amount');
                $pct   = (float) ($school->commission_percent ?? 0);
                $comm  = (int) round($gross * $pct / 100);
                return [
                    'id' => $school->id,
                    'name' => $school->name,
                    'commission_percent' => $pct,
                    'invoice_count' => $group->count(),
                    'gross' => $gross,
                    'commission_amount' => $comm,
                    'net' => $gross - $comm
                ];
            })->filter()
            ->when($request->filled('search'), fn($c) => $c->filter(fn($r) => stripos($r['name'], $request->search) !== false))
            ->sortBy('name')->values();

        return $this->success($data, 'Sekolah dengan tagihan belum disetor.');
    }

    /** Instansi: rincian tagihan satu sekolah + total net. */
    public function instansiSchool(School $school): JsonResponse
    {
        $invoices = Invoice::with('student:id,name,student_code')
            ->where('status', 'lunas')
            ->whereHas('student', fn($s) => $s->where('school_id', $school->id)->where('registration_type', 'instansi'))
            ->whereDoesntHave('settlements', fn($q) => $q->whereIn('school_settlements.status', ['menunggu_verifikasi', 'diverifikasi']))
            ->orderByDesc('created_at')
            ->get(['id', 'invoice_number', 'student_id', 'total_amount', 'due_date']);

        $gross = (int) $invoices->sum('total_amount');
        $pct   = (float) ($school->commission_percent ?? 0);
        $comm  = (int) round($gross * $pct / 100);

        return $this->success([
            'school'   => ['id' => $school->id, 'name' => $school->name, 'commission_percent' => $pct],
            'invoices' => $invoices,
            'gross'    => $gross,
            'commission_amount' => $comm,
            'net' => $gross - $comm,
        ], 'Tagihan sekolah.');
    }

    /** Instansi: link wa.me kolektif ke Admin Sekolah. */
    public function instansiSchoolWaLink(School $school): JsonResponse
    {
        [$phone, $name, $gross, $comm, $net, $count] = $this->schoolSummary($school);
        if (! $phone) return $this->error('Nomor Admin Sekolah tidak ditemukan.', 422);
        $message = $this->buildSchoolMessage($school, $name, $gross, $comm, $net, $count);
        $url = 'https://wa.me/' . Phone::toWa($phone) . '?text=' . rawurlencode($message);
        return $this->success(['url' => $url, 'message' => $message, 'phone' => $phone, 'recipient' => $name], 'Link tagihan sekolah.');
    }

    /** Instansi: kirim otomatis (Fonnte) kolektif ke Admin Sekolah. */
    public function instansiSchoolSend(School $school): JsonResponse
    {
        [$phone, $name, $gross, $comm, $net, $count] = $this->schoolSummary($school);
        if (! $phone) return $this->error('Nomor Admin Sekolah tidak ditemukan.', 422);
        try {
            $this->wa->send($phone, $this->buildSchoolMessage($school, $name, $gross, $comm, $net, $count));
        } catch (\Throwable $e) {
            return $this->error('Gagal mengirim WhatsApp: ' . $e->getMessage(), 502);
        }
        return $this->success(['recipient' => $name, 'net' => $net], 'Tagihan setoran terkirim ke ' . $name . '.');
    }

    private function schoolSummary(School $school): array
    {
        $invoices = Invoice::where('status', 'lunas')
            ->whereHas('student', fn($s) => $s->where('school_id', $school->id)->where('registration_type', 'instansi'))
            ->whereDoesntHave('settlements', fn($q) => $q->whereIn('school_settlements.status', ['menunggu_verifikasi', 'diverifikasi']))
            ->get(['id', 'total_amount']);

        $gross = (int) $invoices->sum('total_amount');
        $pct   = (float) ($school->commission_percent ?? 0);
        $comm  = (int) round($gross * $pct / 100);

        $admin = SchoolAdmin::where('school_id', $school->id)->where('is_active', true)->whereNotNull('phone')->first();
        $phone = $admin->phone ?? $school->contact;
        $name  = $admin->name ?? ('Admin ' . $school->name);

        return [$phone, $name, $gross, $comm, $gross - $comm, $invoices->count()];
    }

    private function buildSchoolMessage(School $school, string $name, int $gross, int $comm, int $net, int $count): string
    {
        $template = Setting::where('key', 'wa_message_template_setoran')->value('value')
            ?? 'Halo {nama}, mohon segera menyetorkan dana Robotiku dari {jumlah} siswa {sekolah}. Total terkumpul {gross}, komisi sekolah {komisi}, sehingga yang disetor ke Robotiku adalah {net}. Terima kasih.';

        return strtr($template, [
            '{nama}' => $name,
            '{sekolah}' => $school->name,
            '{jumlah}' => (string) $count,
            '{gross}'  => 'Rp' . number_format($gross, 0, ',', '.'),
            '{komisi}' => 'Rp' . number_format($comm, 0, ',', '.'),
            '{net}'    => 'Rp' . number_format($net, 0, ',', '.'),
        ]);
    }
}
