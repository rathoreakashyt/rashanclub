<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Purchase\Models\Supplier;
use Modules\Stock\Models\Brand;
use Modules\Stock\Models\Category;
use Modules\Stock\Models\ItemCategory;
use Modules\Stock\Models\Unit;
use Modules\Stock\Models\Rack;
use Modules\Stock\Models\Variation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class MasterController extends Controller
{
    
    
    public function storeSupplier(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:55'],
                'contact_person' => ['required', 'string', 'max:55'],
                'phone' => ['required', 'string', 'max:25'],
                'email' => ['nullable', 'string', 'max:55', 'email'],
                'address' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:255'],
                'opening_balance' => ['nullable', 'numeric'],
                'balance_type' => ['nullable', 'in:Debit,Credit']
            ], [
                'name.required' => 'The supplier name field is required',
                'name.string' => 'The supplier name must be a string',
                'name.max' => 'The supplier name must not exceed 55 characters',
                'contact_person.required' => 'The contact person field is required',
                'contact_person.string' => 'The contact person must be a string',
                'contact_person.max' => 'The contact person must not exceed 55 characters',
                'phone.required' => 'The phone number field is required',
                'phone.string' => 'The phone number must be a string',
                'phone.max' => 'The phone number must not exceed 25 characters',
                'email.email' => 'Please enter a valid email address',
                'email.string' => 'The email must be a string',
                'email.max' => 'The email must not exceed 55 characters',
                'address.string' => 'The address must be a string',
                'address.max' => 'The address must not exceed 255 characters',
                'description.string' => 'The description must be a string',
                'description.max' => 'The description must not exceed 255 characters',
                'opening_balance.numeric' => 'The opening balance must be a number',
                'balance_type.in' => 'The balance type must be either Debit or Credit'
            ]);

            $supplier = Supplier::create([
                'name' => $validatedData['name'],
                'contact_person' => $validatedData['contact_person'],
                'phone' => $validatedData['phone'],
                'email' => $validatedData['email'] ?? null,
                'address' => $validatedData['address'] ?? null,
                'description' => $validatedData['description'] ?? null,
                'opening_balance' => $validatedData['opening_balance'] ?? 0,
                'balance_type' => $validatedData['balance_type'] ?? 'Debit',
                'user_id' => Auth::user()->id,
                'company_id' => 1,
                'del_status' => 'Live'
            ]);

            // Fetch all suppliers after creating new one
            $suppliers = Supplier::where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->orderBy('id', 'desc')
                ->get(['id', 'name']);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier created successfully',
                'data' => [
                    'supplier' => $supplier,
                    'suppliers' => $suppliers
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getSuppliers()
    {
        try {
            $suppliers = Supplier::where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->orderBy('id', 'desc')
                ->get(['id', 'name']);

            return response()->json([
                'status' => 'success',
                'data' => $suppliers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch suppliers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeBrand(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:55'],
                'description' => ['nullable', 'string', 'max:255']
            ], [
                'name.required' => 'The Brand name field is required',
                'name.string' => 'The Brand name must be a string',
                'name.max' => 'The Brand name must not exceed 55 characters',
                'description.string' => 'The description must be a string',
                'description.max' => 'The description must not exceed 255 characters'
            ]);

            $brand = Brand::create([
                'name' => $validatedData['name'],
                'description' => $validatedData['description'] ?? null,
                'user_id' => Auth::user()->id,
                'company_id' => 1,
            ]);

            // Fetch all Brand after creating new one
            $brands = Brand::where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->orderBy('id', 'desc')
                ->get(['id', 'name']);

            return response()->json([
                'status' => 'success',
                'message' => 'Brand created successfully',
                'data' => [
                    'brand' => $brand,
                    'brands' => $brands
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create Brand',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getBrands()
    {
        try {
            $brands = Brand::where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->orderBy('id', 'desc')
                ->get(['id', 'name']);

            return response()->json([
                'status' => 'success',
                'data' => $brands
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch Brands',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function storeCategory(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:55'],
                'description' => ['nullable', 'string', 'max:255']
            ], [
                'name.required' => 'The Category name field is required',
                'name.string' => 'The Category name must be a string', 
                'name.max' => 'The Category name must not exceed 55 characters',
                'description.string' => 'The description must be a string',
                'description.max' => 'The description must not exceed 255 characters'
            ]);

            $category = ItemCategory::create([
                'name' => $validatedData['name'],
                'description' => $validatedData['description'] ?? null,
                'user_id' => Auth::user()->id,
                'company_id' => 1,
            ]);

            // Fetch all Categories after creating new one
            $categories = ItemCategory::where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->orderBy('id', 'desc')
                ->get(['id', 'name']);

            return response()->json([
                'status' => 'success',
                'message' => 'Category created successfully',
                'data' => [
                    'category' => $category,
                    'categories' => $categories
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create Category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCategories()
    {
        try {
            $categories = ItemCategory::where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->orderBy('id', 'desc')
                ->get(['id', 'name']);

            return response()->json([
                'status' => 'success',
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch Categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function storeRack(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:55'],
                'description' => ['nullable', 'string', 'max:255']
            ], [
                'name.required' => 'The Rack name field is required',
                'name.string' => 'The Rack name must be a string', 
                'name.max' => 'The Rack name must not exceed 55 characters',
                'description.string' => 'The description must be a string',
                'description.max' => 'The description must not exceed 255 characters'
            ]);

            $rack = Rack::create([
                'name' => $validatedData['name'],
                'description' => $validatedData['description'] ?? null,
                'user_id' => Auth::user()->id,
                'company_id' => 1,
            ]);

            // Fetch all Racks after creating new one
            $racks = Rack::where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->orderBy('id', 'desc')
                ->get(['id', 'name']);

            return response()->json([
                'status' => 'success',
                'message' => 'Rack created successfully',
                'data' => [
                    'rack' => $rack,
                    'racks' => $racks
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create Rack',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getRacks()
    {
        try {
            $racks = Rack::where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->orderBy('id', 'desc')
                ->get(['id', 'name']);

            return response()->json([
                'status' => 'success',
                'data' => $racks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch Racks',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function storeUnit(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:55'],
                'description' => ['nullable', 'string', 'max:255']
            ], [
                'name.required' => 'The Unit name field is required',
                'name.string' => 'The Unit name must be a string',
                'name.max' => 'The Unit name must not exceed 55 characters', 
                'description.string' => 'The description must be a string',
                'description.max' => 'The description must not exceed 255 characters'
            ]);

            $unit = Unit::create([
                'unit_name' => $validatedData['name'],
                'description' => $validatedData['description'] ?? null,
                'user_id' => Auth::user()->id,
                'company_id' => 1,
            ]);

            // Fetch all Units after creating new one
            $units = Unit::where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->orderBy('id', 'desc')
                ->get(['id', 'unit_name']);

            return response()->json([
                'status' => 'success',
                'message' => 'Unit created successfully',
                'data' => [
                    'unit' => $unit,
                    'units' => $units
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed', 
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create Unit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUnits()
    {
        try {
            $units = Unit::where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->orderBy('id', 'desc')
                ->get(['id', 'unit_name']);

            return response()->json([
                'status' => 'success',
                'data' => $units
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch Units',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getVariations()
    {
        try {
            $variations = Variation::where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->orderBy('id', 'desc')
                ->get(['id', 'variation_name', 'variation_value']);

            // Transform the data to include variation_value as array
            $variations = $variations->map(function($variation) {
                return [
                    'id' => $variation->id,
                    'variation_name' => $variation->variation_name,
                    'variation_value' => is_array($variation->variation_value) 
                        ? $variation->variation_value 
                        : json_decode($variation->variation_value, true) ?? []
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $variations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch Variations',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
