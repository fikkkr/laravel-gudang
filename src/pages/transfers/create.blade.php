@extends('layouts.app')

@section('content')
<section class="crud-shell">
    <header class="crud-form-heading">
        <a href="{{ route('transactions.index') }}" class="back-link">&larr; Kembali ke transaksi</a>
        <p class="eyebrow">Persediaan</p>
        <h1 class="crud-title">Transfer Stok</h1>
        <p class="crud-description">Pindahkan barang dari satu gudang ke gudang lain.</p>
    </header>

    <form method="POST" action="{{ route('transfers.store') }}" class="barang-form">
        @csrf
        @if ($errors->any())
            <div class="form-error-summary" role="alert">
                <strong>Periksa kembali isian berikut:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="barang-form-grid">
            <label class="crud-field">
                Gudang Asal
                <select name="warehouse_id" id="source-warehouse" required class="crud-input">
                    <option value="">Pilih gudang asal</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>
                            {{ $warehouse->code }} — {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>
                @error('warehouse_id')<span class="form-error">{{ $message }}</span>@enderror
            </label>
            <label class="crud-field">
                Gudang Tujuan
                <select name="destination_warehouse_id" id="destination-warehouse" required class="crud-input">
                    <option value="">Pilih gudang tujuan</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('destination_warehouse_id') == $warehouse->id)>
                            {{ $warehouse->code }} — {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>
                @error('destination_warehouse_id')<span class="form-error">{{ $message }}</span>@enderror
            </label>
            <label class="crud-field">
                Tanggal Transaksi
                <input type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" required class="crud-input">
                @error('transaction_date')<span class="form-error">{{ $message }}</span>@enderror
            </label>
            <label class="crud-field">
                Catatan
                <textarea name="notes" rows="3" class="crud-input">{{ old('notes') }}</textarea>
                @error('notes')<span class="form-error">{{ $message }}</span>@enderror
            </label>
        </div>

        @error('items')<p class="form-error">{{ $message }}</p>@enderror
        <div class="table-wrap">
            <table class="barang-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="transfer-items">
                    @foreach (old('items', [['product_id' => '', 'quantity' => 1]]) as $index => $item)
                        <tr class="transfer-item-row">
                            <td>
                                <select name="items[{{ $index }}][product_id]" required class="crud-input">
                                    <option value="">Pilih produk</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" @selected(($item['product_id'] ?? '') == $product->id)>
                                            {{ $product->sku }} — {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error("items.$index.product_id")<span class="form-error">{{ $message }}</span>@enderror
                            </td>
                            <td>
                                <input type="number" name="items[{{ $index }}][quantity]" value="{{ array_key_exists('quantity', $item) ? $item['quantity'] : 1 }}" min="1" max="2147483647" step="1" required class="crud-input item-quantity" aria-describedby="items-{{ $index }}-quantity-error">
                                @error("items.$index.quantity")
                                    <span id="items-{{ $index }}-quantity-error" class="form-error" data-quantity-error role="alert">{{ $message }}</span>
                                @else
                                    <span id="items-{{ $index }}-quantity-error" class="form-error" data-quantity-error hidden></span>
                                @enderror
                            </td>
                            <td><output class="item-subtotal">—</output></td>
                            <td><button type="button" class="button button-secondary button-small remove-item">Hapus</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="crud-form-actions">
            <button type="button" id="add-item" class="button button-secondary">+ Tambah Item</button>
            <button type="submit" class="button button-primary">Simpan Transfer</button>
        </div>
    </form>
</section>

<script>
(() => {
    const source = document.getElementById('source-warehouse');
    const destination = document.getElementById('destination-warehouse');
    const tbody = document.getElementById('transfer-items');

    const updateWarehouses = () => {
        [...destination.options].forEach((option) => {
            option.disabled = option.value !== '' && option.value === source.value;
        });
        if (destination.value && destination.value === source.value) {
            destination.value = '';
        }
    };

    const renumberRows = () => {
        [...tbody.querySelectorAll('.transfer-item-row')].forEach((row, index) => {
            row.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`);
            });
            row.querySelector('.item-quantity').setAttribute('aria-describedby', `items-${index}-quantity-error`);
            row.querySelector('[data-quantity-error]').id = `items-${index}-quantity-error`;
        });
    };

    source.addEventListener('change', updateWarehouses);
    document.getElementById('add-item').addEventListener('click', () => {
        const row = tbody.querySelector('.transfer-item-row').cloneNode(true);
        row.querySelector('select').value = '';
        row.querySelector('.item-quantity').value = 1;
        tbody.appendChild(row);
        renumberRows();
    });

    tbody.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-item');
        if (!button || tbody.querySelectorAll('.transfer-item-row').length === 1) return;
        button.closest('.transfer-item-row').remove();
        renumberRows();
    });

    updateWarehouses();
})();
</script>
@endsection
