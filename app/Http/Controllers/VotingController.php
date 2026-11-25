<?php

namespace App\Http\Controllers;

use App\Models\Rapat;
use App\Models\Voting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use OpenApi\Attributes as OA;

class VotingController extends CrudController
{
    protected string $model = Voting::class;

    public function __construct()
    {
        $this->with = [
            'rapat.anggotaRapats',
            'opsiVotings' => fn ($q) => $q->withCount('suaraVotings'),
        ];
    }

    protected function rules(?int $id = null): array
    {
        return [
            'id_rapat' => [$id ? 'sometimes' : 'required', 'exists:rapats,id_rapat'],
            'judul_voting' => [$id ? 'sometimes' : 'required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', \Illuminate\Validation\Rule::in(['draft', 'dibuka', 'ditutup'])],
            'waktu_mulai' => ['nullable', 'date'],
            'waktu_selesai' => ['nullable', 'date', 'after:waktu_mulai'],
            'opsi' => ['nullable', 'array'],
            'opsi.*' => ['required', 'string', 'max:255'],
        ];
    }

    #[OA\Get(
        path: '/voting',
        summary: 'Daftar Sesi E-Voting Sidang Senat',
        description: 'Mengambil daftar sesi pemungutan suara (e-voting) rapat senat beserta jumlah suara. Filter status menerima nilai legacy baca (aktif/active/open/berlangsung => dibuka, selesai/closed => ditutup). Filter active=1 hanya mengembalikan sesi berstatus terbuka yang jendela waktunya sedang berjalan (masa depan dan kedaluwarsa dikecualikan). all=1 mengembalikan {data:[...]} tanpa paginasi; selain itu default 15 per halaman.',
        tags: ['Voting'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'dibuka', 'ditutup', 'aktif', 'open', 'berlangsung', 'selesai', 'closed', 'active'])),
            new OA\Parameter(name: 'active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar voting terpaginasi')
        ]
    )]
    public function index(?Request $request = null)
    {
        $request = $request ?? request();
        $query = Voting::with($this->with)->withCount('suaraVotings');

        if ($request->filled('status')) {
            $query->whereIn('status', self::statusVariants((string) $request->query('status')));
        } elseif ($request->boolean('active') || $request->query('active') === '1') {
            $query->whereIn('status', ['dibuka', 'aktif', 'active', 'open', 'berlangsung'])
                ->where(fn ($q) => $q->whereNull('waktu_mulai')->orWhere('waktu_mulai', '<=', now()))
                ->where(fn ($q) => $q->whereNull('waktu_selesai')->orWhere('waktu_selesai', '>', now()));
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->latest()->get()]);
        }

        return $query->latest()->paginate(15);
    }

    public static function statusVariants(string $status): array
    {
        $status = strtolower($status);

        return match ($status) {
            'dibuka', 'aktif', 'active', 'open', 'berlangsung' => ['dibuka', 'aktif', 'active', 'open', 'berlangsung'],
            'ditutup', 'selesai', 'closed' => ['ditutup', 'selesai', 'closed'],
            default => [$status],
        };
    }

    #[OA\Post(
        path: '/voting',
        summary: 'Buat Sesi E-Voting Baru',
        description: 'Membuat agenda pemungutan suara sidang senat beserta opsi pilihan (default: Setuju, Tidak Setuju, Abstain) secara atomik. Hanya satu sesi boleh berstatus dibuka: sesi lain yang masih terbuka otomatis ditutup dalam transaksi dan kunci bersama evoting-write. Status pada saat menulis hanya menerima draft, dibuka, atau ditutup (nilai legacy aktif/open/berlangsung/selesai/closed ditolak).',
        tags: ['Voting'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_rapat', 'judul_voting'],
                properties: [
                    new OA\Property(property: 'id_rapat', type: 'integer', example: 1),
                    new OA\Property(property: 'judul_voting', type: 'string', example: 'Persetujuan Pembukaan Program Studi Magister Terapan Rekayasa Komputer'),
                    new OA\Property(property: 'deskripsi', type: 'string', example: 'Pemungutan suara tertutup oleh anggota senat akademik.'),
                    new OA\Property(property: 'status', type: 'string', enum: ['draft', 'dibuka', 'ditutup'], example: 'draft'),
                    new OA\Property(
                        property: 'opsi',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['Setuju', 'Tidak Setuju', 'Abstain']
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Sesi voting berhasil dibuat'),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak (hanya admin/ketua senat/sekretaris)'),
            new OA\Response(response: 422, description: 'Validasi gagal: status bukan draft/dibuka/ditutup, waktu_selesai harus setelah waktu_mulai, atau payload tidak valid')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        return Voting::writeLocked(function () use ($request) {
        $data = $request->validate($this->rules());

        $votingData = Arr::except($data, ['opsi']);
        $votingData['status'] = $votingData['status'] ?? 'draft';

        // Hanya boleh ada 1 sesi voting yang dibuka dalam satu waktu.
        // Jika sesi baru dibuka, otomatis tutup semua sesi voting lain yang masih terbuka.
        if (in_array(strtolower($votingData['status']), ['dibuka', 'aktif', 'active', 'open'], true)) {
            Voting::whereIn('status', ['dibuka', 'aktif', 'active', 'open'])->update([
                'status' => 'ditutup',
                'waktu_selesai' => now(),
            ]);
        }

        $voting = Voting::create($votingData);

        $opsi = $request->input('opsi');
        if (! is_array($opsi) || empty($opsi)) {
            $opsi = ['Setuju', 'Tidak Setuju', 'Abstain'];
        }

        foreach ($opsi as $namaOpsi) {
            $voting->opsiVotings()->create(['nama_opsi' => $namaOpsi]);
        }

        return response()->json($voting->fresh($this->with), 201);
        });
    }

    #[OA\Post(
        path: '/voting/{id}/buka',
        summary: 'Buka Sesi Pemungutan Suara',
        description: 'Mengubah status voting menjadi dibuka sehingga anggota senat dapat memberikan suara.',
        tags: ['Voting'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Sesi voting berhasil dibuka'),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak (hanya admin/ketua senat/sekretaris)'),
            new OA\Response(response: 404, description: 'Sesi voting tidak ditemukan')
        ]
    )]
    public function buka($id): JsonResponse
    {
        return Voting::writeLocked(function () use ($id) {
        $voting = Voting::whereKey($id)->lockForUpdate()->firstOrFail();
        abort_if($voting->status === 'ditutup'
            || ($voting->waktu_selesai && $voting->waktu_selesai->lessThanOrEqualTo(now())), 422);
        if ($voting->status === 'dibuka') {
            return response()->json($voting->fresh($this->with));
        }
        $voting->assertEditable();

        // Tutup sesi voting lain yang masih terbuka agar tidak ada duplikasi sesi aktif
        Voting::whereIn('status', ['dibuka', 'aktif', 'active', 'open'])
            ->where('id_voting', '!=', $id)
            ->update([
                'status' => 'ditutup',
                'waktu_selesai' => now(),
            ]);

        $voting->update([
            'status' => 'dibuka',
            'waktu_mulai' => $voting->waktu_mulai ?? now(),
            'waktu_selesai' => $voting->waktu_selesai,
        ]);

        return response()->json($voting->fresh($this->with));
        });
    }

    #[OA\Post(
        path: '/voting/{id}/tutup',
        summary: 'Tutup Sesi Pemungutan Suara',
        description: 'Menutup sesi voting sidang senat sehingga tidak menerima suara baru.',
        tags: ['Voting'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Sesi voting berhasil ditutup'),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak (hanya admin/ketua senat/sekretaris)'),
            new OA\Response(response: 404, description: 'Sesi voting tidak ditemukan')
        ]
    )]
    public function tutup($id): JsonResponse
    {
        return Voting::writeLocked(function () use ($id) {
            $voting = Voting::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($voting->status !== 'ditutup') {
                $voting->update([
                    'status' => 'ditutup',
                    'waktu_selesai' => $voting->waktu_selesai ?? now(),
                ]);
            }

            return response()->json($voting->fresh($this->with));
        });
    }

    public function kuorumPayload(?Rapat $rapat): array
    {
        return $rapat ? $rapat->hitungKuorum() : [
            'total_invited' => 0, 'present_count' => 0, 'izin_count' => 0,
            'tidak_hadir_count' => 0, 'menunggu_count' => 0, 'kuorum_min_persen' => 67,
            'ambang_kuorum' => 0, 'present_percentage' => 0, 'is_kuorum' => false,
            'status_kuorum_label' => 'Belum Memenuhi Kuorum',
            'is_presensi_open' => false, 'is_kuorum_locked' => false,
        ];
    }

    #[OA\Get(
        path: '/voting/{id}/rekap',
        summary: 'Rekapitulasi Hasil E-Voting & Status Kuorum Sidang',
        description: 'Publik dan read-only (tetap 200 untuk sesi tertutup). Kuorum berasal dari satu sumber kebenaran Rapat::hitungKuorum(): blok kuorum berisi total_invited, present_count, izin_count, tidak_hadir_count, menunggu_count, kuorum_min_persen, ambang_kuorum, present_percentage, is_kuorum, status_kuorum_label beserta alias kompatibilitas total_undangan, total_hadir, persentase_kehadiran, dan kuorum_terpenuhi (selalu sama dengan is_kuorum). Hanya status kehadiran tepat "hadir" dihitung; izin/tidak_hadir/menunggu/tanpa status tidak menaikkan present_count. keputusan_sidang bernilai null kecuali kuorum terpenuhi DAN ada satu pemenang tunggal; keputusan_alasan berisi kuorum_belum_terpenuhi, tidak_ada_suara, seri, atau null. Seri diungkap lewat seri=true dan opsi_seri. Payload tidak pernah memuat baris ballot.',
        tags: ['Voting'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Hasil rekapitulasi, blok kuorum kanonik, dan keputusan sidang', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'kuorum', type: 'object', properties: [
                    new OA\Property(property: 'total_invited', type: 'integer'),
                    new OA\Property(property: 'present_count', type: 'integer'),
                    new OA\Property(property: 'izin_count', type: 'integer'),
                    new OA\Property(property: 'tidak_hadir_count', type: 'integer'),
                    new OA\Property(property: 'menunggu_count', type: 'integer'),
                    new OA\Property(property: 'kuorum_min_persen', type: 'integer'),
                    new OA\Property(property: 'ambang_kuorum', type: 'integer'),
                    new OA\Property(property: 'present_percentage', type: 'number'),
                    new OA\Property(property: 'is_kuorum', type: 'boolean'),
                    new OA\Property(property: 'status_kuorum_label', type: 'string'),
                    new OA\Property(property: 'total_undangan', type: 'integer', description: 'Alias total_invited'),
                    new OA\Property(property: 'total_hadir', type: 'integer', description: 'Alias present_count'),
                    new OA\Property(property: 'total_suara', type: 'integer'),
                    new OA\Property(property: 'persentase_kehadiran', type: 'number', description: 'Alias present_percentage'),
                    new OA\Property(property: 'kuorum_terpenuhi', type: 'boolean', description: 'Alias is_kuorum'),
                ]),
                new OA\Property(property: 'keputusan_sidang', type: 'string', nullable: true),
                new OA\Property(property: 'keputusan_alasan', type: 'string', nullable: true, enum: ['kuorum_belum_terpenuhi', 'tidak_ada_suara', 'seri', null]),
                new OA\Property(property: 'seri', type: 'boolean'),
                new OA\Property(property: 'opsi_seri', type: 'array', items: new OA\Items(type: 'string')),
            ])),
            new OA\Response(response: 404, description: 'Sesi voting tidak ditemukan')
        ]
    )]
    public function rekap($id): JsonResponse
    {
        $voting = Voting::with([
            'rapat.anggotaRapats',
            'rapat.jenisRapat',
            'opsiVotings' => fn ($q) => $q->withCount('suaraVotings'),
        ])->withCount('suaraVotings')->findOrFail($id);

        $rapat = $voting->rapat;
        $kuorum = $this->kuorumPayload($rapat);

        $totalSuara = (int) $voting->total_suara;
        $rekapitulasi = $voting->hasil_rekap;
        $teratas = collect($rekapitulasi)->sortByDesc('jumlah_suara')->first();
        $maxSuara = (int) collect($rekapitulasi)->max('jumlah_suara');
        $teratasSeri = $totalSuara > 0 && $maxSuara > 0
            ? collect($rekapitulasi)->filter(fn ($item) => (int) $item['jumlah_suara'] === $maxSuara)
                ->pluck('nama_opsi')->values()->all()
            : [];
        $seri = count($teratasSeri) > 1;
        $opsiSeri = $seri ? $teratasSeri : [];

        $keputusanSidang = (! $seri && $totalSuara > 0 && $kuorum['is_kuorum'] && $teratas)
            ? $teratas['nama_opsi'] : null;
        $keputusanAlasan = ! $kuorum['is_kuorum']
            ? 'kuorum_belum_terpenuhi'
            : ($totalSuara === 0 ? 'tidak_ada_suara' : ($seri ? 'seri' : null));

        return response()->json([
            'voting' => $voting,
            'rapat' => $rapat,
            'kuorum' => [
                'total_undangan' => $kuorum['total_invited'],
                'total_hadir' => $kuorum['present_count'],
                'total_suara' => $totalSuara,
                'persentase_kehadiran' => $kuorum['present_percentage'],
                'kuorum_terpenuhi' => $kuorum['is_kuorum'],
                'total_invited' => $kuorum['total_invited'],
                'present_count' => $kuorum['present_count'],
                'izin_count' => $kuorum['izin_count'],
                'tidak_hadir_count' => $kuorum['tidak_hadir_count'],
                'menunggu_count' => $kuorum['menunggu_count'],
                'kuorum_min_persen' => $kuorum['kuorum_min_persen'],
                'ambang_kuorum' => $kuorum['ambang_kuorum'],
                'present_percentage' => $kuorum['present_percentage'],
                'is_kuorum' => $kuorum['is_kuorum'],
                'status_kuorum_label' => $kuorum['status_kuorum_label'],
                'is_presensi_open' => $kuorum['is_presensi_open'],
                'is_kuorum_locked' => $kuorum['is_kuorum_locked'],
            ],
            'rekapitulasi' => $rekapitulasi,
            'total_suara' => $totalSuara,
            'opsi_terbanyak' => $seri ? null : $teratas,
            'keputusan_sidang' => $keputusanSidang,
            'keputusan_alasan' => $keputusanAlasan,
            'seri' => $seri,
            'opsi_seri' => $opsiSeri,
        ]);
    }

    #[OA\Get(
        path: '/voting/{id}',
        summary: 'Detail Sesi Voting',
        tags: ['Voting'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail data sesi voting'),
            new OA\Response(response: 404, description: 'Data tidak ditemukan')
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    #[OA\Put(
        path: '/voting/{id}',
        summary: 'Perbarui Sesi Voting',
        description: 'Memperbarui materi sesi yang masih draft kosong dan belum dibuka. Field status DITOLAK seluruhnya lewat PUT (termasuk nilai null) - gunakan POST /voting/{id}/buka atau POST /voting/{id}/tutup. Interval waktu divalidasi terhadap nilai tersimpan: waktu_selesai harus setelah waktu_mulai.',
        tags: ['Voting'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'judul_voting', type: 'string'),
                    new OA\Property(property: 'deskripsi', type: 'string'),
                    new OA\Property(property: 'id_rapat', type: 'integer'),
                    new OA\Property(property: 'waktu_mulai', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'waktu_selesai', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'opsi', type: 'array', items: new OA\Items(type: 'string'))
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Data voting berhasil diperbarui'),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak (hanya admin/ketua senat/sekretaris)'),
            new OA\Response(response: 404, description: 'Data tidak ditemukan'),
            new OA\Response(response: 422, description: 'Field status dikirim lewat PUT, sesi sudah pernah dibuka/berisi suara, atau interval waktu tidak valid')
        ]
    )]
    public function update(Request $request, $id)
    {
        return Voting::writeLocked(function () use ($request, $id) {
            $voting = Voting::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if(array_key_exists('status', $request->all()), 422, 'Gunakan buka/tutup.');
            $voting->assertEditable();
            $data = $request->validate($this->rules((int) $id));
            $start = $data['waktu_mulai'] ?? $voting->waktu_mulai;
            $end = $data['waktu_selesai'] ?? $voting->waktu_selesai;
            abort_if($start && $end && \Illuminate\Support\Carbon::parse($end)->lessThanOrEqualTo($start), 422);
            $voting->update(Arr::except($data, ['opsi']));
            if (array_key_exists('opsi', $data)) {
                $voting->opsiVotings()->delete();
                foreach ($data['opsi'] ?? [] as $name) {
                    $voting->opsiVotings()->create(['nama_opsi' => $name]);
                }
            }

            return $voting->fresh($this->with);
        });
    }

    #[OA\Delete(
        path: '/voting/{id}',
        summary: 'Hapus Sesi Voting (hanya draft kosong)',
        description: 'Hanya sesi draft yang belum pernah dibuka dan belum memiliki suara yang boleh dihapus. Sesi tertutup atau bersuara dipertahankan permanen; permintaan hapus ditolak 422 tanpa mengubah ballot.',
        tags: ['Voting'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Draft kosong berhasil dihapus'),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak (hanya admin/ketua senat/sekretaris)'),
            new OA\Response(response: 404, description: 'Data tidak ditemukan'),
            new OA\Response(response: 422, description: 'Sesi tertutup, sudah pernah dibuka, atau memiliki suara; retensi wajib')
        ]
    )]
    public function destroy($id)
    {
        return Voting::writeLocked(function () use ($id) {
            $voting = Voting::whereKey($id)->lockForUpdate()->firstOrFail();
            $voting->assertEditable();
            $voting->opsiVotings()->delete();
            $voting->delete();

            return response()->noContent();
        });
    }
}
