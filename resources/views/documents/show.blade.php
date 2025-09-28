<h1>Detail Dokumen: {{ $document->title }}</h1>

<p><strong>Description:</strong> {{ $document->description }}</p>
<p><strong>CID:</strong> {{ $document->cid }}</p>
<p><strong>Local Status:</strong> {{ $localExists ? 'File Ada' : 'File Hilang' }}</p>

@if ($localExists)
    @php
        $ext = strtolower(pathinfo($document->filename, PATHINFO_EXTENSION));
    @endphp

    @if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif']))
        <img src="{{ $localUrl }}" width="200">
    @elseif ($ext === 'pdf')
        <a href="{{ $localUrl }}" target="_blank">Lihat PDF</a>
    @else
        <a href="{{ $localUrl }}" target="_blank">Lihat File</a>
    @endif
@endif

<a href="{{ route('documents.index') }}">Kembali</a>
