@extends('layouts.app')

@section('content')
<section class="crud-shell">
    <header class="crud-form-heading">
        <a href="{{ route('transactions.index') }}" class="back-link">&larr; Kembali ke transaksi</a>
        <p class="eyebrow">Persediaan</p>
        <h1 class="crud-title">Barang Masuk</h1>
        <p class="crud-description">Catat barang yang masuk ke gudang.</p>
    </header>

    <form method="POST" action="{{ route('inbounds.store') }}" class="barang-form">
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
                Gudang
                <select name="warehouse_id" required class="crud-input">
                    <option value="">Pilih gudang</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>
                            {{ $warehouse->code }} — {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>
                @error('warehouse_id')<span class="form-error">{{ $message }}</span>@enderror
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
        <p class="crud-description">Subtotal dan total di formulir hanya perkiraan; nilai transaksi dihitung ulang oleh server.</p>

        @error('items')<p class="form-error">{{ $message }}</p>@enderror
        <div class="table-wrap">
            <table class="barang-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Qty</th>
                        <th>Harga</th>
                        <th>Subtotal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="inbound-items">
                    @foreach (old('items', [['product_id' => '', 'quantity' => 1, 'unit_price' => '']]) as $index => $item)
                        <tr class="inbound-item-row">
                            <td>
                                <select name="items[{{ $index }}][product_id]" required class="crud-input">
                                    <option value="">Pilih produk</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" @selected(($item['product_id'] ?? '') == $product->id)>
                                            {{ $product->sku }} — {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" name="items[{{ $index }}][quantity]" value="{{ array_key_exists('quantity', $item) ? $item['quantity'] : 1 }}" min="1" max="2147483647" step="1" required class="crud-input item-quantity" aria-describedby="items-{{ $index }}-quantity-error">
                                @error("items.$index.quantity")
                                    <span id="items-{{ $index }}-quantity-error" class="form-error" data-quantity-error role="alert">{{ $message }}</span>
                                @else
                                    <span id="items-{{ $index }}-quantity-error" class="form-error" data-quantity-error hidden></span>
                                @enderror
                            </td>
                            <td>
                                <input type="number" name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] ?? '' }}" min="0" step="0.01" required class="crud-input item-price">
                            </td>
                            <td><output class="item-subtotal">0.00</output></td>
                            <td><button type="button" class="button button-secondary button-small remove-item">Hapus</button></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3">Total sementara</th>
                        <th><output id="inbound-total">0,00</output></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="crud-form-actions">
            <button type="button" id="add-item" class="button button-secondary">+ Tambah Item</button>
            <button type="submit" class="button button-primary">Simpan</button>
        </div>
    </form>
</section>

<script>
(() => {
    const tbody = document.getElementById('inbound-items');
    const updateSubtotal = (row) => {
        const quantity = Number(row.querySelector('.item-quantity').value) || 0;
        const price = Number(row.querySelector('.item-price').value) || 0;
        row.querySelector('.item-subtotal').textContent = (quantity * price).toFixed(2);
    };
    const updateTotal = () => {
        const total = [...tbody.querySelectorAll('.item-subtotal')]
            .reduce((sum, subtotal) => sum + (Number(subtotal.textContent) || 0), 0);
        document.getElementById('inbound-total').textContent = total.toFixed(2);
    };

    const renumberRows = () => {
        [...tbody.querySelectorAll('.inbound-item-row')].forEach((row, index) => {
            row.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`);
            });
            row.querySelector('.item-quantity').setAttribute('aria-describedby', `items-${index}-quantity-error`);
            row.querySelector('[data-quantity-error]').id = `items-${index}-quantity-error`;
            updateSubtotal(row);
        });
        updateTotal();
    };

    document.getElementById('add-item').addEventListener('click', () => {
        const row = tbody.querySelector('.inbound-item-row').cloneNode(true);
        row.querySelector('select').value = '';
        row.querySelector('.item-quantity').value = 1;
        row.querySelector('.item-price').value = '';
        tbody.appendChild(row);
        renumberRows();
    });

    tbody.addEventListener('input', (event) => {
        const row = event.target.closest('.inbound-item-row');
        if (row) {
            updateSubtotal(row);
            updateTotal();
        }
    });

    tbody.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-item');
        if (!button) return;
        if (tbody.querySelectorAll('.inbound-item-row').length === 1) return;
        button.closest('.inbound-item-row').remove();
        renumberRows();
    });

    tbody.querySelectorAll('.inbound-item-row').forEach(updateSubtotal);
    updateTotal();
})();
</script>
@endsection
