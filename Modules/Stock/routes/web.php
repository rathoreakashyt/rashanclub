<?php

use Illuminate\Support\Facades\Route;
use Modules\Stock\Http\Controllers\StockController;
use Modules\Stock\Http\Controllers\ItemCategoryController;
use Modules\Stock\Http\Controllers\BrandController;
use Modules\Stock\Http\Controllers\RackController;
use Modules\Stock\Http\Controllers\UnitController;
use Modules\Stock\Http\Controllers\VariationAttributeController;
use Modules\Stock\Http\Controllers\ItemController;
use Modules\Stock\Http\Controllers\DamageController;
use Modules\Stock\Http\Controllers\TransferController;
use Modules\Stock\Http\Controllers\FixedAssetItemController;
use Modules\Stock\Http\Controllers\FixedAssetStockInController;
use Modules\Stock\Http\Controllers\FixedAssetStockOutController;
use Modules\Stock\Http\Controllers\PriceListController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Stock Routes
    Route::controller(StockController::class)->group(function () {
        Route::get('stock', 'index')->name('stock.index')->middleware(['permission:stock-stock', 'outlet_set']);
        Route::post('stock/stock', 'stock')->name('stock.stock')->middleware(['permission:stock-stock', 'outlet_set']);
        Route::get('stock/low-stock', 'lowStock')->name('stock.low-stock')->middleware(['permission:stock-low_stock', 'outlet_set']);
        Route::post('stock/getLowStockAjaxData', 'getLowStockAjaxData')->name('stock.getLowStockAjaxData');
        Route::post('stock/getStockSegmentationOfItem', 'getStockSegmentationOfItem')->name('stock.getStockSegmentationOfItem');
    });
    

    // Item Routes
    Route::controller(ItemController::class)->group(function () {
        Route::get('item', 'index')->name('item.index')->middleware('permission:item-list');
        Route::get('item/create', 'create')->name('item.create')->middleware('permission:item-create');
        Route::post('item', 'store')->name('item.store')->middleware('permission:item-create');
        Route::get('item/{item}/show', 'show')->name('item.show')->middleware('permission:item-show');
        Route::get('item/{item}/edit', 'edit')->name('item.edit')->middleware('permission:item-edit');
        Route::put('item/{item}', 'update')->name('item.update')->middleware('permission:item-edit');
        Route::delete('item/{item}', 'destroy')->name('item.destroy')->middleware('permission:item-destroy');

        Route::get('bulk-item-import', 'bulkItemImport')->name('bulk-item-import')->middleware('permission:item-import');
        Route::get('opening-stock-import', 'openingStockImport')->name('opening-stock-import')->middleware(['permission:item-import', 'outlet_set']);
        Route::get('opening-stock-export-general', 'exportOpeningStockGeneral')->name('opening-stock-export-general')->middleware('permission:item-import');
        Route::get('opening-stock-export-imei-serial', 'exportOpeningStockImeiSerial')->name('opening-stock-export-imei-serial')->middleware('permission:item-import');
        Route::get('opening-stock-export-medicine', 'exportOpeningStockMedicine')->name('opening-stock-export-medicine')->middleware('permission:item-import');
        Route::post('opening-stock-import-general', 'storeOpeningStockGeneral')->name('opening-stock-import-general')->middleware(['permission:item-import', 'outlet_set']);
        Route::post('opening-stock-import-imei-serial', 'storeOpeningStockImeiSerial')->name('opening-stock-import-imei-serial')->middleware(['permission:item-import', 'outlet_set']);
        Route::post('opening-stock-import-medicine', 'storeOpeningStockMedicine')->name('opening-stock-import-medicine')->middleware(['permission:item-import', 'outlet_set']);
        Route::get('bulk-item-import-sample', 'downloadItemImportSample')->name('bulk-item-import-sample')->middleware('permission:item-import');
        Route::post('bulk-item-import-store', 'bulkItemImportStore')->name('bulk-item-import-store')->middleware('permission:item-import');
        Route::get('get-general-products', 'getGeneralProducts')->name('get-general-products');
        Route::get('item/{item}/variations', 'getVariations')->name('item.variations');
        Route::get('bulk-item-update', 'bulkItemUpdate')->name('bulk-item-update')->middleware('permission:item-list');
        Route::post('item/bulk-update', 'bulkUpdateItems')->name('item.bulk-update')->middleware('permission:item-list');
        Route::post('item/bulk-delete', 'bulkDeleteItems')->name('item.bulk-delete')->middleware('permission:item-destroy');
    });


    // Unit CRUD Routes
    Route::controller(UnitController::class)->group(function () {
        Route::get('unit', 'index')->name('unit.index')->middleware('permission:unit-list');
        Route::get('unit/create', 'create')->name('unit.create')->middleware('permission:unit-create');
        Route::post('unit', 'store')->name('unit.store')->middleware('permission:unit-create');
        Route::get('unit/{unit}', 'show')->name('unit.show')->middleware('permission:unit-show');
        Route::get('unit/{unit}/edit', 'edit')->name('unit.edit')->middleware('permission:unit-edit');
        Route::put('unit/{unit}', 'update')->name('unit.update')->middleware('permission:unit-edit');
        Route::delete('unit/{unit}', 'destroy')->name('unit.destroy')->middleware('permission:unit-destroy');
    });

    // Brand CRUD Routes
    Route::controller(BrandController::class)->group(function () {
        Route::get('brand', 'index')->name('brand.index')->middleware('permission:brand-list');
        Route::get('brand/create', 'create')->name('brand.create')->middleware('permission:brand-create');
        Route::post('brand', 'store')->name('brand.store')->middleware('permission:brand-create');
        Route::get('brand/{brand}', 'show')->name('brand.show')->middleware('permission:brand-show');
        Route::get('brand/{brand}/edit', 'edit')->name('brand.edit')->middleware('permission:brand-edit');
        Route::put('brand/{brand}', 'update')->name('brand.update')->middleware('permission:brand-edit');
        Route::delete('brand/{brand}', 'destroy')->name('brand.destroy')->middleware('permission:brand-destroy');
    });

    // Item Category CRUD Routes
    Route::controller(ItemCategoryController::class)->group(function () {
        Route::get('item-category', 'index')->name('item-category.index')->middleware('permission:category-list');
        Route::get('item-category/create', 'create')->name('item-category.create')->middleware('permission:category-create');
        Route::post('item-category', 'store')->name('item-category.store')->middleware('permission:category-create');
        // Static routes must come before dynamic routes to prevent route conflicts
        Route::get('item-category/sort-category', 'sortCategory')->name('item-category.sort-category')->middleware('permission:category-list');
        Route::post('item-category/update-sort-order', 'updateSortOrder')->name('item-category.update-sort-order')->middleware('permission:category-edit');
        // Dynamic routes (with parameters) - must come after static routes
        Route::get('item-category/{item_category}', 'show')->name('item-category.show')->middleware('permission:category-show');
        Route::get('item-category/{item_category}/edit', 'edit')->name('item-category.edit')->middleware('permission:category-edit');
        Route::put('item-category/{item_category}', 'update')->name('item-category.update')->middleware('permission:category-edit');
        Route::delete('item-category/{item_category}', 'destroy')->name('item-category.destroy')->middleware('permission:category-destroy');
    });

    

    // Rack CRUD Routes
    Route::controller(RackController::class)->group(function () {
        Route::get('rack', 'index')->name('rack.index')->middleware('permission:rack-list');
        Route::get('rack/create', 'create')->name('rack.create')->middleware('permission:rack-create');
        Route::post('rack', 'store')->name('rack.store')->middleware('permission:rack-create');
        Route::get('rack/{rack}', 'show')->name('rack.show')->middleware('permission:rack-show');
        Route::get('rack/{rack}/edit', 'edit')->name('rack.edit')->middleware('permission:rack-edit');
        Route::put('rack/{rack}', 'update')->name('rack.update')->middleware('permission:rack-edit');
        Route::delete('rack/{rack}', 'destroy')->name('rack.destroy')->middleware('permission:rack-destroy');
    });

    // Variation Attribute CRUD Routes
    Route::controller(VariationAttributeController::class)->group(function () {
        Route::get('variation-attribute', 'index')->name('variation-attribute.index')->middleware('permission:variation_attribute-list');
        Route::get('variation-attribute/create', 'create')->name('variation-attribute.create')->middleware('permission:variation_attribute-create');
        Route::post('variation-attribute', 'store')->name('variation-attribute.store')->middleware('permission:variation_attribute-create');
        Route::get('variation-attribute/{variation_attribute}', 'show')->name('variation-attribute.show')->middleware('permission:variation_attribute-show');
        Route::get('variation-attribute/{variation_attribute}/edit', 'edit')->name('variation-attribute.edit')->middleware('permission:variation_attribute-edit');
        Route::put('variation-attribute/{variation_attribute}', 'update')->name('variation-attribute.update')->middleware('permission:variation_attribute-edit');
        Route::delete('variation-attribute/{variation_attribute}', 'destroy')->name('variation-attribute.destroy')->middleware('permission:variation_attribute-destroy');
    });

    // Damage CRUD Routes
    Route::controller(DamageController::class)->group(function () {
        // Static routes must come before dynamic routes to prevent conflicts
        Route::get('damage', 'index')->name('damage.index')->middleware(['permission:damage-list', 'outlet_set', 'register_open']);
        Route::get('damage/create', 'create')->name('damage.create')->middleware(['permission:damage-create', 'outlet_set', 'register_open']);
        Route::post('damage', 'store')->name('damage.store')->middleware(['permission:damage-create', 'outlet_set', 'register_open']);
        Route::get('api/damage/item-details', 'getItemDetails')->name('damage.item-details');
        Route::get('api/damage/variation-child-items', 'getVariationChildItems')->name('damage.variation-child-items');
        Route::get('api/damage/item-current-stock', 'getItemCurrentStock')->name('damage.item-current-stock');
        Route::post('api/damage/check-imei-in-outlet', 'checkImeiSerialInOutlet')->name('damage.check-imei-in-outlet');
        
        // Dynamic routes (with parameters)
        Route::get('damage/{damage}', 'show')->name('damage.show')->middleware(['permission:damage-show', 'outlet_set']);
        Route::get('damage/{damage}/edit', 'edit')->name('damage.edit')->middleware(['permission:damage-edit', 'outlet_set', 'register_open']);
        Route::put('damage/{damage}', 'update')->name('damage.update')->middleware(['permission:damage-edit', 'outlet_set', 'register_open']);
        Route::delete('damage/{damage}', 'destroy')->name('damage.destroy')->middleware(['permission:damage-destroy', 'outlet_set', 'register_open']);
    });

    // Transfer CRUD Routes
    Route::controller(TransferController::class)->group(function () {
        // Static routes must come before dynamic routes to prevent conflicts
        Route::get('transfer', 'index')->name('transfer.index')->middleware(['permission:transfer-list', 'outlet_set', 'register_open']);
        Route::get('transfer/create', 'create')->name('transfer.create')->middleware(['permission:transfer-create', 'outlet_set', 'register_open']);
        Route::post('transfer', 'store')->name('transfer.store')->middleware(['permission:transfer-create', 'outlet_set', 'register_open']);
        Route::get('api/transfer/variation-child-items', 'getVariationChildItems')->name('transfer.variation-child-items');
        Route::get('api/transfer/item-stock-at-outlet', 'getItemStockAtOutlet')->name('transfer.item-stock-at-outlet');
        Route::post('api/transfer/check-imei-in-outlet', 'checkImeiSerialInFromOutlet')->name('transfer.check-imei-in-outlet');

        // Dynamic routes (with parameters)
        Route::get('transfer/{transfer}', 'show')->name('transfer.show')->middleware(['permission:transfer-show', 'outlet_set']);
        Route::get('transfer/{transfer}/edit', 'edit')->name('transfer.edit')->middleware(['permission:transfer-edit', 'outlet_set', 'register_open']);
        Route::put('transfer/{transfer}', 'update')->name('transfer.update')->middleware(['permission:transfer-edit', 'outlet_set', 'register_open']);
        Route::delete('transfer/{transfer}', 'destroy')->name('transfer.destroy')->middleware(['permission:transfer-destroy', 'outlet_set', 'register_open']);
    });

    // Fixed Asset Item CRUD Routes
    Route::controller(FixedAssetItemController::class)->group(function () {
        Route::get('fixed-asset-item', 'index')->name('fixed-asset-item.index')->middleware('permission:fixed_asset_item-list');
        Route::get('fixed-asset-item/create', 'create')->name('fixed-asset-item.create')->middleware('permission:fixed_asset_item-create');
        Route::post('fixed-asset-item', 'store')->name('fixed-asset-item.store')->middleware('permission:fixed_asset_item-create');
        Route::get('fixed-asset-item/{fixed_asset_item}/edit', 'edit')->name('fixed-asset-item.edit')->middleware('permission:fixed_asset_item-edit');
        Route::put('fixed-asset-item/{fixed_asset_item}', 'update')->name('fixed-asset-item.update')->middleware('permission:fixed_asset_item-edit');
        Route::delete('fixed-asset-item/{fixed_asset_item}', 'destroy')->name('fixed-asset-item.destroy')->middleware('permission:fixed_asset_item-destroy');
    });

    // Fixed Asset Stock In CRUD Routes
    Route::controller(FixedAssetStockInController::class)->group(function () {
        Route::get('fixed-asset-stock-in', 'index')->name('fixed-asset-stock-in.index')->middleware('permission:fixed_asset_stock_in-list');
        Route::get('fixed-asset-stock-in/create', 'create')->name('fixed-asset-stock-in.create')->middleware('permission:fixed_asset_stock_in-create');
        Route::post('fixed-asset-stock-in', 'store')->name('fixed-asset-stock-in.store')->middleware('permission:fixed_asset_stock_in-create');
        Route::get('fixed-asset-stock-in/{fixed_asset_stock_in}', 'show')->name('fixed-asset-stock-in.show')->middleware('permission:fixed_asset_stock_in-show');
        Route::get('fixed-asset-stock-in/{fixed_asset_stock_in}/edit', 'edit')->name('fixed-asset-stock-in.edit')->middleware('permission:fixed_asset_stock_in-edit');
        Route::put('fixed-asset-stock-in/{fixed_asset_stock_in}', 'update')->name('fixed-asset-stock-in.update')->middleware('permission:fixed_asset_stock_in-edit');
        Route::delete('fixed-asset-stock-in/{fixed_asset_stock_in}', 'destroy')->name('fixed-asset-stock-in.destroy')->middleware('permission:fixed_asset_stock_in-destroy');
    });

    // Fixed Asset Stock Out CRUD Routes
    Route::controller(FixedAssetStockOutController::class)->group(function () {
        Route::get('fixed-asset-stock-out', 'index')->name('fixed-asset-stock-out.index')->middleware('permission:fixed_asset_stock_out-list');
        Route::get('fixed-asset-stock-out/create', 'create')->name('fixed-asset-stock-out.create')->middleware('permission:fixed_asset_stock_out-create');
        Route::post('fixed-asset-stock-out', 'store')->name('fixed-asset-stock-out.store')->middleware('permission:fixed_asset_stock_out-create');
        Route::get('fixed-asset-stock-out/{fixed_asset_stock_out}', 'show')->name('fixed-asset-stock-out.show')->middleware('permission:fixed_asset_stock_out-show');
        Route::get('fixed-asset-stock-out/{fixed_asset_stock_out}/edit', 'edit')->name('fixed-asset-stock-out.edit')->middleware('permission:fixed_asset_stock_out-edit');
        Route::put('fixed-asset-stock-out/{fixed_asset_stock_out}', 'update')->name('fixed-asset-stock-out.update')->middleware('permission:fixed_asset_stock_out-edit');
        Route::delete('fixed-asset-stock-out/{fixed_asset_stock_out}', 'destroy')->name('fixed-asset-stock-out.destroy')->middleware('permission:fixed_asset_stock_out-destroy');
    });

    // Price List CRUD Routes
    Route::controller(PriceListController::class)->group(function () {
        Route::get('price-list', 'index')->name('price-list.index')->middleware('permission:price_list-list');
        Route::get('price-list/create', 'create')->name('price-list.create')->middleware('permission:price_list-create');
        Route::post('price-list', 'store')->name('price-list.store')->middleware('permission:price_list-create');
        Route::get('price-list/{price_list}/edit', 'edit')->name('price-list.edit')->middleware('permission:price_list-edit');
        Route::put('price-list/{price_list}', 'update')->name('price-list.update')->middleware('permission:price_list-edit');
        Route::delete('price-list/{price_list}', 'destroy')->name('price-list.destroy')->middleware('permission:price_list-destroy');
        Route::get('price-list/list-for-select', 'listForSelect')->name('price-list.list-for-select');
    });


});
