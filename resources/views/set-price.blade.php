<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="shopify-api-key" content="{{ env('SHOPIFY_API_KEY') }}">
    <meta name="shopify-host" content="{{ request()->query('host') }}">
    <title>Price Set</title>
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    <link rel="stylesheet" href="{{ asset('css/set-price.css') }}">
</head>
<body>
<ui-nav-menu>
    <a href="{{ route('price.index', request()->query()) }}" rel="home">Set Price</a>
</ui-nav-menu>

<main class="page">
    <div class="card">
            <h1 style="margin-top:0;">Price Set</h1>

            @if(session('success'))
                <div class="success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="error">
                    <strong>Please fix these errors:</strong>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('price.store') }}">
                @csrf

                <div class="card">
                    <h3>Metal Price</h3>
                    <div class="grid">
                        <div class="form-group">
                            <label>Gold 10kt</label>
                            <input type="number" step="0.01" name="gold_10kt" value="{{ old('gold_10kt', $priceSetting->gold_10kt ?? 0) }}">
                        </div>
                        <div class="form-group">
                            <label>Gold 14kt</label>
                            <input type="number" step="0.01" name="gold_14kt" value="{{ old('gold_14kt', $priceSetting->gold_14kt ?? 0) }}">
                        </div>
                        <div class="form-group">
                            <label>Gold 18kt</label>
                            <input type="number" step="0.01" name="gold_18kt" value="{{ old('gold_18kt', $priceSetting->gold_18kt ?? 0) }}">
                        </div>
                        <div class="form-group">
                            <label>Gold 22kt</label>
                            <input type="number" step="0.01" name="gold_22kt" value="{{ old('gold_22kt', $priceSetting->gold_22kt ?? 0) }}">
                        </div>
                        <div class="form-group">
                            <label>Silver Price</label>
                            <input type="number" step="0.01" name="silver_price" value="{{ old('silver_price', $priceSetting->silver_price ?? 0) }}">
                        </div>
                        <div class="form-group">
                            <label>Platinum Price</label>
                            <input type="number" step="0.01" name="platinum_price" value="{{ old('platinum_price', $priceSetting->platinum_price ?? 0) }}">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3>Diamond Price</h3>
                    <table id="diamond-table">
                        <thead>
                            <tr>
                                <th>Quality</th>
                                <th>Color</th>
                                <th>Min Range (ct)</th>
                                <th>Max Range (ct)</th>
                                <th>Price</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="diamond-body">
                        @php
                            $diamondRows = old('diamonds', $diamonds->toArray());
                            if (count($diamondRows) === 0) {
                                $diamondRows = [['quality' => '', 'color' => '', 'min_ct' => '', 'max_ct' => '', 'price' => '']];
                            }
                        @endphp
                        @foreach($diamondRows as $index => $diamond)
                            <tr>
                                <td><input type="text" name="diamonds[{{ $index }}][quality]" value="{{ $diamond['quality'] ?? '' }}"></td>
                                <td><input type="text" name="diamonds[{{ $index }}][color]" value="{{ $diamond['color'] ?? '' }}"></td>
                                <td><input type="number" step="0.01" name="diamonds[{{ $index }}][min_ct]" value="{{ $diamond['min_ct'] ?? '' }}"></td>
                                <td><input type="number" step="0.01" name="diamonds[{{ $index }}][max_ct]" value="{{ $diamond['max_ct'] ?? '' }}"></td>
                                <td><input type="number" step="0.01" name="diamonds[{{ $index }}][price]" value="{{ $diamond['price'] ?? '' }}"></td>
                                <td><button type="button" class="btn btn-danger remove-row">Remove</button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <div class="actions">
                        <button type="button" id="add-row" class="btn btn-secondary">+ Add Diamond Row</button>
                    </div>
                </div>

                <div class="card">
                    <h3>Tax</h3>
                    <div class="form-group" style="max-width: 300px;">
                        <label>Tax Percent (%)</label>
                        <input type="number" step="0.01" name="tax_percent" value="{{ old('tax_percent', $priceSetting->tax_percent ?? 0) }}">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save Price Set</button>
            </form>
    </div>
</main>

<script src="{{ asset('js/set-price.js') }}"></script>
</body>
</html>
