<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
    public function index()
    {
        $docs = Document::all();

        $changedFiles = [];
        $pinataFiles = [];

        // Cek file lokal
        foreach ($docs as $doc) {
            $localPath = Storage::disk('public')->path('files/' . $doc->filename);
            $doc->status_changed = false;

            if (!file_exists($localPath)) {
                $doc->status = 'File hilang';
                $doc->status_changed = true;
                $changedFiles[] = $doc->title;
            } else {
                $hash = hash_file('sha256', $localPath);
                if ($hash !== $doc->local_hash) {
                    $doc->status = 'File berubah';
                    $doc->status_changed = true;
                    $changedFiles[] = $doc->title;
                } else {
                    $doc->status = 'Aman';
                }
            }

            // Preview file
            $ext = strtolower(pathinfo($doc->filename, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $doc->preview = '<img src="' . asset('storage/files/' . $doc->filename) . '" width="120">';
            } elseif ($ext === 'pdf') {
                $doc->preview = file_exists($localPath)
                    ? '<a href="' . asset('storage/files/' . $doc->filename) . '" target="_blank">Lihat PDF</a>'
                    : '<span style="color:red;">File hilang</span>';
            } else {
                $doc->preview = file_exists($localPath)
                    ? '<a href="' . asset('storage/files/' . $doc->filename) . '" target="_blank">Lihat File</a>'
                    : '<span style="color:red;">File hilang</span>';
            }
        }

        // Ambil data Pinata
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('PINATA_JWT'),
            ])->get('https://api.pinata.cloud/data/pinList', [
                'status' => 'pinned',
                'pageLimit' => 20
            ]);

            if ($response->successful()) {
                foreach ($response->json()['rows'] as $row) {
                    $cid = $row['ipfs_pin_hash'];
                    $title = $row['metadata']['keyvalues']['title'] ?? $row['metadata']['name'] ?? 'Tanpa Judul';
                    $description = $row['metadata']['keyvalues']['description'] ?? '';
                    $gatewayUrl = "https://gateway.pinata.cloud/ipfs/" . $cid;

                    $dbDoc = Document::where('title', $title)->first();
                    $statusChange = ($dbDoc && $dbDoc->cid !== $cid);

                    $pinataFiles[] = [
                        'cid' => $cid,
                        'title' => $title,
                        'description' => $description,
                        'gateway_url' => $gatewayUrl,
                        'changed' => $statusChange,
                        'preview' => '<a href="' . $gatewayUrl . '" target="_blank">Lihat File</a>'
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Fetch Pinata gagal: ' . $e->getMessage());
        }

        return view('documents.index', compact('docs', 'pinataFiles', 'changedFiles'));
    }
    public function create()
    {
        return view('documents.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'file' => 'required|file'
        ]);

        try {
            $file = $request->file('file');

            // Generate filename yang aman
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());

            // **METHOD 1: Simpan file menggunakan Storage facade (lebih recommended)**
            $storagePath = 'files/' . $filename;
            Storage::disk('public')->put($storagePath, file_get_contents($file->getRealPath()));

            // Dapatkan path lengkap yang benar
            $localPath = Storage::disk('public')->path($storagePath);

            // **METHOD 2: Alternatif - simpan file langsung**
            // $file->move(public_path('storage/files'), $filename);
            // $localPath = public_path('storage/files/' . $filename);

            // Debug: log path untuk troubleshooting
            Log::info('File saved at: ' . $localPath);
            Log::info('File exists: ' . (file_exists($localPath) ? 'YES' : 'NO'));

            // Pastikan file benar-benar ada
            if (!file_exists($localPath)) {
                // Coba list files di directory untuk debugging
                $filesInDir = Storage::disk('public')->files('files');
                Log::error('Files in directory: ' . implode(', ', $filesInDir));

                return back()->with('error', 'File lokal gagal disimpan. Path: ' . $localPath);
            }

            // Hitung hash file
            $localHash = hash_file('sha256', $localPath);

            // Upload ke Pinata
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('PINATA_JWT'),
            ])->attach('file', file_get_contents($localPath), $filename)
                ->post('https://api.pinata.cloud/pinning/pinFileToIPFS', [
                    'pinataMetadata' => json_encode([
                        'name' => $request->title,
                        'keyvalues' => [
                            'title' => $request->title,
                            'description' => $request->description ?? '',
                        ]
                    ])
                ]);

            if (!$response->successful()) {
                // Hapus file lokal jika upload ke Pinata gagal
                Storage::disk('public')->delete($storagePath);
                return back()->with('error', 'Upload ke Pinata gagal: ' . $response->body());
            }

            $cid = $response->json()['IpfsHash'] ?? null;

            // Simpan ke database
            Document::updateOrCreate(
                ['title' => $request->title],
                [
                    'description' => $request->description,
                    'filename' => $filename,
                    'cid' => $cid,
                    'local_hash' => $localHash,
                ]
            );

            return redirect()->route('documents.index')->with('success', 'Dokumen berhasil diupload');
        } catch (\Exception $e) {
            Log::error('Store Document Error: ' . $e->getMessage());
            return back()->with('error', 'Terjadi error: ' . $e->getMessage());
        }
    }



    public function show(Document $document)
    {
        $localPath = Storage::disk('public')->path('files/' . $document->filename);
        $localExists = file_exists($localPath);

        return view('documents.show', [
            'document' => $document,
            'localExists' => $localExists,
            'localUrl' => $localExists ? asset('storage/files/' . $document->filename) : null
        ]);
    }



    public function restoreFromPinata(Document $document)
    {
        $localPath = Storage::disk('public')->path('files/' . $document->filename);

        if (!$document->cid) {
            return back()->with('error', 'Dokumen tidak memiliki CID di Pinata.');
        }

        try {
            // Ambil file dari Pinata gateway
            $pinataUrl = "https://gateway.pinata.cloud/ipfs/" . $document->cid;
            $fileContent = file_get_contents($pinataUrl);

            if (!$fileContent) {
                return back()->with('error', 'Gagal mengambil file dari Pinata.');
            }

            // Simpan/overwrite ke folder lokal
            Storage::disk('public')->put('files/' . $document->filename, $fileContent);

            // Update hash lokal
            $document->local_hash = hash('sha256', $fileContent);
            $document->save();

            return back()->with('success', 'File berhasil direstore dari Pinata.');
        } catch (\Exception $e) {
            Log::error('Restore from Pinata failed: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat restore: ' . $e->getMessage());
        }
    }
}
