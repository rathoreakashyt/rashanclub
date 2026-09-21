<?php

namespace Modules\Stock\Services;

use App\Services\BippService;
use Modules\Stock\Models\Item;
use Modules\Stock\Models\SetOpeningStock;
use Modules\Stock\Repositories\ItemRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ItemService
{
    protected $itemRepository;

    /**
     * ItemService constructor.
     *
     * @param ItemRepository $itemRepository
     */
    public function __construct(ItemRepository $itemRepository)
    {
        $this->itemRepository = $itemRepository;
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->itemRepository->getDataTableData($params);
    }

    /**
     * Get data for Bulk Update DataTables
     */
    public function getBulkUpdateDataTableData(array $params): array
    {
        return $this->itemRepository->getBulkUpdateDataTableData($params);
    }

    /**
     * Get all items
     */
    public function getAllItems()
    {
        return $this->itemRepository->all();
    }

    /**
     * Get items with pagination
     */
    public function getItemsPaginated(int $perPage = 10)
    {
        return $this->itemRepository->paginate($perPage);
    }

    /**
     * Get item by encrypted ID
     */
    public function getItemByEncryptedId(string $encryptedId): ?Item
    {
        return $this->itemRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new item
     */
    public function createItem(array $data, $request): Item
    {
        if (!BippService::canAddProducts(1)) {
            throw ValidationException::withMessages([
                'package_limit' => [BippService::productLimitMessage()],
            ]);
        }

        // Process tax information if taxes are present
        if(session('company.collect_tax') == 'Yes'){
            if (!empty($data['taxes']) && !empty($data['tax_rate'])) {
                $taxSettings = [];
                foreach ($data['taxes'] as $key => $taxName) {
                    $taxSettings[] = [
                        'tax_field_id' => '1',
                        'tax_field_company_id' => session('company.company_id'), 
                        'tax_field_name' => $taxName,
                        'tax_field_percentage' => $data['tax_rate'][$key]
                    ];
                }
                // $data['tax_information'] = json_encode($taxSettings);
                $data['tax_information'] = $taxSettings;
                
                // Create tax string
                $data['tax_string'] = implode(':', array_map(function($tax) {
                    return $tax['tax_field_name'];
                }, $taxSettings)) . ':';
            }
        }

        // Save applicable tax IDs (multi-select from item profile taxes) as comma-separated
        if (isset($data['applicable_tax_id'])) {
            $data['applicable_tax_id'] = is_array($data['applicable_tax_id'])
                ? implode(',', array_filter($data['applicable_tax_id']))
                : $data['applicable_tax_id'];
            $data['applicable_tax_id'] = $data['applicable_tax_id'] ?: null;
        }

        // Add user and company information
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        $data['parent_id'] = null;

        // Set mrp_price if not provided
        if (!isset($data['mrp_price']) || $data['mrp_price'] === null || $data['mrp_price'] === '') {
            $data['mrp_price'] = $data['sale_price'] ?? 0;
        }

        // If unit type is single, save conversion_rate = 1 and purchase_unit_id = sale_unit_id
        if (isset($data['unit_type']) && $data['unit_type'] == 1) {
            $data['conversion_rate'] = 1;
            if (isset($data['sale_unit_id']) && !empty($data['sale_unit_id'])) {
                $data['purchase_unit_id'] = $data['sale_unit_id'];
            }
        }

        // Save purchase_price as last_three_purchase_avg and last_purchase_price during create (not update)
        if (isset($data['purchase_price']) && $data['purchase_price'] !== null) {
            $data['last_three_purchase_avg'] = $data['purchase_price'];
            $data['last_purchase_price'] = $data['purchase_price'];
        }

        // Service_Product specific handling - services don't have units
        if ($data['type'] === 'Service_Product') {
            // Service products don't need unit information, purchase price, whole sale price, or stock information
            $data['unit_type'] = null;
            $data['sale_unit_id'] = null;
            $data['purchase_unit_id'] = null;
            $data['conversion_rate'] = null;
            $data['purchase_price'] = null;
            $data['whole_sale_price'] = null;
            $data['mrp_price'] = null;
            $data['alert_quantity'] = null;
        }

        // Combo_Product specific handling
        if ($data['type'] === 'Combo_Product') {
            // Combo products don't need purchase price, whole sale price
            $data['sale_price'] = $request->combo_sale_price ?? 0;
            $data['purchase_price'] = null;
            $data['whole_sale_price'] = null;
            $data['unit_type'] = null;
            $data['sale_unit_id'] = null;
            $data['purchase_unit_id'] = null;
            $data['conversion_rate'] = 1; // Set conversion rate to 1 for Combo_Product
            $data['combo_sale_price'] = $request->combo_sale_price;
        }

        // IMEI_Product, Serial_Product, Installment_Product - set conversion_rate = 1
        // Note: Medicine_Product can use double unit, so don't force conversion_rate = 1
        if (in_array($data['type'], ['IMEI_Product', 'Serial_Product', 'Installment_Product'])) {
            $data['conversion_rate'] = 1;
        }

        // Convert expiry_date_maintain from Yes/No to 1/0
        if (array_key_exists('expiry_date_maintain', $data)) {
            $data['expiry_date_maintain'] = $data['expiry_date_maintain'] === 'Yes' ? 1 : 0;
        }

        // Handle file upload if present
        if ($request->hasFile('photo')) {
            $data['photo'] = $this->storePhoto($request->file('photo'));
        }

        $item = $this->itemRepository->create($data);

        // Handle opening stock if present (not applicable for Service_Product)
        if ($data['type'] !== 'Service_Product') {
            $this->saveOpeningStock($item, $request, $data['type']);
        }

        // Handle combo items if Combo_Product
        if ($data['type'] === 'Combo_Product' && $request->has('combo_items')) {
            $this->saveComboItems($item, $request);
        }

        return $item;
    }

    /**
     * Create variation product with child items
     */
    public function createVariationProduct(array $data, $request): array
    {
        $variationCount = is_array($request->variations) ? count($request->variations) : 0;
        $totalToAdd = 1 + $variationCount; // parent + children
        if (!BippService::canAddProducts($totalToAdd)) {
            throw ValidationException::withMessages([
                'package_limit' => [BippService::productLimitMessage()],
            ]);
        }

        // Process tax information if taxes are present
        if(session('company.collect_tax') == 'Yes'){
            if (!empty($data['taxes']) && !empty($data['tax_rate'])) {
                $taxSettings = [];
                foreach ($data['taxes'] as $key => $taxName) {
                    $taxSettings[] = [
                        'tax_field_id' => '1',
                        'tax_field_company_id' => session('company.company_id'), 
                        'tax_field_name' => $taxName,
                        'tax_field_percentage' => $data['tax_rate'][$key]
                    ];
                }
                // $data['tax_information'] = json_encode($taxSettings);
                $data['tax_information'] = $taxSettings;
                
                // Create tax string
                $data['tax_string'] = implode(':', array_map(function($tax) {
                    return $tax['tax_field_name'];
                }, $taxSettings)) . ':';
            }
        }

        // Save applicable tax IDs as comma-separated for parent item
        if (isset($data['applicable_tax_id'])) {
            $data['applicable_tax_id'] = is_array($data['applicable_tax_id'])
                ? implode(',', array_filter($data['applicable_tax_id']))
                : $data['applicable_tax_id'];
            $data['applicable_tax_id'] = $data['applicable_tax_id'] ?: null;
        }

        // Create parent item
        $parentData = $data;
        $parentData['parent_id'] = null;
        $parentData['sale_price'] = 0; // Parent item doesn't have price
        $parentData['purchase_price'] = null;
        $parentData['whole_sale_price'] = null;
        $parentData['user_id'] = Auth::id();
        $parentData['company_id'] = session('company.company_id');
        
        // Convert expiry_date_maintain from Yes/No to 1/0
        if (array_key_exists('expiry_date_maintain', $parentData)) {
            $parentData['expiry_date_maintain'] = $parentData['expiry_date_maintain'] === 'Yes' ? 1 : 0;
        }
        
        // Store variation details in new format
        // Format: ["{\"variation_name\":\"Color\",\"attribute_id\":\"2\",\"child_row_attribute\":\"[\\\"White\\\",\\\"Black\\\"]\"}","{\"variation_name\":\"Size\",\"attribute_id\":\"1\",\"child_row_attribute\":\"[\\\"M\\\",\\\"L\\\"]\"}"]
        if ($request->has('variation_details') && !empty($request->variation_details)) {
            // variation_details is a JSON string of an array of JSON strings
            // Decode to get the array of JSON strings, then store as array (Laravel will auto-encode)
            $variationDetails = json_decode($request->variation_details, true);
            // Store as array of JSON strings (each element is already a JSON string)
            $parentData['variation_details'] = $variationDetails;
        } else {
            // Fallback to old format for backward compatibility
            $variationDetails = [];
            foreach ($request->variations as $variation) {
                $variationDetails[] = [
                    'variation_name' => $variation['variation_name'],
                    'variation_combination' => json_decode($variation['variation_combination'], true),
                    'variation_attributes' => json_decode($variation['variation_attributes'], true),
                ];
            }
            $parentData['variation_details'] = $variationDetails;
        }
        
        // Handle file upload if present
        if ($request->hasFile('photo')) {
            $parentData['photo'] = $this->storePhoto($request->file('photo'));
        }
        
        $parentItem = $this->itemRepository->create($parentData);
        
        // Create child items for each variation
        $createdVariations = [];
        foreach ($request->variations as $index => $variation) {
            // Validate unique code for each variation
            if ($this->itemRepository->codeExists($variation['item_code'])) {
                throw ValidationException::withMessages([
                    'variations' => ['Item code "' . $variation['item_code'] . '" already exists for variation "' . $variation['variation_name'] . '"']
                ]);
            }
            
            // Use only variation name, not parent name (to avoid name length issues)
            $variationName = $variation['variation_name'];
            if (mb_strlen($variationName) > 55) {
                throw ValidationException::withMessages([
                    "variations.{$index}.variation_name" => ['The variation name may not be greater than 55 characters.']
                ]);
            }
            
            $childData = $data;
            $childData['parent_id'] = $parentItem->id;
            $childData['name'] = $variationName;
            $childData['alternative_name'] = $variation['alternative_name'] ?? null;
            $childData['code'] = $variation['item_code'];
            $childData['sale_price'] = $variation['sale_price'];
            $childData['purchase_price'] = $variation['purchase_price'] ?? null;
            $childData['whole_sale_price'] = $variation['whole_sale_price'] ?? null;
            $childData['mrp_price'] = $variation['mrp_price'] ?? ($variation['sale_price'] ?? 0);
            $childData['alert_quantity'] = $variation['alert_quantity'] ?? null;
            $childData['variation_details'] = json_encode([
                'variation_name' => $variation['variation_name'],
                'variation_combination' => json_decode($variation['variation_combination'], true),
                'variation_attributes' => json_decode($variation['variation_attributes'], true),
            ]);
            $childData['user_id'] = Auth::id();
            $childData['company_id'] = session('company.company_id');
            // Set variation child type to '0'
            $childData['type'] = '0';
            // Convert expiry_date_maintain from Yes/No to 1/0 for child items
            if (array_key_exists('expiry_date_maintain', $childData)) {
                $childData['expiry_date_maintain'] = $childData['expiry_date_maintain'] === 'Yes' ? 1 : 0;
            }
            
            // If unit type is single, save conversion_rate = 1 and purchase_unit_id = sale_unit_id
            if (isset($childData['unit_type']) && $childData['unit_type'] == 1) {
                $childData['conversion_rate'] = 1;
                if (isset($childData['sale_unit_id']) && !empty($childData['sale_unit_id'])) {
                    $childData['purchase_unit_id'] = $childData['sale_unit_id'];
                }
            }
            
            // Save purchase_price as last_three_purchase_avg and last_purchase_price during create
            if (isset($childData['purchase_price']) && $childData['purchase_price'] !== null) {
                $childData['last_three_purchase_avg'] = $childData['purchase_price'];
                $childData['last_purchase_price'] = $childData['purchase_price'];
            }
            
            // Handle variation photo if present
            // Photos are sent as variations[0][photo], variations[1][photo], etc.
            if ($request->hasFile("variations.{$index}.photo")) {
                $childData['photo'] = $this->storePhoto($request->file("variations.{$index}.photo"));
            } else {
                // Use parent photo if no variation photo is provided
                $childData['photo'] = $parentItem->photo;
            }
            
            $childItem = $this->itemRepository->create($childData);
            $createdVariations[] = $childItem;
            
            // Handle opening stock for this variation child item (type '0')
            if (isset($variation['opening_stock']) && is_array($variation['opening_stock'])) {
                $hasOpeningStock = false;
                foreach ($variation['opening_stock'] as $outletId => $quantity) {
                    if ((float)$quantity > 0) {
                        $hasOpeningStock = true;
                        break;
                    }
                }
                
                // If opening stock exists and purchase_price is set, save last_three_purchase_avg and last_purchase_price
                if ($hasOpeningStock && isset($childData['purchase_price']) && $childData['purchase_price'] !== null) {
                    $childItem->last_three_purchase_avg = $childData['purchase_price'];
                    $childItem->last_purchase_price = $childData['purchase_price'];
                    $childItem->save();
                }
                
                $this->saveVariationOpeningStock($childItem, $variation['opening_stock'], '0');
            }
        }
        
        return [
            'parent_item' => $parentItem,
            'variations' => $createdVariations
        ];
    }

    /**
     * Update variation product with child items
     */
    public function updateVariationProduct(Item $parentItem, $request, array $data): void
    {
        // Update variation details if provided
        if ($request->has('variation_details') && !empty($request->variation_details)) {
            $variationDetails = json_decode($request->variation_details, true);
            $parentItem->variation_details = $variationDetails;
            $parentItem->save();
        }

        // Get existing variation IDs to track which ones to keep
        $existingVariationIds = [];
        if ($request->has('variations')) {
            foreach ($request->variations as $index => $variation) {
                $variationId = null;
                if (isset($variation['id'])) {
                    try {
                        $variationId = decrypt($variation['id']);
                    } catch (\Exception $e) {
                        // Invalid encrypted ID, create new variation
                    }
                }

                if ($variationId) {
                    // Update existing variation
                    $childItem = Item::where('id', $variationId)
                        ->where('parent_id', $parentItem->id)
                        ->first();
                    
                    if ($childItem) {
                        $existingVariationIds[] = $childItem->id;
                        
                        // Use only variation name, not parent name (to avoid name length issues)
                        $variationName = $variation['variation_name'];
                        if (mb_strlen($variationName) > 55) {
                            throw ValidationException::withMessages([
                                "variations.{$index}.variation_name" => ['The variation name may not be greater than 55 characters.']
                            ]);
                        }
                        
                        $childData = [
                            'name' => $variationName,
                            'code' => $variation['item_code'],
                            'sale_price' => $variation['sale_price'],
                            'purchase_price' => $variation['purchase_price'] ?? null,
                            'whole_sale_price' => $variation['whole_sale_price'] ?? null,
                            'mrp_price' => $variation['mrp_price'] ?? ($variation['sale_price'] ?? 0),
                            'alert_quantity' => $variation['alert_quantity'] ?? null,
                            'variation_details' => json_encode([
                                'variation_name' => $variation['variation_name'],
                                'variation_combination' => json_decode($variation['variation_combination'], true),
                                'variation_attributes' => json_decode($variation['variation_attributes'], true),
                            ]),
                            'user_id' => Auth::id(),
                        ];
                        
                        // Handle variation photo if present
                        if ($request->hasFile("variations.{$index}.photo")) {
                            // Delete old photo if exists
                            if ($childItem->photo) {
                                $this->deletePhoto($childItem->photo);
                            }
                            $childData['photo'] = $this->storePhoto($request->file("variations.{$index}.photo"));
                        }
                        
                        $this->itemRepository->update($childItem, $childData);
                        
                        // Handle opening stock for this variation
                        if (isset($variation['opening_stock']) && is_array($variation['opening_stock'])) {
                            // Delete existing opening stock
                            SetOpeningStock::where('item_id', $childItem->id)->delete();
                            // Save new opening stock
                            $this->saveVariationOpeningStock($childItem, $variation['opening_stock'], '0');
                        }
                    }
                } else {
                    // Create new variation
                    // Use only variation name, not parent name (to avoid name length issues)
                    $variationName = $variation['variation_name'];
                    if (mb_strlen($variationName) > 55) {
                        throw ValidationException::withMessages([
                            "variations.{$index}.variation_name" => ['The variation name may not be greater than 55 characters.']
                        ]);
                    }
                    
                    $childData = $data;
                    $childData['parent_id'] = $parentItem->id;
                    $childData['name'] = $variationName;
                    $childData['code'] = $variation['item_code'];
                    $childData['sale_price'] = $variation['sale_price'];
                    $childData['purchase_price'] = $variation['purchase_price'] ?? null;
                    $childData['whole_sale_price'] = $variation['whole_sale_price'] ?? null;
                    $childData['mrp_price'] = $variation['mrp_price'] ?? ($variation['sale_price'] ?? 0);
                    $childData['alert_quantity'] = $variation['alert_quantity'] ?? null;
                    $childData['variation_details'] = json_encode([
                        'variation_name' => $variation['variation_name'],
                        'variation_combination' => json_decode($variation['variation_combination'], true),
                        'variation_attributes' => json_decode($variation['variation_attributes'], true),
                    ]);
                    $childData['user_id'] = Auth::id();
                    $childData['company_id'] = session('company.company_id');
                    $childData['type'] = '0';
                    
                    if (isset($childData['unit_type']) && $childData['unit_type'] == 1) {
                        $childData['conversion_rate'] = 1;
                        if (isset($childData['sale_unit_id']) && !empty($childData['sale_unit_id'])) {
                            $childData['purchase_unit_id'] = $childData['sale_unit_id'];
                        }
                    }
                    
                    // Handle variation photo if present
                    if ($request->hasFile("variations.{$index}.photo")) {
                        $childData['photo'] = $this->storePhoto($request->file("variations.{$index}.photo"));
                    } else {
                        $childData['photo'] = $parentItem->photo;
                    }
                    
                    $childItem = $this->itemRepository->create($childData);
                    $existingVariationIds[] = $childItem->id;
                    
                    // Handle opening stock for new variation
                    if (isset($variation['opening_stock']) && is_array($variation['opening_stock'])) {
                        $this->saveVariationOpeningStock($childItem, $variation['opening_stock'], '0');
                    }
                }
            }
        }
        
        // Delete variations that are no longer in the request
        Item::where('parent_id', $parentItem->id)
            ->whereNotIn('id', $existingVariationIds)
            ->delete();
    }

    /**
     * Update an existing item
     */
    public function updateItem(string $encryptedId, array $data, $request): bool
    {
        $item = $this->itemRepository->findByEncryptedId($encryptedId);
        if (!$item) {
            throw new \Exception('Item not found');
        }

        // Process tax information if taxes are present
        if(session('company.collect_tax') == 'Yes'){
            if (!empty($data['taxes']) && !empty($data['tax_rate'])) {
                $taxSettings = [];
                foreach ($data['taxes'] as $key => $taxName) {
                    $taxSettings[] = [ 
                        'tax_field_name' => $taxName,
                        'tax_field_percentage' => $data['tax_rate'][$key]
                    ];
                }
                $data['tax_information'] = json_encode($taxSettings);
                
                // Create tax string
                $data['tax_string'] = implode(':', array_map(function($tax) {
                    return $tax['tax_field_name'];
                }, $taxSettings)) . ':';
            }
        }

        // Save applicable tax IDs (multi-select from item profile taxes) as comma-separated
        if (array_key_exists('applicable_tax_id', $data)) {
            $data['applicable_tax_id'] = is_array($data['applicable_tax_id'])
                ? implode(',', array_filter($data['applicable_tax_id']))
                : $data['applicable_tax_id'];
            $data['applicable_tax_id'] = $data['applicable_tax_id'] ?: null;
        }

        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');

        // If unit type is single, save conversion_rate = 1 and purchase_unit_id = sale_unit_id
        if (isset($data['unit_type']) && $data['unit_type'] == 1) {
            $data['conversion_rate'] = 1;
            if (isset($data['sale_unit_id']) && !empty($data['sale_unit_id'])) {
                $data['purchase_unit_id'] = $data['sale_unit_id'];
            }
        }

        // Convert expiry_date_maintain from Yes/No to 1/0
        if (array_key_exists('expiry_date_maintain', $data)) {
            $data['expiry_date_maintain'] = $data['expiry_date_maintain'] === 'Yes' ? 1 : 0;
        }

        // Handle file upload if present
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($item->photo) {
                $this->deletePhoto($item->photo);
            }
            $data['photo'] = $this->storePhoto($request->file('photo'));
        }

        // For Variation_Product parents, don't overwrite prices with parent form data
        // (parent prices should stay 0; real prices live on children)
        if ($item->type === 'Variation_Product') {
            unset($data['sale_price'], $data['purchase_price'], $data['mrp_price'], $data['whole_sale_price']);
        }

        $updated = $this->itemRepository->update($item, $data);

        // Handle Variation Product updates
        if ($item->type === 'Variation_Product' && $request->has('variations')) {
            $this->updateVariationProduct($item, $request, $data);
        }

        // Handle Combo Product updates
        if ($item->type === 'Combo_Product' && $request->has('combo_items')) {
            // Delete existing combo items
            \Modules\Stock\Models\ComboItem::where('combo_item_id', $item->id)->delete();
            // Save new combo items
            $this->saveComboItems($item, $request);
        }

        // Handle opening stock update if present (not for Variation_Product parent, handled in updateVariationProduct)
        if ($item->type !== 'Variation_Product' && ($request->has('outlet_id') || $request->has('opening_stock'))) {
            // Delete existing opening stock records for this item
            SetOpeningStock::where('item_id', $item->id)->delete();
            
            // Save new opening stock
            $this->saveOpeningStock($item, $request, $item->type);
        }

        return $updated;
    }

    /**
     * Delete an item
     */
    public function deleteItem(string $encryptedId): bool
    {
        $item = $this->itemRepository->findByEncryptedId($encryptedId);
        if (!$item) {
            throw new \Exception('Item not found');
        }
        
        // Delete photo if exists
        if ($item->photo) {
            $this->deletePhoto($item->photo);
        }
        
        return $this->itemRepository->delete($item);
    }

    /**
     * Save combo items for a combo product
     */
    private function saveComboItems(Item $item, $request): void
    {
        if ($request->has('combo_items') && is_array($request->combo_items)) {
            foreach ($request->combo_items as $comboItemData) {
                \Modules\Stock\Models\ComboItem::create([
                    'combo_item_id' => $item->id,
                    'item_id' => $comboItemData['item_id'],
                    'quantity' => $comboItemData['quantity'],
                    'amount' => $comboItemData['amount'],
                    'total' => $comboItemData['total'],
                    'show_in_invoice' => isset($comboItemData['show_in_invoice']) ? (bool)$comboItemData['show_in_invoice'] : true,
                    'user_id' => Auth::id(),
                    'company_id' => session('company.company_id'),
                ]);
            }
        }
    }

    
    /**
     * Store photo file
     */
    private function storePhoto($photo): string
    {
        $extension = $photo->getClientOriginalExtension();
        $photoName = time() . '_' . uniqid() . '.' . $extension;
        $photo->move(public_path('uploads/items'), $photoName);
        return $photoName;
    }

    /**
     * Delete photo file
     */
    private function deletePhoto($photoName): void
    {
        $photoPath = public_path('uploads/items/' . $photoName);
        if (file_exists($photoPath)) {
            unlink($photoPath);
        }
    }

    /**
     * Get item statistics
     */
    public function getItemStatistics()
    {
        return [
            'total' => $this->itemRepository->getTotalCount(),
        ];
    }

    /**
     * Save opening stock for an item
     */
    private function saveOpeningStock(Item $item, $request, string $itemType): void
    {
        $totalStock = 0;
        // Handle opening stock from modal (for IMEI, Serial, Medicine products)
        if ($request->has('outlet_id') && is_array($request->outlet_id)) {
            foreach ($request->outlet_id as $key => $outletId) {
                $quantity = isset($request->quantity[$key]) ? (float)$request->quantity[$key] : 0;
                $itemDescription = isset($request->item_description[$key]) 
                    ? (is_array($request->item_description[$key]) 
                        ? json_encode($request->item_description[$key]) 
                        : $request->item_description[$key]) 
                    : null;

                if ($quantity > 0 || !empty($itemDescription)) {
                    $quantity = $quantity * $item->conversion_rate;
                    SetOpeningStock::create([
                        'item_id' => $item->id,
                        'item_type' => $itemType,
                        'item_description' => $itemDescription,
                        'stock_quantity' => $quantity,
                        'outlet_id' => $outletId,
                        'user_id' => Auth::id(),
                        'company_id' => session('company.company_id'),
                    ]);
                    $totalStock += $quantity;
                }
            }
        }
        // Handle simple opening stock (for General products)
        elseif ($request->has('opening_stock') && !empty($request->opening_stock)) {
            // Get all outlets for the company
            $outlets = \Modules\Configuration\Models\Outlet::where('company_id', session('company.company_id'))
                ->where('del_status', 'Live')
                ->where('active_status', 'Active')
                ->get();

            foreach ($outlets as $outlet) {
                $quantity = (float)$request->opening_stock;
                $quantity = $quantity * $item->conversion_rate;
                SetOpeningStock::create([
                    'item_id' => $item->id,
                    'item_type' => $itemType,
                    'item_description' => null,
                    'stock_quantity' => $quantity,
                    'outlet_id' => $outlet->id,
                    'user_id' => Auth::id(),
                    'company_id' => session('company.company_id'),
                ]);
                $totalStock += $quantity;
            }
        }
        // Update items.stock_quantity so desktop sync picks it up
        if ($totalStock > 0) {
            $item->stock_quantity = $totalStock;
            $item->save();
        }
    }

    /**
     * Save opening stock for variation child items
     */
    private function saveVariationOpeningStock(Item $item, array $openingStockData, string $itemType): void
    {
        $totalStock = 0;
        foreach ($openingStockData as $outletId => $quantity) {
            $quantity = (float)$quantity;
            $quantity = $quantity * $item->conversion_rate;
            if ($quantity > 0) {
                SetOpeningStock::create([
                    'item_id' => $item->id,
                    'item_type' => $itemType,
                    'item_description' => null,
                    'stock_quantity' => $quantity,
                    'outlet_id' => $outletId,
                    'user_id' => Auth::id(),
                    'company_id' => session('company.company_id'),
                ]);
                $totalStock += $quantity;
            }
        }
        // Update items.stock_quantity so desktop sync picks it up
        if ($totalStock > 0) {
            $item->stock_quantity = $totalStock;
            $item->save();
        }
    }
}

