<div class="container">
    <h2>Ledger Imports</h2>

    <form action="{{ route('ledger.import.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="mb-3">
            <label>PDF File</label>
            <input type="file" name="pdf" class="form-control">
        </div>

        <button type="submit" class="btn btn-primary">
            Upload
        </button>
    </form>

    <hr>

    {{-- List imports here --}}
</div>
