using System;

namespace RashanKiDukan.Services
{
    /// <summary>
    /// Central stock-change notifier. Jab bhi kisi bhi source se stock update
    /// hota hai (Bulk Item Update, Item Master, Purchase, Purchase Return,
    /// Sale Return, Item Import, etc.) — NotifyStockChanged() call karo.
    /// Saare subscribed pages (Stock, Low Stock, Inventory, Item List,
    /// Dashboard) turant refresh ho jaate hain.
    /// 
    /// Enterprise: Also triggers sync to push stock changes to cloud.
    /// </summary>
    public static class StockEvents
    {
        public static event Action? StockChanged;

        /// <summary>
        /// Fired when stock changes need to be synced to cloud.
        /// SyncService subscribes to this to trigger immediate push.
        /// </summary>
        public static event Action? SyncRequired;

        public static void NotifyStockChanged()
        {
            try { StockChanged?.Invoke(); }
            catch (Exception ex) { LogService.Error("StockChanged event handler error", ex); }

            // Trigger sync so stock changes push to cloud immediately
            try { SyncRequired?.Invoke(); }
            catch (Exception ex) { LogService.Error("SyncRequired event handler error", ex); }
        }

        /// <summary>
        /// Notify stock change for a specific item (for targeted sync).
        /// </summary>
        public static void NotifyItemStockChanged(string itemCode, double newStock)
        {
            LogService.Info($"Stock changed: {itemCode} → {newStock}");
            NotifyStockChanged();
        }
    }
}
