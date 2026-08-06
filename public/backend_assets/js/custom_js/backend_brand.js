(function () {
  "use strict";
  var pageTitle = document.title || "";
  document.title = "Rashan Ki Dukan - Digital Akash | " + pageTitle;
  var headerEl = document.getElementById("install-header-text");
  if (headerEl) headerEl.textContent = "Rashan Ki Dukan - Digital Akash";
  var el = document.getElementById("app-footer-text");
  if (el) el.textContent = "© " + new Date().getFullYear() + ", Developed by Digital Akash";
})();
