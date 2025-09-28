<h1>Upload dokumen ke IPFS & Simpan Lokal</h1>

{{-- Tampilkan error validasi --}}
@if ($errors->any())
    <ul style="color:red;">
        @foreach ($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
@endif
@if (env('APP_DEBUG'))
    <div style="background: #f0f0f0; padding: 10px; margin: 10px 0;">
        <small>Debug: {{ session('debug_path') }}</small>
    </div>
@endif

{{-- Tampilkan error flash dari Controller --}}
@if (session('error'))
    <div style="color:red; font-weight:bold; margin-bottom:10px;">
        {{ session('error') }}
    </div>
@endif

{{-- Tampilkan success message --}}
@if (session('success'))
    <div style="color:green; font-weight:bold; margin-bottom:10px;">
        {{ session('success') }}
    </div>
@endif

<form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div>
        <label>Title</label>
        <input type="text" name="title" value="{{ old('title') }}" required>
    </div>
    <div>
        <label>Description</label>
        <textarea name="description">{{ old('description') }}</textarea>
    </div>
    <div>
        <label>File (PDF, gambar, dsb.)</label>
        <input type="file" name="file" required>
        <small>File juga akan disimpan di folder <code>public/storage/files</code></small>
    </div>
    <button type="submit">Upload ke IPFS & Simpan Lokal</button>
</form>
