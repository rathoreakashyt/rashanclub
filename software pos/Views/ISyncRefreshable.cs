namespace RashanKiDukan.Views
{
    /// <summary>
    /// Cloud/Web se sync-pull mein changes aaye (kisi ka delete/edit) to
    /// current open page khud refresh ho jaye — bina manual refresh ke.
    /// MainDashboard har successful pull cycle ke baad current page ko
    /// OnSyncPulled() call karta hai (sirf agar page is interface ko
    /// implement karta hai).
    /// </summary>
    public interface ISyncRefreshable
    {
        void OnSyncPulled();
    }
}