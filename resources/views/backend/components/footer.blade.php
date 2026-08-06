@php
  $whiteLabel = getWhiteLabel();
  $lpIsBd = defined('LP') && strtoupper((string) LP) === 'BD';
@endphp
<footer class="content-footer footer bg-footer-theme">
  <div class="container-xxl">
    <div
      class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
      <div class="text-body">
        <span id="app-footer-text">
          © <script>document.write(new Date().getFullYear());</script>, Developed by <strong>Digital Akash</strong>
        </span>
      </div>
      <div class="d-none d-lg-inline-block">
        <a href="https://wa.me/917827307271" target="_blank" class="footer-link d-none d-sm-inline-block">Support</a>
      </div>
    </div>
  </div>
</footer>