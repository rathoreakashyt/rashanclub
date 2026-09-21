<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GstValidationService
{
    private const CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private array $stateCodes = [
        1 => 'Jammu & Kashmir',
        2 => 'Himachal Pradesh',
        3 => 'Punjab',
        4 => 'Chandigarh',
        5 => 'Uttarakhand',
        6 => 'Haryana',
        7 => 'Delhi',
        8 => 'Rajasthan',
        9 => 'Uttar Pradesh',
        10 => 'Bihar',
        11 => 'Sikkim',
        12 => 'Arunachal Pradesh',
        13 => 'Nagaland',
        14 => 'Manipur',
        15 => 'Mizoram',
        16 => 'Tripura',
        17 => 'Meghalaya',
        18 => 'Assam',
        19 => 'West Bengal',
        20 => 'Jharkhand',
        21 => 'Odisha',
        22 => 'Chhattisgarh',
        23 => 'Madhya Pradesh',
        24 => 'Gujarat',
        25 => 'Daman & Diu',
        26 => 'Dadra & Nagar Haveli',
        27 => 'Maharashtra',
        28 => 'Andhra Pradesh',
        29 => 'Karnataka',
        30 => 'Goa',
        31 => 'Lakshadweep',
        32 => 'Kerala',
        33 => 'Tamil Nadu',
        34 => 'Puducherry',
        35 => 'Andaman & Nicobar Islands',
        36 => 'Telangana',
        37 => 'Andhra Pradesh (New)',
    ];

    private array $entityTypes = [
        'C' => 'Company',
        'P' => 'Individual/Proprietorship',
        'F' => 'Firm/LLP',
        'A' => 'Association of Persons',
        'T' => 'Trust',
        'B' => 'Body of Individuals',
        'L' => 'Local Authority',
        'J' => 'Artificial Juridical Person',
        'G' => 'Government',
        'H' => 'HUF',
        'N' => 'Foreign Company',
        'Q' => 'Government Department ID',
    ];

    private array $hsnMasterData;

    public function __construct()
    {
        $this->hsnMasterData = $this->loadHsnMasterData();
    }

    public function validateGstin(string $gstin): array
    {
        $gstin = strtoupper(trim($gstin));

        if (strlen($gstin) !== 15) {
            return ['valid' => false, 'message' => 'GSTIN must be exactly 15 characters', 'state_code' => 0, 'state_name' => '', 'pan' => '', 'entity_type' => ''];
        }

        if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/', $gstin)) {
            return ['valid' => false, 'message' => 'GSTIN format is invalid', 'state_code' => 0, 'state_name' => '', 'pan' => '', 'entity_type' => ''];
        }

        $stateCode = (int) substr($gstin, 0, 2);
        if ($stateCode < 1 || $stateCode > 37) {
            return ['valid' => false, 'message' => 'Invalid state code in GSTIN', 'state_code' => $stateCode, 'state_name' => '', 'pan' => '', 'entity_type' => ''];
        }

        $checksumValid = $this->validateLuhnMod36($gstin);
        $pan = substr($gstin, 2, 10);
        $entityChar = substr($gstin, 5, 1);
        $stateName = $this->stateCodes[$stateCode] ?? '';
        $entityType = $this->entityTypes[$entityChar] ?? 'Unknown';

        if (!$checksumValid) {
            return ['valid' => false, 'message' => 'GSTIN checksum validation failed', 'state_code' => $stateCode, 'state_name' => $stateName, 'pan' => $pan, 'entity_type' => $entityType];
        }

        return ['valid' => true, 'message' => 'Valid GSTIN', 'state_code' => $stateCode, 'state_name' => $stateName, 'pan' => $pan, 'entity_type' => $entityType];
    }

    private function validateLuhnMod36(string $gstin): bool
    {
        $chars = self::CHARS;
        $mod = strlen($chars);
        $input = substr($gstin, 0, 14);
        $expectedCheckChar = $gstin[14];

        $factor = 2;
        $sum = 0;

        for ($i = strlen($input) - 1; $i >= 0; $i--) {
            $codePoint = strpos($chars, $input[$i]);
            if ($codePoint === false) {
                return false;
            }

            $addend = $factor * $codePoint;
            $factor = ($factor == 2) ? 1 : 2;
            $addend = intdiv($addend, $mod) + ($addend % $mod);
            $sum += $addend;
        }

        $remainder = $sum % $mod;
        $checkCodePoint = ($mod - $remainder) % $mod;
        $computedCheckChar = $chars[$checkCodePoint];

        return $computedCheckChar === $expectedCheckChar;
    }

    public function validateHsn(string $hsnCode): array
    {
        $hsnCode = trim($hsnCode);

        if (empty($hsnCode)) {
            return ['valid' => false, 'message' => 'HSN code cannot be empty', 'code' => '', 'description' => '', 'gst_rate' => 0.0, 'type' => ''];
        }

        if (!preg_match('/^[0-9]{2,8}$/', $hsnCode)) {
            return ['valid' => false, 'message' => 'HSN code must be 2-8 digits', 'code' => $hsnCode, 'description' => '', 'gst_rate' => 0.0, 'type' => ''];
        }

        foreach ($this->hsnMasterData as $entry) {
            if ($entry['code'] === $hsnCode) {
                return ['valid' => true, 'message' => 'Valid HSN code', 'code' => $entry['code'], 'description' => $entry['description'], 'gst_rate' => $entry['gst_rate'], 'type' => $entry['type']];
            }
        }

        foreach ($this->hsnMasterData as $entry) {
            if (str_starts_with($entry['code'], $hsnCode) || str_starts_with($hsnCode, $entry['code'])) {
                return ['valid' => true, 'message' => 'HSN code matched via prefix', 'code' => $entry['code'], 'description' => $entry['description'], 'gst_rate' => $entry['gst_rate'], 'type' => $entry['type']];
            }
        }

        return ['valid' => false, 'message' => 'HSN code not found in master data', 'code' => $hsnCode, 'description' => '', 'gst_rate' => 0.0, 'type' => ''];
    }

    public function searchHsn(string $query, int $limit = 20): array
    {
        $query = strtolower(trim($query));
        if (empty($query)) {
            return [];
        }

        $results = [];
        foreach ($this->hsnMasterData as $entry) {
            if (str_contains(strtolower($entry['code']), $query) || str_contains(strtolower($entry['description']), $query) || str_contains(strtolower($entry['type']), $query)) {
                $results[] = $entry;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        return $results;
    }

    public function lookupGstinOnline(string $gstin): array
    {
        $gstin = strtoupper(trim($gstin));

        $validation = $this->validateGstin($gstin);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message'], 'gstin' => $gstin, 'business_name' => '', 'state' => '', 'status' => ''];
        }

        try {
            $response = Http::timeout(10)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json',
            ])->get("https://ifsc.razorpay.com/gst/{$gstin}");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => 'GSTIN found',
                    'gstin' => $gstin,
                    'business_name' => $data['legal_name'] ?? $data['trade_name'] ?? '',
                    'trade_name' => $data['trade_name'] ?? '',
                    'legal_name' => $data['legal_name'] ?? '',
                    'state' => $data['state'] ?? $validation['state_name'],
                    'status' => $data['status'] ?? '',
                    'registration_date' => $data['registration_date'] ?? '',
                    'business_type' => $data['business_type'] ?? '',
                    'address' => $data['address'] ?? '',
                ];
            }

            return ['success' => false, 'message' => 'GSTIN not found online (HTTP ' . $response->status() . ')', 'gstin' => $gstin, 'business_name' => '', 'state' => $validation['state_name'], 'status' => ''];
        } catch (\Exception $e) {
            Log::warning('GSTIN online lookup failed', ['gstin' => $gstin, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Online lookup failed: ' . $e->getMessage(), 'gstin' => $gstin, 'business_name' => '', 'state' => $validation['state_name'], 'status' => ''];
        }
    }

    public function calculateGst(float $amount, float $rate, string $type = 'intra'): array
    {
        $totalGst = round($amount * $rate / 100, 2);

        if ($type === 'inter') {
            return [
                'base_amount' => round($amount, 2),
                'gst_rate' => $rate,
                'type' => 'inter',
                'cgst_rate' => 0.0,
                'cgst_amount' => 0.0,
                'sgst_rate' => 0.0,
                'sgst_amount' => 0.0,
                'igst_rate' => $rate,
                'igst_amount' => $totalGst,
                'total_gst' => $totalGst,
                'total_amount' => round($amount + $totalGst, 2),
            ];
        }

        $halfRate = $rate / 2;
        $cgst = round($amount * $halfRate / 100, 2);
        $sgst = round($amount * $halfRate / 100, 2);

        return [
            'base_amount' => round($amount, 2),
            'gst_rate' => $rate,
            'type' => 'intra',
            'cgst_rate' => $halfRate,
            'cgst_amount' => $cgst,
            'sgst_rate' => $halfRate,
            'sgst_amount' => $sgst,
            'igst_rate' => 0.0,
            'igst_amount' => 0.0,
            'total_gst' => round($cgst + $sgst, 2),
            'total_amount' => round($amount + $cgst + $sgst, 2),
        ];
    }

    public function getStateName(int $code): string
    {
        return $this->stateCodes[$code] ?? '';
    }

    public function getAllStates(): array
    {
        $states = [];
        foreach ($this->stateCodes as $code => $name) {
            $states[] = ['code' => $code, 'name' => $name];
        }
        return $states;
    }

    private function loadHsnMasterData(): array
    {
        return [

            ['code'=>'1001','description'=>'Wheat and meslin','gst_rate'=>0.0,'type'=>'Grocery'],
            ['code'=>'1006','description'=>'Rice','gst_rate'=>0.0,'type'=>'Grocery'],
            ['code'=>'1003','description'=>'Barley','gst_rate'=>0.0,'type'=>'Grocery'],
            ['code'=>'1005','description'=>'Maize (corn)','gst_rate'=>0.0,'type'=>'Grocery'],
            ['code'=>'1007','description'=>'Grain sorghum (Jowar)','gst_rate'=>0.0,'type'=>'Grocery'],
            ['code'=>'1008','description'=>'Buckwheat, millet and other cereals (Ragi, Bajra)','gst_rate'=>0.0,'type'=>'Grocery'],
            ['code'=>'0713','description'=>'Dried leguminous vegetables (Pulses/Dal)','gst_rate'=>0.0,'type'=>'Grocery'],
            ['code'=>'071320','description'=>'Chickpeas (Chana)','gst_rate'=>0.0,'type'=>'Grocery'],
            ['code'=>'071331','description'=>'Moong dal (Beans)','gst_rate'=>0.0,'type'=>'Grocery'],
            ['code'=>'071340','description'=>'Masoor dal (Lentils)','gst_rate'=>0.0,'type'=>'Grocery'],
            ['code'=>'071350','description'=>'Broad beans and horse beans','gst_rate'=>0.0,'type'=>'Grocery'],
            ['code'=>'1101','description'=>'Wheat or meslin flour (Atta)','gst_rate'=>0.0,'type'=>'Grocery'],
            ['code'=>'1102','description'=>'Cereal flours (Besan, Rice flour)','gst_rate'=>0.0,'type'=>'Grocery'],
            ['code'=>'1103','description'=>'Cereal groats, meal and pellets (Suji, Dalia)','gst_rate'=>0.0,'type'=>'Grocery'],
            ['code'=>'1104','description'=>'Cereal grains rolled/flaked (Poha, Murmura)','gst_rate'=>0.0,'type'=>'Grocery'],
            ['code'=>'1701','description'=>'Cane or beet sugar (Sugar)','gst_rate'=>5.0,'type'=>'Grocery'],
            ['code'=>'170199','description'=>'Refined sugar','gst_rate'=>5.0,'type'=>'Grocery'],
            ['code'=>'1702','description'=>'Jaggery (Gur)','gst_rate'=>0.0,'type'=>'Grocery'],
            ['code'=>'0801','description'=>'Coconuts, Brazil nuts, cashew nuts','gst_rate'=>5.0,'type'=>'Grocery'],
            ['code'=>'0802','description'=>'Other nuts (Almonds, Walnuts, Pistachios)','gst_rate'=>5.0,'type'=>'Grocery'],
            ['code'=>'0401','description'=>'Milk and cream, not concentrated','gst_rate'=>0.0,'type'=>'Dairy'],
            ['code'=>'0402','description'=>'Milk and cream concentrated (Milk powder)','gst_rate'=>5.0,'type'=>'Dairy'],
            ['code'=>'0403','description'=>'Buttermilk, curd, yogurt','gst_rate'=>0.0,'type'=>'Dairy'],
            ['code'=>'040310','description'=>'Yogurt / Dahi','gst_rate'=>0.0,'type'=>'Dairy'],
            ['code'=>'0405','description'=>'Butter and ghee','gst_rate'=>12.0,'type'=>'Dairy'],
            ['code'=>'040510','description'=>'Butter','gst_rate'=>12.0,'type'=>'Dairy'],
            ['code'=>'040520','description'=>'Ghee (dairy spreads)','gst_rate'=>12.0,'type'=>'Dairy'],
            ['code'=>'0406','description'=>'Cheese and paneer','gst_rate'=>12.0,'type'=>'Dairy'],
            ['code'=>'1507','description'=>'Soyabean oil','gst_rate'=>5.0,'type'=>'Edible Oil'],
            ['code'=>'1508','description'=>'Groundnut oil','gst_rate'=>5.0,'type'=>'Edible Oil'],
            ['code'=>'1509','description'=>'Olive oil','gst_rate'=>5.0,'type'=>'Edible Oil'],
            ['code'=>'1511','description'=>'Palm oil','gst_rate'=>5.0,'type'=>'Edible Oil'],
            ['code'=>'1512','description'=>'Sunflower oil, safflower oil','gst_rate'=>5.0,'type'=>'Edible Oil'],
            ['code'=>'1513','description'=>'Coconut oil','gst_rate'=>5.0,'type'=>'Edible Oil'],
            ['code'=>'1514','description'=>'Mustard oil (Sarson ka tel)','gst_rate'=>5.0,'type'=>'Edible Oil'],
            ['code'=>'1515','description'=>'Other vegetable oils (Til oil, Rice bran oil)','gst_rate'=>5.0,'type'=>'Edible Oil'],
            ['code'=>'1517','description'=>'Vanaspati / Margarine','gst_rate'=>12.0,'type'=>'Edible Oil'],
            ['code'=>'0904','description'=>'Pepper (Kali mirch)','gst_rate'=>5.0,'type'=>'Spices'],
            ['code'=>'0905','description'=>'Vanilla','gst_rate'=>5.0,'type'=>'Spices'],
            ['code'=>'0906','description'=>'Cinnamon (Dalchini)','gst_rate'=>5.0,'type'=>'Spices'],
            ['code'=>'0907','description'=>'Cloves (Laung)','gst_rate'=>5.0,'type'=>'Spices'],
            ['code'=>'0908','description'=>'Nutmeg, mace, cardamom (Elaichi)','gst_rate'=>5.0,'type'=>'Spices'],
            ['code'=>'0909','description'=>'Cumin, Fennel, Coriander seeds (Jeera, Saunf, Dhaniya)','gst_rate'=>5.0,'type'=>'Spices'],
            ['code'=>'0910','description'=>'Ginger, turmeric (Adrak, Haldi)','gst_rate'=>5.0,'type'=>'Spices'],
            ['code'=>'091030','description'=>'Turmeric / Haldi','gst_rate'=>5.0,'type'=>'Spices'],
            ['code'=>'0903','description'=>'Tea (Chai)','gst_rate'=>5.0,'type'=>'Beverages'],
            ['code'=>'0901','description'=>'Coffee','gst_rate'=>5.0,'type'=>'Beverages'],
            ['code'=>'2201','description'=>'Mineral water and aerated water','gst_rate'=>18.0,'type'=>'Beverages'],
            ['code'=>'2202','description'=>'Sweetened/flavoured water, soft drinks','gst_rate'=>28.0,'type'=>'Beverages'],
            ['code'=>'220210','description'=>'Aerated drinks / Carbonated beverages','gst_rate'=>28.0,'type'=>'Beverages'],
            ['code'=>'2009','description'=>'Fruit juices (packaged)','gst_rate'=>12.0,'type'=>'Beverages'],

            ['code'=>'1905','description'=>'Bread, pastry, cakes, biscuits','gst_rate'=>18.0,'type'=>'Packaged Food'],
            ['code'=>'190531','description'=>'Sweet biscuits','gst_rate'=>18.0,'type'=>'Packaged Food'],
            ['code'=>'190520','description'=>'Bread (not branded)','gst_rate'=>0.0,'type'=>'Packaged Food'],
            ['code'=>'190530','description'=>'Rusks, toasted bread','gst_rate'=>5.0,'type'=>'Packaged Food'],
            ['code'=>'190540','description'=>'Namkeen / Snacks (branded)','gst_rate'=>12.0,'type'=>'Packaged Food'],
            ['code'=>'1704','description'=>'Sugar confectionery (not chocolate)','gst_rate'=>18.0,'type'=>'Packaged Food'],
            ['code'=>'1806','description'=>'Chocolate and cocoa preparations','gst_rate'=>18.0,'type'=>'Packaged Food'],
            ['code'=>'2104','description'=>'Soups, broths, instant food preparations','gst_rate'=>18.0,'type'=>'Packaged Food'],
            ['code'=>'2106','description'=>'Food preparations (Papad, Namkeen branded)','gst_rate'=>12.0,'type'=>'Packaged Food'],
            ['code'=>'1902','description'=>'Pasta, noodles (Maggi, Instant noodles)','gst_rate'=>12.0,'type'=>'Packaged Food'],
            ['code'=>'1904','description'=>'Prepared cereals (Cornflakes, Muesli)','gst_rate'=>18.0,'type'=>'Packaged Food'],
            ['code'=>'3301','description'=>'Essential oils','gst_rate'=>18.0,'type'=>'FMCG'],
            ['code'=>'3303','description'=>'Perfumes and toilet waters','gst_rate'=>28.0,'type'=>'FMCG'],
            ['code'=>'3304','description'=>'Beauty/makeup preparations, skin care','gst_rate'=>28.0,'type'=>'FMCG'],
            ['code'=>'3305','description'=>'Hair care preparations (Shampoo, Oil)','gst_rate'=>18.0,'type'=>'FMCG'],
            ['code'=>'330510','description'=>'Shampoo','gst_rate'=>18.0,'type'=>'FMCG'],
            ['code'=>'3306','description'=>'Oral hygiene preparations (Toothpaste)','gst_rate'=>18.0,'type'=>'FMCG'],
            ['code'=>'3307','description'=>'Deodorants, bath preparations','gst_rate'=>28.0,'type'=>'FMCG'],
            ['code'=>'3401','description'=>'Soap, organic surface-active preparations','gst_rate'=>18.0,'type'=>'FMCG'],
            ['code'=>'340111','description'=>'Toilet soap / Bathing bar','gst_rate'=>18.0,'type'=>'FMCG'],
            ['code'=>'340120','description'=>'Washing soap / Detergent bar','gst_rate'=>18.0,'type'=>'FMCG'],
            ['code'=>'3402','description'=>'Detergents and washing preparations','gst_rate'=>18.0,'type'=>'FMCG'],
            ['code'=>'3405','description'=>'Polishes and creams (shoe polish)','gst_rate'=>18.0,'type'=>'FMCG'],
            ['code'=>'3406','description'=>'Candles and tapers','gst_rate'=>12.0,'type'=>'FMCG'],
            ['code'=>'3605','description'=>'Matches (Safety matches)','gst_rate'=>5.0,'type'=>'FMCG'],
            ['code'=>'4818','description'=>'Toilet paper, tissues, napkins, diapers','gst_rate'=>18.0,'type'=>'FMCG'],
            ['code'=>'481810','description'=>'Toilet paper / Tissue paper','gst_rate'=>18.0,'type'=>'FMCG'],
            ['code'=>'481840','description'=>'Sanitary napkins, diapers','gst_rate'=>12.0,'type'=>'FMCG'],
            ['code'=>'3808','description'=>'Insecticides, fungicides, herbicides','gst_rate'=>18.0,'type'=>'FMCG'],
            ['code'=>'9603','description'=>'Brooms, brushes, mops','gst_rate'=>12.0,'type'=>'FMCG'],
            ['code'=>'0701','description'=>'Potatoes (Aloo)','gst_rate'=>0.0,'type'=>'Vegetables'],
            ['code'=>'0702','description'=>'Tomatoes (Tamatar)','gst_rate'=>0.0,'type'=>'Vegetables'],
            ['code'=>'0703','description'=>'Onions, garlic, leeks (Pyaz, Lehsun)','gst_rate'=>0.0,'type'=>'Vegetables'],
            ['code'=>'0706','description'=>'Carrots, turnips, beetroot, radish','gst_rate'=>0.0,'type'=>'Vegetables'],
            ['code'=>'0707','description'=>'Cucumbers and gherkins','gst_rate'=>0.0,'type'=>'Vegetables'],
            ['code'=>'0709','description'=>'Other vegetables (Bhindi, Brinjal, Capsicum)','gst_rate'=>0.0,'type'=>'Vegetables'],
            ['code'=>'0710','description'=>'Frozen vegetables','gst_rate'=>0.0,'type'=>'Vegetables'],
            ['code'=>'0712','description'=>'Dried vegetables (dried onion, garlic)','gst_rate'=>0.0,'type'=>'Vegetables'],
            ['code'=>'0803','description'=>'Bananas','gst_rate'=>0.0,'type'=>'Fruits'],
            ['code'=>'0804','description'=>'Dates, figs, pineapples, mangoes','gst_rate'=>0.0,'type'=>'Fruits'],
            ['code'=>'0805','description'=>'Citrus fruits (Oranges, Lemons)','gst_rate'=>0.0,'type'=>'Fruits'],
            ['code'=>'0806','description'=>'Grapes','gst_rate'=>0.0,'type'=>'Fruits'],
            ['code'=>'0807','description'=>'Melons, watermelons, papaya','gst_rate'=>0.0,'type'=>'Fruits'],
            ['code'=>'0808','description'=>'Apples, pears','gst_rate'=>0.0,'type'=>'Fruits'],
            ['code'=>'0810','description'=>'Other fruits (Litchi, Pomegranate, Guava)','gst_rate'=>0.0,'type'=>'Fruits'],
            ['code'=>'0201','description'=>'Meat of bovine animals, fresh or chilled','gst_rate'=>0.0,'type'=>'Meat & Fish'],
            ['code'=>'0204','description'=>'Meat of sheep or goats (Mutton)','gst_rate'=>0.0,'type'=>'Meat & Fish'],
            ['code'=>'0207','description'=>'Poultry meat (Chicken)','gst_rate'=>0.0,'type'=>'Meat & Fish'],
            ['code'=>'0302','description'=>'Fish, fresh or chilled','gst_rate'=>0.0,'type'=>'Meat & Fish'],
            ['code'=>'0306','description'=>'Crustaceans (Prawns, Crabs)','gst_rate'=>5.0,'type'=>'Meat & Fish'],
            ['code'=>'0407','description'=>'Eggs (Anda)','gst_rate'=>0.0,'type'=>'Meat & Fish'],

            ['code'=>'2001','description'=>'Pickles (Achar) - vegetables in vinegar','gst_rate'=>12.0,'type'=>'Processed Food'],
            ['code'=>'2002','description'=>'Tomato ketchup / sauce','gst_rate'=>12.0,'type'=>'Processed Food'],
            ['code'=>'2005','description'=>'Other prepared vegetables (Frozen, preserved)','gst_rate'=>12.0,'type'=>'Processed Food'],
            ['code'=>'2007','description'=>'Jams, fruit jellies, marmalades','gst_rate'=>12.0,'type'=>'Processed Food'],
            ['code'=>'2008','description'=>'Fruits and nuts preserved','gst_rate'=>12.0,'type'=>'Processed Food'],
            ['code'=>'2103','description'=>'Sauces, mixed condiments, mustard','gst_rate'=>12.0,'type'=>'Processed Food'],
            ['code'=>'210310','description'=>'Soy sauce','gst_rate'=>12.0,'type'=>'Processed Food'],
            ['code'=>'210320','description'=>'Tomato ketchup and sauces','gst_rate'=>12.0,'type'=>'Processed Food'],
            ['code'=>'2105','description'=>'Ice cream','gst_rate'=>18.0,'type'=>'Processed Food'],
            ['code'=>'8471','description'=>'Computers, laptops, tablets','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'847130','description'=>'Laptops / Portable computers','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'8517','description'=>'Telephones, smartphones','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'851712','description'=>'Mobile phones / Smartphones','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'8518','description'=>'Microphones, loudspeakers, headphones','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'8519','description'=>'Sound recording/reproducing apparatus','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'8521','description'=>'Video recording apparatus','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'8523','description'=>'Storage media (USB, SD cards, HDD)','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'8528','description'=>'Monitors, projectors, TVs','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'852871','description'=>'Television sets (up to 32 inch)','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'852872','description'=>'Television sets (above 32 inch)','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'8443','description'=>'Printers, scanners, fax machines','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'8473','description'=>'Computer parts and accessories','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'8504','description'=>'Electrical transformers, power supplies, chargers','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'8506','description'=>'Primary batteries (dry cells)','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'8507','description'=>'Electric accumulators (batteries, Li-ion)','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'8544','description'=>'Insulated wire, cables, connectors','gst_rate'=>18.0,'type'=>'Electronics'],
            ['code'=>'8414','description'=>'Air conditioning machines, fans','gst_rate'=>28.0,'type'=>'Electrical'],
            ['code'=>'8415','description'=>'Air conditioners (AC)','gst_rate'=>28.0,'type'=>'Electrical'],
            ['code'=>'8418','description'=>'Refrigerators, freezers','gst_rate'=>28.0,'type'=>'Electrical'],
            ['code'=>'8422','description'=>'Dish washing machines','gst_rate'=>28.0,'type'=>'Electrical'],
            ['code'=>'8450','description'=>'Washing machines','gst_rate'=>28.0,'type'=>'Electrical'],
            ['code'=>'8451','description'=>'Drying machines, ironing machines','gst_rate'=>28.0,'type'=>'Electrical'],
            ['code'=>'8508','description'=>'Vacuum cleaners','gst_rate'=>28.0,'type'=>'Electrical'],
            ['code'=>'8509','description'=>'Electro-mechanical domestic appliances (Mixer, Grinder)','gst_rate'=>18.0,'type'=>'Electrical'],
            ['code'=>'8510','description'=>'Electric shavers, hair clippers','gst_rate'=>18.0,'type'=>'Electrical'],
            ['code'=>'8516','description'=>'Electric water heaters, hair dryers, irons','gst_rate'=>18.0,'type'=>'Electrical'],
            ['code'=>'851610','description'=>'Electric water heaters (Geyser)','gst_rate'=>18.0,'type'=>'Electrical'],
            ['code'=>'851640','description'=>'Electric iron','gst_rate'=>18.0,'type'=>'Electrical'],
            ['code'=>'8539','description'=>'Electric lamps, LED bulbs, tube lights','gst_rate'=>18.0,'type'=>'Electrical'],
            ['code'=>'5208','description'=>'Woven cotton fabrics','gst_rate'=>5.0,'type'=>'Textiles'],
            ['code'=>'5209','description'=>'Woven cotton fabrics (heavy weight)','gst_rate'=>5.0,'type'=>'Textiles'],
            ['code'=>'5407','description'=>'Woven fabrics of synthetic filament yarn','gst_rate'=>5.0,'type'=>'Textiles'],
            ['code'=>'5408','description'=>'Woven fabrics of artificial filament yarn','gst_rate'=>5.0,'type'=>'Textiles'],
            ['code'=>'5513','description'=>'Woven fabrics of synthetic staple fibres','gst_rate'=>5.0,'type'=>'Textiles'],
            ['code'=>'6101','description'=>'Overcoats, jackets (knitted)','gst_rate'=>12.0,'type'=>'Textiles'],
            ['code'=>'6103','description'=>'Suits, trousers (knitted)','gst_rate'=>12.0,'type'=>'Textiles'],
            ['code'=>'6104','description'=>'Women suits, dresses, skirts (knitted)','gst_rate'=>12.0,'type'=>'Textiles'],
            ['code'=>'6105','description'=>'Shirts (knitted)','gst_rate'=>12.0,'type'=>'Textiles'],
            ['code'=>'6106','description'=>'Women blouses, shirts (knitted)','gst_rate'=>12.0,'type'=>'Textiles'],
            ['code'=>'6109','description'=>'T-shirts, singlets, vests (knitted)','gst_rate'=>12.0,'type'=>'Textiles'],
            ['code'=>'6110','description'=>'Jerseys, pullovers, sweatshirts','gst_rate'=>12.0,'type'=>'Textiles'],
            ['code'=>'6203','description'=>'Suits, trousers (not knitted)','gst_rate'=>12.0,'type'=>'Textiles'],
            ['code'=>'6204','description'=>'Women suits, dresses (not knitted)','gst_rate'=>12.0,'type'=>'Textiles'],
            ['code'=>'6205','description'=>'Shirts (not knitted)','gst_rate'=>12.0,'type'=>'Textiles'],
            ['code'=>'6206','description'=>'Women blouses (not knitted)','gst_rate'=>12.0,'type'=>'Textiles'],
            ['code'=>'6211','description'=>'Track suits, ski suits, swimwear','gst_rate'=>12.0,'type'=>'Textiles'],
            ['code'=>'6301','description'=>'Blankets and travelling rugs','gst_rate'=>12.0,'type'=>'Textiles'],
            ['code'=>'6302','description'=>'Bed linen, table linen, toilet linen','gst_rate'=>12.0,'type'=>'Textiles'],
            ['code'=>'6305','description'=>'Sacks and bags for packing','gst_rate'=>12.0,'type'=>'Textiles'],
            ['code'=>'6401','description'=>'Waterproof footwear (rubber/plastics)','gst_rate'=>18.0,'type'=>'Footwear'],
            ['code'=>'6402','description'=>'Footwear with outer soles of rubber/plastics','gst_rate'=>18.0,'type'=>'Footwear'],
            ['code'=>'6403','description'=>'Footwear with leather upper (Shoes, Sandals)','gst_rate'=>18.0,'type'=>'Footwear'],
            ['code'=>'6404','description'=>'Footwear with textile uppers (Sports shoes)','gst_rate'=>18.0,'type'=>'Footwear'],
            ['code'=>'6405','description'=>'Other footwear (Chappal below Rs 1000)','gst_rate'=>5.0,'type'=>'Footwear'],

            ['code'=>'4802','description'=>'Uncoated paper for writing/printing','gst_rate'=>12.0,'type'=>'Stationery'],
            ['code'=>'4810','description'=>'Coated paper (Art paper)','gst_rate'=>12.0,'type'=>'Stationery'],
            ['code'=>'4817','description'=>'Envelopes, letter cards','gst_rate'=>18.0,'type'=>'Stationery'],
            ['code'=>'4820','description'=>'Registers, notebooks, diaries','gst_rate'=>18.0,'type'=>'Stationery'],
            ['code'=>'8214','description'=>'Paper knives, pencil sharpeners','gst_rate'=>18.0,'type'=>'Stationery'],
            ['code'=>'9608','description'=>'Ball point pens, felt tip pens, markers','gst_rate'=>18.0,'type'=>'Stationery'],
            ['code'=>'9609','description'=>'Pencils, crayons, chalks','gst_rate'=>12.0,'type'=>'Stationery'],
            ['code'=>'9612','description'=>'Typewriter/printer ribbons, ink pads','gst_rate'=>18.0,'type'=>'Stationery'],
            ['code'=>'3923','description'=>'Plastic containers, boxes, bags','gst_rate'=>18.0,'type'=>'Plastics'],
            ['code'=>'3924','description'=>'Plastic household articles (Buckets, Mugs)','gst_rate'=>18.0,'type'=>'Plastics'],
            ['code'=>'3926','description'=>'Other articles of plastics','gst_rate'=>18.0,'type'=>'Plastics'],
            ['code'=>'4819','description'=>'Cartons, boxes of paper/paperboard','gst_rate'=>18.0,'type'=>'Packaging'],
            ['code'=>'7323','description'=>'Table/kitchen articles of iron/steel (Utensils)','gst_rate'=>18.0,'type'=>'Hardware'],
            ['code'=>'7615','description'=>'Table/kitchen articles of aluminium (Pressure cooker)','gst_rate'=>18.0,'type'=>'Hardware'],
            ['code'=>'8210','description'=>'Hand-operated kitchen appliances','gst_rate'=>18.0,'type'=>'Hardware'],
            ['code'=>'8211','description'=>'Knives (kitchen, table)','gst_rate'=>18.0,'type'=>'Hardware'],
            ['code'=>'8301','description'=>'Padlocks, locks, keys','gst_rate'=>18.0,'type'=>'Hardware'],
            ['code'=>'7318','description'=>'Screws, bolts, nuts, washers','gst_rate'=>18.0,'type'=>'Hardware'],
            ['code'=>'8205','description'=>'Hand tools (Hammers, Pliers, Screwdrivers)','gst_rate'=>18.0,'type'=>'Hardware'],
            ['code'=>'2401','description'=>'Unmanufactured tobacco','gst_rate'=>28.0,'type'=>'Tobacco'],
            ['code'=>'2402','description'=>'Cigars, cigarettes, tobacco products','gst_rate'=>28.0,'type'=>'Tobacco'],
            ['code'=>'240220','description'=>'Cigarettes','gst_rate'=>28.0,'type'=>'Tobacco'],
            ['code'=>'2403','description'=>'Other manufactured tobacco (Gutka, Pan masala)','gst_rate'=>28.0,'type'=>'Tobacco'],
            ['code'=>'3003','description'=>'Medicaments (not in dosage form)','gst_rate'=>12.0,'type'=>'Healthcare'],
            ['code'=>'3004','description'=>'Medicaments in measured doses (Medicines)','gst_rate'=>12.0,'type'=>'Healthcare'],
            ['code'=>'3005','description'=>'Bandages, first aid kits','gst_rate'=>18.0,'type'=>'Healthcare'],
            ['code'=>'3006','description'=>'Pharmaceutical preparations (Contraceptives)','gst_rate'=>12.0,'type'=>'Healthcare'],
            ['code'=>'9018','description'=>'Medical instruments and appliances','gst_rate'=>12.0,'type'=>'Healthcare'],
            ['code'=>'9019','description'=>'Mechano-therapy, massage apparatus','gst_rate'=>18.0,'type'=>'Healthcare'],
            ['code'=>'8703','description'=>'Motor cars (Passenger vehicles)','gst_rate'=>28.0,'type'=>'Automobile'],
            ['code'=>'8711','description'=>'Motorcycles, scooters','gst_rate'=>28.0,'type'=>'Automobile'],
            ['code'=>'8712','description'=>'Bicycles','gst_rate'=>12.0,'type'=>'Automobile'],
            ['code'=>'4011','description'=>'New pneumatic tyres of rubber','gst_rate'=>28.0,'type'=>'Automobile'],
            ['code'=>'8708','description'=>'Parts and accessories of motor vehicles','gst_rate'=>28.0,'type'=>'Automobile'],
            ['code'=>'9954','description'=>'Construction services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9961','description'=>'Financial and related services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9962','description'=>'Insurance services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9963','description'=>'Real estate services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9964','description'=>'Rental/leasing services (without operator)','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9965','description'=>'Transport of goods services','gst_rate'=>5.0,'type'=>'Services'],
            ['code'=>'9966','description'=>'Transport of passengers services','gst_rate'=>5.0,'type'=>'Services'],
            ['code'=>'9967','description'=>'Supporting transport services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9968','description'=>'Postal and courier services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9971','description'=>'Telecommunications services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9972','description'=>'IT and ITES services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9973','description'=>'Licensing services for right to use IP','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9981','description'=>'Research and development services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9982','description'=>'Legal and accounting services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9983','description'=>'Management consulting services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9984','description'=>'Publishing, printing services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9985','description'=>'Education services','gst_rate'=>0.0,'type'=>'Services'],
            ['code'=>'9986','description'=>'Healthcare services','gst_rate'=>0.0,'type'=>'Services'],
            ['code'=>'9987','description'=>'Hotel/accommodation services','gst_rate'=>12.0,'type'=>'Services'],
            ['code'=>'9988','description'=>'Manufacturing services on physical inputs','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9989','description'=>'Other manufacturing services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9991','description'=>'Public administration services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9992','description'=>'Agricultural services','gst_rate'=>0.0,'type'=>'Services'],
            ['code'=>'9993','description'=>'Water supply, sewerage services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9994','description'=>'Food and beverage serving services (Restaurant)','gst_rate'=>5.0,'type'=>'Services'],
            ['code'=>'9995','description'=>'Recreation, cultural and sporting services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9996','description'=>'Personal care services (Salon, Spa)','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'9997','description'=>'Maintenance and repair services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'997212','description'=>'Software development services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'998599','description'=>'Other education services','gst_rate'=>18.0,'type'=>'Services'],
            ['code'=>'996311','description'=>'Room/accommodation services (Hotels)','gst_rate'=>12.0,'type'=>'Services'],
        ];
    }
}
