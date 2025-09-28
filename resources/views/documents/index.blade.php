<h1>Data Dokumen dari Database Lokal</h1>

@if (session('error'))
    <div style="color:red; font-weight:bold;">
        ⚠️ Error: {{ session('error') }}
    </div>
@endif

@if (session('success'))
    <div style="color:green; font-weight:bold;">
        ✅ {{ session('success') }}
    </div>
@endif

<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <th>Title</th>
            <th>Description</th>
            <th>Preview / File</th>
            <th>CID</th>
            <th>Local Hash</th>
            <th>Status / Action</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($docs as $doc)
            <tr>
                <td>{{ $doc->title }}</td>
                <td>{{ $doc->description }}</td>
                <td>{!! $doc->preview !!}</td>
                <td>{{ $doc->cid }}</td>
                <td>{{ $doc->local_hash }}</td>
                <td>
                    @if ($doc->status_changed)
                        <strong style="color:red;">{{ $doc->status }}</strong>
                        <form action="{{ route('documents.restore', $doc->id) }}" method="POST" style="display:inline;">
                            @csrf
                            <button type="submit">Restore dari Pinata</button>
                        </form>
                    @else
                        {{ $doc->status }}
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<hr>

<h1>Data Dokumen dari Pinata IPFS</h1>

@if (!empty($changedFiles))
    <div style="color:red; font-weight:bold;">
        ⚠️ File berikut berubah: {{ implode(', ', $changedFiles) }}
    </div>
@endif

<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <th>Title</th>
            <th>Description</th>
            <th>Preview / File</th>
            <th>CID</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($pinataFiles as $pin)
            <tr @if ($pin['changed']) style="background-color: #ffe6e6;" @endif>
                <td>{{ $pin['title'] }}</td>
                <td>{{ $pin['description'] }}</td>
                <td>{!! $pin['preview'] !!}</td>
                <td>{{ $pin['cid'] }}</td>
                <td>
                    @if ($pin['changed'])
                        <strong style="color:red;">Berubah</strong>
                    @else
                        Aman
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<a href="{{ route('documents.create') }}">Tambah Data</a>
