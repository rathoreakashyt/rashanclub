/**
 * ZATCA Compliance Manager
 * Handles ZATCA invoice status polling and UI updates
 */

class ZATCAManager {
    constructor() {
        this.pollingIntervals = {}; // Store polling intervals per sale
        this.baseUrl = $('#base_url').val() || window.location.origin;
    }

    /**
     * Start polling ZATCA status for a sale
     * @param {number} saleId - Sale ID
     * @param {function} onStatusUpdate - Callback when status updates
     * @param {number} interval - Polling interval in milliseconds (default: 2000)
     */
    startPolling(saleId, onStatusUpdate = null, interval = 2000) {
        // Stop existing polling for this sale
        this.stopPolling(saleId);

        // Initial check
        this.checkStatus(saleId, onStatusUpdate);

        // Set up polling interval
        const pollInterval = setInterval(() => {
            this.checkStatus(saleId, (status) => {
                if (onStatusUpdate) {
                    onStatusUpdate(status);
                }

                // Stop polling if invoice is cleared, reported, or failed
                if (['cleared', 'reported', 'failed'].includes(status.zatca_status)) {
                    this.stopPolling(saleId);
                }
            });
        }, interval);

        this.pollingIntervals[saleId] = pollInterval;
    }

    /**
     * Stop polling for a sale
     * @param {number} saleId - Sale ID
     */
    stopPolling(saleId) {
        if (this.pollingIntervals[saleId]) {
            clearInterval(this.pollingIntervals[saleId]);
            delete this.pollingIntervals[saleId];
        }
    }

    /**
     * Check ZATCA status for a sale
     * @param {number} saleId - Sale ID
     * @param {function} callback - Callback function
     */
    async checkStatus(saleId, callback = null) {
        try {
            const response = await fetch(`${this.baseUrl}/pos/sale/${saleId}/zatca-status`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
            });

            const result = await response.json();

            if (result.status === 'success' && result.data) {
                if (callback) {
                    callback(result.data);
                }
                this.updateUI(saleId, result.data);
            } else {
                console.error('Failed to get ZATCA status:', result.message);
            }
        } catch (error) {
            console.error('Error checking ZATCA status:', error);
        }
    }

    /**
     * Update UI with ZATCA status
     * @param {number} saleId - Sale ID
     * @param {object} statusData - Status data
     */
    updateUI(saleId, statusData) {
        // Find or create status indicator
        let $statusIndicator = $(`#zatca-status-${saleId}`);
        
        if ($statusIndicator.length === 0) {
            // Create status indicator if it doesn't exist
            $statusIndicator = $('<div>', {
                id: `zatca-status-${saleId}`,
                class: 'zatca-status-indicator'
            });
            
            // Try to find a container to append to (e.g., invoice header)
            const $container = $('.invoice-header, .sale-header, .pos-cart-summary').first();
            if ($container.length) {
                $container.append($statusIndicator);
            }
        }

        // Update status display
        const statusClass = this.getStatusClass(statusData.zatca_status);
        const statusText = this.getStatusText(statusData.zatca_status);
        const statusIcon = this.getStatusIcon(statusData.zatca_status);

        $statusIndicator.html(`
            <div class="zatca-status ${statusClass}">
                <i class="${statusIcon}"></i>
                <span class="zatca-status-text">${statusText}</span>
                ${statusData.is_offline ? '<span class="badge bg-warning">Offline</span>' : ''}
                ${statusData.error ? `<span class="zatca-error text-danger">${this.escapeHtml(statusData.error)}</span>` : ''}
            </div>
        `);

        // Show QR code if available
        if (statusData.qr_code) {
            this.showQRCode(saleId, statusData.qr_code);
        }

        // Show notification based on status
        if (statusData.zatca_status === 'cleared' || statusData.zatca_status === 'reported') {
            if (typeof showSuccessNotification !== 'undefined') {
                showSuccessNotification(`Invoice ${statusData.zatca_status === 'cleared' ? 'cleared' : 'reported'} successfully by ZATCA`);
            }
        } else if (statusData.zatca_status === 'failed') {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification(`ZATCA submission failed: ${statusData.error || 'Unknown error'}`);
            }
        }
    }

    /**
     * Get CSS class for status
     * @param {string} status - ZATCA status
     * @returns {string}
     */
    getStatusClass(status) {
        const classes = {
            'pending': 'text-warning',
            'cleared': 'text-success',
            'reported': 'text-success',
            'failed': 'text-danger',
            'cancelled': 'text-muted',
        };
        return classes[status] || 'text-secondary';
    }

    /**
     * Get status text
     * @param {string} status - ZATCA status
     * @returns {string}
     */
    getStatusText(status) {
        const texts = {
            'pending': 'ZATCA: Processing...',
            'cleared': 'ZATCA: Cleared',
            'reported': 'ZATCA: Reported',
            'failed': 'ZATCA: Failed',
            'cancelled': 'ZATCA: Cancelled',
        };
        return texts[status] || 'ZATCA: Unknown';
    }

    /**
     * Get status icon
     * @param {string} status - ZATCA status
     * @returns {string}
     */
    getStatusIcon(status) {
        const icons = {
            'pending': 'icon-base ti tabler-clock',
            'cleared': 'icon-base ti tabler-check',
            'reported': 'icon-base ti tabler-check',
            'failed': 'icon-base ti tabler-x',
            'cancelled': 'icon-base ti tabler-ban',
        };
        return icons[status] || 'icon-base ti tabler-help';
    }

    /**
     * Show QR code
     * @param {number} saleId - Sale ID
     * @param {string} qrCodeData - QR code data
     */
    showQRCode(saleId, qrCodeData) {
        // Create or update QR code display
        let $qrContainer = $(`#zatca-qr-${saleId}`);
        
        if ($qrContainer.length === 0) {
            $qrContainer = $('<div>', {
                id: `zatca-qr-${saleId}`,
                class: 'zatca-qr-container'
            });
            
            const $statusIndicator = $(`#zatca-status-${saleId}`);
            if ($statusIndicator.length) {
                $statusIndicator.after($qrContainer);
            }
        }

        // Generate QR code using a library (e.g., qrcode.js)
        // For now, just display the data
        $qrContainer.html(`
            <div class="zatca-qr-code">
                <h6>ZATCA QR Code</h6>
                <div class="qr-code-placeholder" data-qr="${this.escapeHtml(qrCodeData)}">
                    <p class="text-muted small">QR Code data available</p>
                    <button class="btn btn-sm btn-primary" onclick="zatcaManager.renderQRCode(${saleId})">
                        Show QR Code
                    </button>
                </div>
            </div>
        `);
    }

    /**
     * Render QR code using a library
     * @param {number} saleId - Sale ID
     */
    renderQRCode(saleId) {
        const $qrPlaceholder = $(`#zatca-qr-${saleId} .qr-code-placeholder`);
        const qrData = $qrPlaceholder.data('qr');

        if (!qrData) {
            return;
        }

        // Use QRCode.js or similar library
        if (typeof QRCode !== 'undefined') {
            $qrPlaceholder.empty();
            new QRCode($qrPlaceholder[0], {
                text: qrData,
                width: 200,
                height: 200,
            });
        } else {
            // Fallback: show data as text
            $qrPlaceholder.html(`<pre class="small">${this.escapeHtml(qrData)}</pre>`);
        }
    }

    /**
     * Retry ZATCA submission
     * @param {number} saleId - Sale ID
     */
    async retrySubmission(saleId) {
        try {
            if (typeof showInfoNotification !== 'undefined') {
                showInfoNotification('Retrying ZATCA submission...');
            }

            const response = await fetch(`${this.baseUrl}/pos/sale/${saleId}/zatca-retry`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
            });

            const result = await response.json();

            if (result.status === 'success') {
                if (typeof showSuccessNotification !== 'undefined') {
                    showSuccessNotification('ZATCA submission retried successfully');
                }
                
                // Start polling again
                this.startPolling(saleId);
            } else {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification(result.message || 'Failed to retry ZATCA submission');
                }
            }
        } catch (error) {
            console.error('Error retrying ZATCA submission:', error);
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Failed to retry ZATCA submission');
            }
        }
    }

    /**
     * Escape HTML
     * @param {string} text
     * @returns {string}
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, (m) => map[m]);
    }
}

// Initialize global instance
const zatcaManager = new ZATCAManager();

// Auto-start polling when sale is saved (if ZATCA data is present)
$(document).ready(function() {
    // Listen for sale save success
    $(document).on('sale:saved', function(e, saleData) {
        if (saleData.zatca && saleData.zatca.status === 'pending') {
            // Start polling for ZATCA status
            zatcaManager.startPolling(saleData.sale_id, (status) => {
                console.log('ZATCA Status Update:', status);
            });
        }
    });
});
