namespace RashanKiDukan.Views
{
    public class VoucherSpec
    {
        public CrudSpec Header { get; set; } = new();
        public string DetailTable { get; set; } = "";
        public string HeaderFkCol { get; set; } = "";
        public string ItemCol { get; set; } = "item_id";
        public string QtyCol { get; set; } = "quantity";
        public string PriceCol { get; set; } = "unit_price";
        public string TotalCol { get; set; } = "total";
        public int StockEffect { get; set; } = 0; // -1 = reduce stock, +1 = add stock, 0 = none
        public bool UseDamageCols { get; set; }   // damage_details: damage_quantity / last_purchase_price / loss_amount
        public bool EnsureQtyColumn { get; set; } // quotation_details has no quantity column -> add locally
        public string ItemComboSql { get; set; } = "SELECT Id, name FROM items ORDER BY name";
        public string GrandTotalColumn { get; set; } = ""; // e.g. "grand_total" / "total_loss" (empty = not stored)
    }

    public static class VoucherRegistry
    {
        public static VoucherSpec Transfer() => new()
        {
            Header = EntityRegistry.TransferHeader(),
            DetailTable = "transfer_details",
            HeaderFkCol = "transfer_id",
            ItemCol = "item_id",
            QtyCol = "quantity",
            PriceCol = "unit_price",
            TotalCol = "total",
            StockEffect = -1
        };

        public static VoucherSpec Damage() => new()
        {
            Header = EntityRegistry.DamageHeader(),
            DetailTable = "damage_details",
            HeaderFkCol = "damage_id",
            ItemCol = "item_id",
            QtyCol = "damage_quantity",
            PriceCol = "last_purchase_price",
            TotalCol = "loss_amount",
            UseDamageCols = true,
            GrandTotalColumn = "total_loss",
            StockEffect = -1
        };

        public static VoucherSpec Quotation() => new()
        {
            Header = EntityRegistry.QuotationHeader(),
            DetailTable = "quotation_details",
            HeaderFkCol = "quotation_id",
            ItemCol = "item_id",
            QtyCol = "quantity",
            PriceCol = "unit_price",
            TotalCol = "total",
            EnsureQtyColumn = true,
            GrandTotalColumn = "grand_total",
            StockEffect = 0
        };

        public static VoucherSpec FixedAssetStockIn() => new()
        {
            Header = EntityRegistry.FixedAssetStockInHeader(),
            DetailTable = "fixed_asset_stock_in_details",
            HeaderFkCol = "fixed_asset_stock_in_id",
            ItemCol = "fixed_asset_item_id",
            QtyCol = "quantity",
            PriceCol = "unit_price",
            TotalCol = "total",
            ItemComboSql = "SELECT Id, name FROM fixed_asset_items ORDER BY name",
            GrandTotalColumn = "grand_total",
            StockEffect = 0
        };

        public static VoucherSpec FixedAssetStockOut() => new()
        {
            Header = EntityRegistry.FixedAssetStockOutHeader(),
            DetailTable = "fixed_asset_stock_out_details",
            HeaderFkCol = "fixed_asset_stock_out_id",
            ItemCol = "fixed_asset_item_id",
            QtyCol = "quantity",
            PriceCol = "unit_price",
            TotalCol = "total",
            ItemComboSql = "SELECT Id, name FROM fixed_asset_items ORDER BY name",
            GrandTotalColumn = "grand_total",
            StockEffect = 0
        };
    }
}
