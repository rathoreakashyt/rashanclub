using System;
using System.Collections.Generic;
using System.Linq;

namespace RashanKiDukan.Services
{
    /// <summary>
    /// Embedded HSN/SAC master database for offline validation and auto-suggest.
    /// Covers common grocery, FMCG, retail, electronics, and service codes.
    /// Source: Government of India GST HSN database (2024 edition).
    /// </summary>
    public static class HsnMasterData
    {
        private static readonly List<HsnEntry> _entries = new()
        {
            // ═══ CHAPTER 01-05: ANIMAL PRODUCTS ═══
            new() { Code = "0401", Description = "Milk and cream, not concentrated", GstRate = 0 },
            new() { Code = "0402", Description = "Milk and cream, concentrated/sweetened", GstRate = 5 },
            new() { Code = "0403", Description = "Buttermilk, curd, yogurt", GstRate = 0 },
            new() { Code = "0406", Description = "Cheese and cottage cheese (paneer)", GstRate = 5 },
            new() { Code = "0407", Description = "Eggs in shell", GstRate = 0 },
            new() { Code = "0409", Description = "Natural honey", GstRate = 0 },

            // ═══ CHAPTER 06-14: VEGETABLES, FRUITS, CEREALS ═══
            new() { Code = "0701", Description = "Potatoes, fresh or chilled", GstRate = 0 },
            new() { Code = "0702", Description = "Tomatoes, fresh or chilled", GstRate = 0 },
            new() { Code = "0703", Description = "Onions, garlic, leeks", GstRate = 0 },
            new() { Code = "0713", Description = "Dried leguminous vegetables (dal/pulses)", GstRate = 0 },
            new() { Code = "0801", Description = "Coconuts, cashew nuts", GstRate = 5 },
            new() { Code = "0802", Description = "Almonds, walnuts, pistachios", GstRate = 5 },
            new() { Code = "0803", Description = "Bananas", GstRate = 0 },
            new() { Code = "0805", Description = "Citrus fruit (orange, lemon)", GstRate = 0 },
            new() { Code = "0806", Description = "Grapes", GstRate = 0 },
            new() { Code = "0808", Description = "Apples, pears", GstRate = 0 },
            new() { Code = "1001", Description = "Wheat and meslin", GstRate = 0 },
            new() { Code = "1005", Description = "Maize (corn)", GstRate = 0 },
            new() { Code = "1006", Description = "Rice", GstRate = 5 },
            new() { Code = "100630", Description = "Rice, semi-milled or wholly milled", GstRate = 5 },
            new() { Code = "1101", Description = "Wheat or meslin flour (atta)", GstRate = 0 },
            new() { Code = "1102", Description = "Cereal flours (besan, maize flour)", GstRate = 0 },
            new() { Code = "1103", Description = "Cereal groats, meal, pellets (suji, dalia)", GstRate = 0 },
            new() { Code = "1104", Description = "Cereal grains (rolled oats, flakes)", GstRate = 5 },
            new() { Code = "1106", Description = "Flour of dried pulses", GstRate = 0 },
            new() { Code = "1201", Description = "Soya beans", GstRate = 0 },
            new() { Code = "1202", Description = "Ground-nuts (peanuts)", GstRate = 5 },
            new() { Code = "1207", Description = "Mustard seeds, sunflower seeds", GstRate = 0 },

            // ═══ CHAPTER 15: EDIBLE OILS ═══
            new() { Code = "1507", Description = "Soyabean oil", GstRate = 5 },
            new() { Code = "1508", Description = "Groundnut oil", GstRate = 5 },
            new() { Code = "1509", Description = "Olive oil", GstRate = 5 },
            new() { Code = "1510", Description = "Other olive oils", GstRate = 5 },
            new() { Code = "1511", Description = "Palm oil", GstRate = 5 },
            new() { Code = "1512", Description = "Sunflower/safflower oil", GstRate = 5 },
            new() { Code = "1513", Description = "Coconut oil", GstRate = 5 },
            new() { Code = "1514", Description = "Rapeseed/mustard oil", GstRate = 5 },
            new() { Code = "1515", Description = "Other vegetable fats/oils (rice bran oil)", GstRate = 5 },
            new() { Code = "1517", Description = "Vanaspati, margarine", GstRate = 5 },

            // ═══ CHAPTER 16-21: PREPARED FOODS ═══
            new() { Code = "1601", Description = "Sausages and meat preparations", GstRate = 12 },
            new() { Code = "1701", Description = "Cane/beet sugar (cheeni)", GstRate = 5 },
            new() { Code = "170199", Description = "Refined sugar", GstRate = 5 },
            new() { Code = "1702", Description = "Jaggery (gur), glucose", GstRate = 0 },
            new() { Code = "1704", Description = "Sugar confectionery (sweets, toffees)", GstRate = 18 },
            new() { Code = "1806", Description = "Chocolate and cocoa preparations", GstRate = 18 },
            new() { Code = "1901", Description = "Malt extract, food preparations of flour", GstRate = 18 },
            new() { Code = "1902", Description = "Pasta, noodles, instant noodles", GstRate = 12 },
            new() { Code = "1904", Description = "Cornflakes, puffed rice (murmura)", GstRate = 5 },
            new() { Code = "1905", Description = "Bread, biscuits, cakes, pastries", GstRate = 18 },
            new() { Code = "190531", Description = "Biscuits (sweet)", GstRate = 18 },
            new() { Code = "190540", Description = "Rusks, toasted bread", GstRate = 5 },
            new() { Code = "2001", Description = "Pickles (vegetables/fruits in vinegar)", GstRate = 12 },
            new() { Code = "2004", Description = "Frozen vegetables", GstRate = 12 },
            new() { Code = "2005", Description = "Prepared vegetables (not frozen)", GstRate = 12 },
            new() { Code = "2007", Description = "Jams, jellies, marmalades", GstRate = 12 },
            new() { Code = "2101", Description = "Coffee, tea extracts", GstRate = 18 },
            new() { Code = "2106", Description = "Food preparations (papad, namkeen)", GstRate = 5 },

            // ═══ CHAPTER 22: BEVERAGES ═══
            new() { Code = "2201", Description = "Mineral water, packaged drinking water", GstRate = 18 },
            new() { Code = "220110", Description = "Packaged drinking water (up to 20L)", GstRate = 18 },
            new() { Code = "2202", Description = "Soft drinks, flavoured water, energy drinks", GstRate = 28 },
            new() { Code = "2209", Description = "Vinegar", GstRate = 12 },

            // ═══ CHAPTER 23-24: RESIDUES, TOBACCO ═══
            new() { Code = "2302", Description = "Bran, sharps (chokar)", GstRate = 0 },
            new() { Code = "2401", Description = "Unmanufactured tobacco", GstRate = 28 },
            new() { Code = "2402", Description = "Cigars, cigarettes", GstRate = 28 },

            // ═══ CHAPTER 25-27: MINERALS, FUELS ═══
            new() { Code = "2501", Description = "Salt (namak)", GstRate = 0 },

            // ═══ CHAPTER 28-38: CHEMICALS ═══
            new() { Code = "3003", Description = "Medicaments (not in dosage form)", GstRate = 12 },
            new() { Code = "3004", Description = "Medicaments in dosage form", GstRate = 12 },
            new() { Code = "3006", Description = "Pharmaceutical goods (bandages etc.)", GstRate = 12 },
            new() { Code = "3301", Description = "Essential oils", GstRate = 18 },
            new() { Code = "3304", Description = "Beauty/makeup preparations", GstRate = 18 },
            new() { Code = "3305", Description = "Hair care preparations (shampoo)", GstRate = 18 },
            new() { Code = "3306", Description = "Oral hygiene (toothpaste)", GstRate = 18 },
            new() { Code = "3307", Description = "Deodorants, perfumes", GstRate = 18 },
            new() { Code = "3401", Description = "Soap, washing preparations (detergent)", GstRate = 18 },
            new() { Code = "3402", Description = "Surface-active agents (surf, liquid soap)", GstRate = 18 },

            // ═══ CHAPTER 39-40: PLASTICS, RUBBER ═══
            new() { Code = "3923", Description = "Plastic containers, boxes, bags", GstRate = 18 },
            new() { Code = "3924", Description = "Plastic household articles", GstRate = 18 },
            new() { Code = "4014", Description = "Rubber gloves", GstRate = 12 },
            new() { Code = "4015", Description = "Rubber articles of apparel", GstRate = 12 },

            // ═══ CHAPTER 48-49: PAPER ═══
            new() { Code = "4802", Description = "Paper and paperboard", GstRate = 12 },
            new() { Code = "4818", Description = "Toilet paper, tissues, napkins", GstRate = 18 },
            new() { Code = "4819", Description = "Cartons, boxes, paper bags", GstRate = 12 },
            new() { Code = "4820", Description = "Notebooks, registers, diaries", GstRate = 12 },
            new() { Code = "4901", Description = "Printed books, newspapers", GstRate = 0 },
            new() { Code = "4907", Description = "Postage stamps, cheque forms", GstRate = 12 },

            // ═══ CHAPTER 52-63: TEXTILES, GARMENTS ═══
            new() { Code = "5208", Description = "Cotton fabrics, woven", GstRate = 5 },
            new() { Code = "6101", Description = "Men's knitted overcoats, jackets", GstRate = 12 },
            new() { Code = "6109", Description = "T-shirts, vests, knitted", GstRate = 5 },
            new() { Code = "6203", Description = "Men's suits, trousers, shirts", GstRate = 12 },
            new() { Code = "6204", Description = "Women's suits, dresses, skirts", GstRate = 12 },
            new() { Code = "6301", Description = "Blankets and travelling rugs", GstRate = 12 },
            new() { Code = "6302", Description = "Bed linen, table linen, towels", GstRate = 12 },

            // ═══ CHAPTER 64-67: FOOTWEAR ═══
            new() { Code = "6401", Description = "Waterproof footwear (rubber/plastic)", GstRate = 12 },
            new() { Code = "6402", Description = "Footwear with rubber/plastic soles", GstRate = 12 },
            new() { Code = "6403", Description = "Footwear with leather uppers", GstRate = 18 },
            new() { Code = "6404", Description = "Footwear with textile uppers", GstRate = 12 },
            new() { Code = "6405", Description = "Other footwear (chappal, sandal)", GstRate = 12 },

            // ═══ CHAPTER 69-70: CERAMICS, GLASS ═══
            new() { Code = "6911", Description = "Tableware, kitchenware (ceramic)", GstRate = 12 },
            new() { Code = "6912", Description = "Ceramic household articles", GstRate = 12 },
            new() { Code = "7013", Description = "Glassware (drinking glasses, vases)", GstRate = 18 },

            // ═══ CHAPTER 73-76: IRON, STEEL, ALUMINIUM ═══
            new() { Code = "7310", Description = "Steel tanks, drums, cans", GstRate = 18 },
            new() { Code = "7323", Description = "Steel wool, kitchen utensils", GstRate = 18 },
            new() { Code = "7615", Description = "Aluminium utensils, kitchenware", GstRate = 12 },

            // ═══ CHAPTER 84-85: ELECTRICAL, ELECTRONICS ═══
            new() { Code = "8414", Description = "Fans, air pumps", GstRate = 18 },
            new() { Code = "8418", Description = "Refrigerators, freezers", GstRate = 18 },
            new() { Code = "8422", Description = "Dish washing machines", GstRate = 18 },
            new() { Code = "8443", Description = "Printers, copiers", GstRate = 18 },
            new() { Code = "8450", Description = "Washing machines", GstRate = 18 },
            new() { Code = "8471", Description = "Computers, laptops", GstRate = 18 },
            new() { Code = "8504", Description = "Electrical transformers, UPS", GstRate = 18 },
            new() { Code = "8506", Description = "Batteries (dry cells)", GstRate = 18 },
            new() { Code = "8507", Description = "Rechargeable batteries", GstRate = 18 },
            new() { Code = "8508", Description = "Vacuum cleaners", GstRate = 18 },
            new() { Code = "8509", Description = "Domestic electrical appliances (mixer, grinder)", GstRate = 18 },
            new() { Code = "8516", Description = "Electric heaters, iron, toaster", GstRate = 18 },
            new() { Code = "8517", Description = "Mobile phones, smartphones", GstRate = 18 },
            new() { Code = "851712", Description = "Smartphones", GstRate = 18 },
            new() { Code = "8518", Description = "Headphones, speakers, microphones", GstRate = 18 },
            new() { Code = "8521", Description = "Video recording apparatus", GstRate = 18 },
            new() { Code = "8523", Description = "Pen drives, memory cards, DVDs", GstRate = 18 },
            new() { Code = "8528", Description = "Television sets, monitors", GstRate = 18 },
            new() { Code = "8539", Description = "LED lamps, bulbs, tubes", GstRate = 12 },

            // ═══ CHAPTER 87: VEHICLES ═══
            new() { Code = "8711", Description = "Motorcycles, scooters", GstRate = 28 },
            new() { Code = "8712", Description = "Bicycles", GstRate = 12 },
            new() { Code = "8713", Description = "Invalid carriages, wheelchairs", GstRate = 5 },

            // ═══ CHAPTER 90-97: INSTRUMENTS, MISC ═══
            new() { Code = "9004", Description = "Spectacles, goggles", GstRate = 12 },
            new() { Code = "9018", Description = "Medical instruments", GstRate = 12 },
            new() { Code = "9021", Description = "Hearing aids", GstRate = 5 },
            new() { Code = "9401", Description = "Seats, chairs (not medical)", GstRate = 18 },
            new() { Code = "9403", Description = "Furniture (tables, shelves, cupboards)", GstRate = 18 },
            new() { Code = "9404", Description = "Mattresses, quilts, pillows", GstRate = 18 },
            new() { Code = "9405", Description = "Lamps, light fittings", GstRate = 18 },
            new() { Code = "9503", Description = "Toys, games", GstRate = 12 },
            new() { Code = "9608", Description = "Pens, pencils", GstRate = 18 },
            new() { Code = "9609", Description = "Pencils, crayons, chalk", GstRate = 12 },
            new() { Code = "9619", Description = "Sanitary napkins, diapers", GstRate = 12 },

            // ═══ SAC CODES (SERVICES) ═══
            new() { Code = "9954", Description = "Construction services", GstRate = 18 },
            new() { Code = "9961", Description = "Financial services", GstRate = 18 },
            new() { Code = "9962", Description = "Insurance services", GstRate = 18 },
            new() { Code = "9963", Description = "Accommodation services (hotel)", GstRate = 12 },
            new() { Code = "9964", Description = "Passenger transport services", GstRate = 5 },
            new() { Code = "9965", Description = "Goods transport services", GstRate = 5 },
            new() { Code = "9966", Description = "Rental services (vehicles)", GstRate = 18 },
            new() { Code = "9971", Description = "Telecommunication services", GstRate = 18 },
            new() { Code = "9972", Description = "Real estate services", GstRate = 18 },
            new() { Code = "9973", Description = "Leasing/rental without operator", GstRate = 18 },
            new() { Code = "9981", Description = "Government services", GstRate = 18 },
            new() { Code = "9982", Description = "Education services", GstRate = 0 },
            new() { Code = "9983", Description = "Healthcare services", GstRate = 0 },
            new() { Code = "9985", Description = "Support services (cleaning, security)", GstRate = 18 },
            new() { Code = "9986", Description = "IT and telecom services", GstRate = 18 },
            new() { Code = "9987", Description = "Maintenance and repair services", GstRate = 18 },
            new() { Code = "9988", Description = "Manufacturing on physical inputs", GstRate = 18 },
            new() { Code = "9991", Description = "Public administration services", GstRate = 0 },
            new() { Code = "9992", Description = "Sporting/recreational services", GstRate = 18 },
            new() { Code = "9993", Description = "Religious/political/community services", GstRate = 0 },
            new() { Code = "9994", Description = "Domestic services (cook, maid)", GstRate = 0 },
            new() { Code = "9995", Description = "Personal care services (salon, spa)", GstRate = 18 },
            new() { Code = "9996", Description = "Waste management services", GstRate = 12 },
            new() { Code = "9997", Description = "Other services n.e.c.", GstRate = 18 },
        };

        /// <summary>
        /// Lookup exact HSN code or best prefix match.
        /// </summary>
        public static HsnEntry? Lookup(string code)
        {
            if (string.IsNullOrEmpty(code)) return null;

            // Exact match first
            var exact = _entries.FirstOrDefault(e => e.Code == code);
            if (exact != null) return exact;

            // Try 6-digit prefix of 8-digit code
            if (code.Length == 8)
            {
                var six = _entries.FirstOrDefault(e => e.Code == code.Substring(0, 6));
                if (six != null) return six;
            }

            // Try 4-digit prefix
            if (code.Length >= 4)
            {
                var four = _entries.FirstOrDefault(e => e.Code == code.Substring(0, 4));
                if (four != null) return four;
            }

            return null;
        }

        /// <summary>
        /// Search by code prefix or description keyword.
        /// </summary>
        public static List<HsnEntry> Search(string query, int maxResults = 20)
        {
            if (string.IsNullOrWhiteSpace(query)) return new();

            query = query.Trim();

            // If query is all digits, search by code prefix
            if (query.All(char.IsDigit))
            {
                return _entries
                    .Where(e => e.Code.StartsWith(query))
                    .Take(maxResults)
                    .ToList();
            }

            // Otherwise search by description keyword
            var lower = query.ToLower();
            return _entries
                .Where(e => e.Description.ToLower().Contains(lower))
                .Take(maxResults)
                .ToList();
        }

        /// <summary>
        /// Get all entries (for export/reference).
        /// </summary>
        public static IReadOnlyList<HsnEntry> GetAll() => _entries;
    }
}
