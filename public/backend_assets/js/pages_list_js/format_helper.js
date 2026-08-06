/**
 * Format Helper - Lightweight Version
 * Supports only 3 date formats:
 * Y/m/d , d/m/Y , m/d/Y
 */
(function() {
  "use strict";
  let companyData = {};
  let initialized = false;
  function initializeCompanyData() {
    if (initialized) return;
    const el = document.getElementById("company_data");
    if (el) {
      try {
        companyData = JSON.parse(el.value) || {};
      } catch (e) {
        console.error("Invalid company JSON");
      }
    }
    initialized = true;
  }

  /**
   * Format date for only:
   * Y/m/d
   * d/m/Y
   * m/d/Y
   */
  function simpleFormatDate(dateInput, format) {
    if (!dateInput) return "";
    const date = new Date(dateInput);
    if (isNaN(date.getTime())) return dateInput;
    const Y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    // Supported formats
    let f = format || companyData.date_format || "Y/m/d";
    if (f === "Y/m/d") return `${Y}/${m}/${d}`;
    if (f === "d/m/Y") return `${d}/${m}/${Y}`;
    if (f === "m/d/Y") return `${m}/${d}/${Y}`;
    return `${Y}/${m}/${d}`; // fallback
  }
  /**
   * Format Amount (Simplified)
   */
  window.formatAmount = function(amount, includeCurrency = true) {
    initializeCompanyData();
    const precision = parseInt(companyData.precision) || 2;
    const thousandSep = companyData.thousands_separator || ",";
    const decimalSep = companyData.decimals_separator || ".";
    const currency = includeCurrency ? (companyData.currency || "") : "";
    const position = companyData.currency_position || "Before Amount";
    let num = parseFloat(amount);
    if (isNaN(num)) num = 0;
    const formatted = num
      .toFixed(precision)
      .replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep)
      .replace(".", decimalSep);
    return position === "Before Amount"
      ? currency + formatted
      : formatted + currency;
  };
  /**
   * Format Date (Simplified)
   */
  window.formatDate = function(dateInput, format) {
    initializeCompanyData();
    return simpleFormatDate(dateInput, format);
  };
  // Auto init
  document.addEventListener("DOMContentLoaded", initializeCompanyData);
})();