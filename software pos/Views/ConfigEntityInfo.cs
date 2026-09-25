namespace RashanKiDukan.Views
{
    public enum ConfigEntity
    {
        ItemCategory,
        Brand,
        Unit,
        Rack,
        Variation,
        ExpenseCategory
    }

    public static class ConfigEntityInfo
    {
        public static string Title(ConfigEntity e) => e switch
        {
            ConfigEntity.ItemCategory => "Item Category",
            ConfigEntity.Brand => "Brand",
            ConfigEntity.Unit => "Unit",
            ConfigEntity.Rack => "Rack",
            ConfigEntity.Variation => "Variation Attribute",
            ConfigEntity.ExpenseCategory => "Expense Category",
            _ => ""
        };

        public static string Plural(ConfigEntity e) => e switch
        {
            ConfigEntity.ItemCategory => "Categories",
            ConfigEntity.Brand => "Brands",
            ConfigEntity.Unit => "Units",
            ConfigEntity.Rack => "Racks",
            ConfigEntity.Variation => "Variations",
            ConfigEntity.ExpenseCategory => "Expense Categories",
            _ => ""
        };

        public static string Table(ConfigEntity e) => e switch
        {
            ConfigEntity.ItemCategory => "ItemCategories",
            ConfigEntity.Brand => "Brands",
            ConfigEntity.Unit => "Units",
            ConfigEntity.Rack => "Racks",
            ConfigEntity.Variation => "Variations",
            ConfigEntity.ExpenseCategory => "ExpenseCategories",
            _ => ""
        };

        public static string NameColumn(ConfigEntity e) => e switch
        {
            ConfigEntity.Unit => "UnitName",
            ConfigEntity.Variation => "VariationName",
            _ => "Name"
        };

        /// <summary>
        /// Server-side (Laravel) table name used by the pending_sync queue for
        /// delete operations — must match SyncController ENTITY_TABLES.
        /// </summary>
        public static string ServerTable(ConfigEntity e) => e switch
        {
            ConfigEntity.ItemCategory => "item_categories",
            ConfigEntity.Brand => "brands",
            ConfigEntity.Unit => "units",
            ConfigEntity.Rack => "racks",
            ConfigEntity.Variation => "variations",
            ConfigEntity.ExpenseCategory => "expense_categories",
            _ => ""
        };
    }
}
