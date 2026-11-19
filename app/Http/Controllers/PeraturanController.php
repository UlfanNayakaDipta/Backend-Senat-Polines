<?php

namespace App\Http\Controllers;

use App\Models\Peraturan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class PeraturanController extends CrudController
{
    protected string $model = Peraturan::class;
    protected array $with = ['periodeSenat'];

    protected function rules(?int $id = null): array
    {
        return [
            'id_periode_senat' => ['required', 'exists:periode_senats,id_periode_senat'],
            'nomor_sk' => ['required', 'string', 'max:255'],
            'judul' => ['required', 'string', 'max:255'],
            'kategori' => ['required', 'string', 'max:255'],
            'tahap_pembentukan' => ['required', 'string', 'max:255'],
            'file_dokumen' => ['nullable', 'string', 'max:500'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,odt,rtf,xls,xlsx,ppt,pptx', 'max:10240'],
            'tanggal_terbit' => ['required', 'date'],
        ];
    }

    #[OA\Get(
        path: '/peraturan',
        summary: 'Daftar Peraturan & Dokumen Senat',
        tags: ['Peraturan & Dokumen'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar peraturan terpaginasi')
        ]
    )]
    public function index()
    {
        $request = request();
        $query = $this->model::with($this->with);

        if ($request->filled('kategori') && $request->query('kategori') !== 'all') {
            $query->where('kategori', $request->query('kategori'));
        }

        if ($request->filled('tahap') && $request->query('tahap') !== 'all') {
            $query->where('tahap_pembentukan', $request->query('tahap'));
        }

        if ($request->filled('periode')) {
            $query->where('id_periode_senat', $request->query('periode'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('nomor_sk', 'like', "%{$search}%")
                  ->orWhere('kategori', 'like', "%{$search}%")
                  ->orWhere('tahap_pembentukan', 'like', "%{$search}%");
            });
        }

        $query->orderByDesc('tanggal_terbit')->orderByDesc('id_peraturan');

        if ($request->boolean('all')) {
            return response()->json($query->get());
        }

        return $query->paginate($request->integer('per_page', 15));
    }

    #[OA\Post(
        path: '/peraturan',
        summary: 'Tambah Dokumen Peraturan Baru',
        tags: ['Peraturan & Dokumen'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_periode_senat', 'nomor_sk', 'judul', 'kategori', 'tahap_pembentukan', 'tanggal_terbit'],
                properties: [
                    new OA\Property(property: 'id_periode_senat', type: 'integer', example: 1),
                    new OA\Property(property: 'nomor_sk', type: 'string', example: 'SK/SENAT-POLINES/2026/012'),
                    new OA\Property(property: 'judul', type: 'string', example: 'Peraturan Tata Tertib Sidang dan Pengambilan Keputusan Senat'),
                    new OA\Property(property: 'kategori', type: 'string', example: 'Tata Tertib'),
                    new OA\Property(property: 'tahap_pembentukan', type: 'string', example: 'Disahkan'),
                    new OA\Property(property: 'file_dokumen', type: 'string', nullable: true),
                    new OA\Property(property: 'tanggal_terbit', type: 'string', format: 'date', example: '2026-05-10')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Peraturan berhasil disimpan'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        unset($data['file']);

        if ($request->hasFile('file')) {
            $data['file_dokumen'] = $request->file('file')->store('peraturan', 'public');
        }

        $item = $this->model::create($data);

        return response()->json($item->load($this->with), 201);
    }

    #[OA\Get(
        path: '/peraturan/{id}',
        summary: 'Detail Dokumen Peraturan',
        tags: ['Peraturan & Dokumen'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail peraturan'),
            new OA\Response(response: 404, description: 'Peraturan tidak ditemukan')
        ]
    )]
    public function show($id)
    {
        return parent::show($id);
    }

    public function download($id)
    {
        $item = $this->model::findOrFail($id);
        $filePath = $item->file_dokumen;

        if (! $filePath) {
            abort(404, 'Berkas dokumen belum tersedia.');
        }

        if (Str::startsWith($filePath, ['http://', 'https://'])) {
            return redirect()->away($filePath);
        }

        if (! Storage::disk('public')->exists($filePath)) {
            abort(404, 'Berkas dokumen tidak ditemukan di penyimpanan.');
        }

        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $baseName = Str::slug($item->nomor_sk ?: $item->judul ?: 'peraturan-' . $item->id_peraturan);
        $downloadName = $ext ? "{$baseName}.{$ext}" : $baseName;

        return Storage::disk('public')->download($filePath, $downloadName);
    }

    #[OA\Put(
        path: '/peraturan/{id}',
        summary: 'Perbarui Dokumen Peraturan',
        tags: ['Peraturan & Dokumen'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'judul', type: 'string'),
                    new OA\Property(property: 'nomor_sk', type: 'string'),
                    new OA\Property(property: 'tahap_pembentukan', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Peraturan berhasil diperbarui')
        ]
    )]
    public function update(Request $request, $id)
    {
        $item = $this->model::findOrFail($id);
        $rules = array_map(fn ($r) => array_merge(['sometimes'], $r), $this->rules((int) $id));
        $data = $request->validate($rules);
        unset($data['file']);

        if ($request->hasFile('file')) {
            $this->deleteLocalFile($item->file_dokumen);
            $data['file_dokumen'] = $request->file('file')->store('peraturan', 'public');
        }

        $item->update($data);

        return $item->load($this->with);
    }

    #[OA\Delete(
        path: '/peraturan/{id}',
        summary: 'Hapus Peraturan',
        tags: ['Peraturan & Dokumen'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Peraturan berhasil dihapus')
        ]
    )]
    public function destroy($id)
    {
        $item = $this->model::findOrFail($id);
        $this->deleteLocalFile($item->file_dokumen);
        $item->delete();

        return response()->noContent();
    }

    private function deleteLocalFile(?string $filePath): void
    {
        if (! $filePath || Str::startsWith($filePath, ['http://', 'https://'])) {
            return;
        }

        if (Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }
    }
}
