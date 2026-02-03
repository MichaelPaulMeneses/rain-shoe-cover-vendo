<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR CODE</title>

    <style>
        #expiredModal .modal-dialog,
        #successModal .modal-dialog {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0 auto;
        }
        
        .payment-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
        }
        
        .payment-tab {
            padding: 10px 20px;
            border: none;
            background: none;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 500;
            color: #6c757d;
        }
        
        .payment-tab.active {
            color: #0d6efd;
            border-bottom-color: #0d6efd;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .mobile-input-wrapper {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .mobile-number-input {
            font-size: 1.1rem;
            padding: 12px;
        }
        
        .wallet-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .wallet-option {
            flex: 1;
            padding: 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        
        .wallet-option:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        
        .wallet-option.selected {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        
        .wallet-option input[type="radio"] {
            display: none;
        }
        
        .wallet-logo {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
    </style>

</head>
    
<body>

    <div class="d-flex justify-content-center align-items-center" style="height: 100vh;">
        <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#myModal">Pay Now</button>
    </div>

    <div class="modal fade" id="myModal" tabindex="-1" aria-labelledby="myModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel">Select Payment Method</h5>
                </div>
                <div class="modal-body">
                    <!-- Payment Method Tabs -->
                    <div class="payment-tabs">
                        <button class="payment-tab active" data-tab="qr">
                            <svg width="20" height="20" fill="currentColor" class="me-1" viewBox="0 0 16 16">
                                <path d="M2 2h4v4H2V2zm1 1v2h2V3H3z"/>
                                <path d="M8 2h4v4H8V2zm1 1v2h2V3H9z"/>
                                <path d="M2 8h4v4H2V8zm1 1v2h2V9H3z"/>
                            </svg>
                            Scan QR Code
                        </button>
                        <button class="payment-tab" data-tab="mobile">
                            <svg width="20" height="20" fill="currentColor" class="me-1" viewBox="0 0 16 16">
                                <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z"/>
                                <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/>
                            </svg>
                            Mobile Number
                        </button>
                    </div>

                    <!-- QR Code Tab Content -->
                    <div id="qrTabContent" class="tab-content active">
                        <div id="qrContainer" class="text-center">
                            <p>Loading QR...</p>
                        </div>
                    </div>

                    <!-- Mobile Number Tab Content -->
                    <div id="mobileTabContent" class="tab-content">
                        <div class="mobile-input-wrapper">
                            <!-- Wallet Selection -->
                            <div class="wallet-selector">
                                <label class="wallet-option" id="gcashOption">
                                    <input type="radio" name="wallet" value="gcash" checked>
                                    <div class="wallet-logo" style="color: #007DFF;">GCash</div>
                                    <small class="text-muted">Pay with GCash</small>
                                </label>
                                <label class="wallet-option selected" id="mayaOption">
                                    <input type="radio" name="wallet" value="paymaya">
                                    <div class="wallet-logo" style="color: #00D632;">Maya</div>
                                    <small class="text-muted">Pay with Maya</small>
                                </label>
                            </div>

                            <!-- Mobile Number Input -->
                            <div class="mb-3">
                                <label for="mobileNumber" class="form-label fw-bold">Mobile Number</label>
                                <div class="input-group">
                                    <span class="input-group-text">+63</span>
                                    <input 
                                        type="tel" 
                                        class="form-control mobile-number-input" 
                                        id="mobileNumber" 
                                        placeholder="9XX XXX XXXX"
                                        maxlength="10"
                                        pattern="[0-9]{10}"
                                    >
                                </div>
                                <div class="form-text">Enter your 10-digit mobile number (without +63)</div>
                                <div id="mobileError" class="text-danger small mt-2" style="display: none;"></div>
                            </div>

                            <!-- Amount Display -->
                            <div class="alert alert-info mb-3">
                                <strong>Amount to Pay:</strong> ₱1.00
                            </div>

                            <!-- Submit Button -->
                            <button type="button" class="btn btn-primary w-100" id="payWithMobileBtn">
                                <svg width="16" height="16" fill="currentColor" class="me-1" viewBox="0 0 16 16">
                                    <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1H2zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7z"/>
                                    <path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1z"/>
                                </svg>
                                Proceed to Payment
                            </button>

                            <div class="mt-3 text-muted small">
                                <strong>Note:</strong> You will receive a payment notification on your mobile wallet app. Please approve it to complete the transaction.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cancelBtn">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Expired Modal -->
    <div class="modal fade" id="expiredModal" tabindex="-1" aria-labelledby="expiredModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 300px;">
            <div class="modal-content border-danger border-3">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="expiredModalLabel">QR Code Expired</h5>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <svg class="text-danger" width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M2 2h4v4H2V2zm1 1v2h2V3H3z"/>
                            <path d="M8 2h4v4H8V2zm1 1v2h2V3H9z"/>
                            <path d="M2 8h4v4H2V8zm1 1v2h2V9H3z"/>
                            <path d="M9.5 9.5l4 4m0-4-4 4"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"/>
                        </svg>
                    </div>
                    <p class="text-danger fw-bold">The QR code has expired. Please try again.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 250px;">
            <div class="modal-content border-success border-3">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">✓ Payment Successful</h5>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <svg class="text-success" width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>
                            <path fill-rule="evenodd" d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-1.5 0a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0z"/>
                        </svg>
                    </div>
                    <p class="text-success fw-bold">Your payment has been processed successfully.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Dispensing Modal -->
    <div class="modal fade" id="dispensingModal" tabindex="-1" aria-labelledby="dispensingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 300px;">
            <div class="modal-content border-info border-3">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="dispensingModalLabel">Dispensing Product</h5>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <svg class="text-info" width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
                            <style>
                                @keyframes spin {
                                    from { transform: rotate(0deg); }
                                    to { transform: rotate(360deg); }
                                }
                                .spinner { animation: spin 1s linear infinite; }
                            </style>
                            <g class="spinner" transform-origin="50% 50%">
                                <circle cx="8" cy="8" r="7" fill="none" stroke="currentColor" stroke-width="2" stroke-dasharray="11 22" />
                            </g>
                        </svg>
                    </div>
                    <p class="text-info fw-bold">Please wait while we dispense your product...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Awaiting Payment Modal -->
    <div class="modal fade" id="awaitingModal" tabindex="-1" aria-labelledby="awaitingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 350px;">
            <div class="modal-content border-warning border-3">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="awaitingModalLabel">⏳ Waiting for Payment</h5>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <svg class="text-warning" width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
                            <style>
                                @keyframes pulse {
                                    0%, 100% { opacity: 1; }
                                    50% { opacity: 0.5; }
                                }
                                .pulse { animation: pulse 1.5s ease-in-out infinite; }
                            </style>
                            <g class="pulse">
                                <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z"/>
                                <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/>
                            </g>
                        </svg>
                    </div>
                    <p class="fw-bold">Please check your phone</p>
                    <p class="text-muted small">A payment request has been sent to your <span id="walletName">mobile wallet</span>. Please approve it to complete the transaction.</p>
                    <div class="alert alert-light mt-3">
                        <small><strong>Amount:</strong> ₱1.00</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" id="cancelAwaitingBtn">Cancel Payment</button>
                </div>
            </div>
        </div>
    </div>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<script>
document.addEventListener("DOMContentLoaded", () => {

    const qrContainer = document.getElementById("qrContainer");
    const qrModalEl = document.getElementById("myModal");
    const mobileNumberInput = document.getElementById("mobileNumber");
    const payWithMobileBtn = document.getElementById("payWithMobileBtn");
    const mobileError = document.getElementById("mobileError");

    let intentId = null;
    let showDispensing = null;
    let currentPaymentMethod = 'qr'; // 'qr' or 'mobile'

    // ---- Tab Switching ----
    document.querySelectorAll('.payment-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            // Update active tab
            document.querySelectorAll('.payment-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            // Update active content
            const tabName = tab.dataset.tab;
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabName + 'TabContent').classList.add('active');

            currentPaymentMethod = tabName;

            // Load QR if switching to QR tab and not loaded yet
            if (tabName === 'qr' && qrContainer.innerHTML === '<p>Loading QR...</p>') {
                loadQRCode();
            }
        });
    });

    // ---- Wallet Selection ----
    document.querySelectorAll('.wallet-option').forEach(option => {
        option.addEventListener('click', () => {
            document.querySelectorAll('.wallet-option').forEach(o => o.classList.remove('selected'));
            option.classList.add('selected');
            option.querySelector('input[type="radio"]').checked = true;
        });
    });

    // ---- Mobile Number Formatting ----
    mobileNumberInput.addEventListener('input', (e) => {
        let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
        if (value.length > 10) value = value.slice(0, 10);
        e.target.value = value;
        mobileError.style.display = 'none';
    });

    // ---- Pay with Mobile Number ----
    payWithMobileBtn.addEventListener('click', async () => {
        const mobileNumber = mobileNumberInput.value.trim();
        const wallet = document.querySelector('input[name="wallet"]:checked').value;

        // Validation
        if (!mobileNumber || mobileNumber.length !== 10) {
            mobileError.textContent = 'Please enter a valid 10-digit mobile number';
            mobileError.style.display = 'block';
            return;
        }

        if (!/^9\d{9}$/.test(mobileNumber)) {
            mobileError.textContent = 'Mobile number must start with 9';
            mobileError.style.display = 'block';
            return;
        }

        // Disable button and show loading
        payWithMobileBtn.disabled = true;
        payWithMobileBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

        try {
            const response = await fetch('generate_mobile_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    mobile: '+63' + mobileNumber,
                    wallet: wallet
                })
            });

            const result = await response.json();

            if (result.success) {
                intentId = result.intent_id;
                
                // Close payment modal
                const modal = bootstrap.Modal.getInstance(qrModalEl);
                modal.hide();

                // Show awaiting payment modal
                const awaitingModal = new bootstrap.Modal(document.getElementById('awaitingModal'));
                document.getElementById('walletName').textContent = wallet === 'gcash' ? 'GCash' : 'Maya';
                awaitingModal.show();

            } else {
                mobileError.textContent = result.message || 'Payment request failed. Please try again.';
                mobileError.style.display = 'block';
            }

        } catch (error) {
            console.error('Payment error:', error);
            mobileError.textContent = 'An error occurred. Please try again.';
            mobileError.style.display = 'block';
        } finally {
            payWithMobileBtn.disabled = false;
            payWithMobileBtn.innerHTML = '<svg width="16" height="16" fill="currentColor" class="me-1" viewBox="0 0 16 16"><path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1H2zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7z"/><path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1z"/></svg>Proceed to Payment';
        }
    });

    // ---- Cancel Awaiting Payment ----
    document.getElementById('cancelAwaitingBtn').addEventListener('click', () => {
        if (intentId) {
            fetch("cancel_qr.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ intent_id: intentId })
            });
        }
        location.reload();
    });

    // ---- Load QR Code ----
    function loadQRCode() {
        qrContainer.innerHTML = "Generating QR...";

        fetch("generate_qr.php")
            .then(res => res.text())
            .then(html => {
                qrContainer.innerHTML = html;
                const wrapper = document.getElementById("qrWrapper");
                intentId = wrapper?.dataset.intentId;
                console.log("Intent ID:", intentId);
            })
            .catch(() => {
                qrContainer.innerHTML = "Error loading QR";
            });
    }

    // ---- Download QR Function ----
    function downloadQRCode() {
        const qrImg = document.getElementById('qrCodeImage');
        const downloadBtn = document.getElementById('downloadQrBtn');
        
        if (!qrImg) {
            alert('QR Code not found. Please try generating again.');
            return;
        }

        try {
            const imgSrc = qrImg.src;
            const link = document.createElement('a');
            link.href = imgSrc;
            link.download = 'payment-qr-code.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            if (downloadBtn) {
                const originalHTML = downloadBtn.innerHTML;
                downloadBtn.innerHTML = '<svg width="16" height="16" fill="currentColor" class="me-1" viewBox="0 0 16 16"><path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/></svg> Downloaded!';
                setTimeout(() => { downloadBtn.innerHTML = originalHTML; }, 2000);
            }
        } catch (err) {
            console.error('Download error:', err);
            alert('Download failed. Please:\n1. Long-press the QR code\n2. Select "Save Image"');
        }
    }

    // ---- Event Delegation for Download Button ----
    qrContainer.addEventListener('click', function(e) {
        if (e.target.id === 'downloadQrBtn' || e.target.closest('#downloadQrBtn')) {
            downloadQRCode();
        }
    });

    // ---- Load QR when modal opens (QR tab is default) ----
    qrModalEl.addEventListener("shown.bs.modal", () => {
        if (currentPaymentMethod === 'qr') {
            loadQRCode();
        }
    });

    // ---- Reset on modal close ----
    qrModalEl.addEventListener("hidden.bs.modal", () => {
        qrContainer.innerHTML = '<p>Loading QR...</p>';
        mobileNumberInput.value = '';
        mobileError.style.display = 'none';
        intentId = null;
    });

    // ---- Cancel QR ----
    cancelBtn.addEventListener("click", () => {
        if (!intentId) return;

        fetch("cancel_qr.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ intent_id: intentId })
        });

        intentId = null;
        qrContainer.innerHTML = "";
    });

    // ---- Check payment status ----
    function checkStatus() {
        if (!intentId) return;

        fetch(`https://vendo-machine-c75fb-default-rtdb.asia-southeast1.firebasedatabase.app/payments/${intentId}.json`)
            .then(res => res.json())
            .then(data => {
                if (!data || !data.status) return;

                if (data.status === "paid") {
                    // Close awaiting modal if open
                    const awaitingModal = bootstrap.Modal.getInstance(document.getElementById('awaitingModal'));
                    if (awaitingModal) awaitingModal.hide();

                    const showSuccess = new bootstrap.Modal(document.getElementById("successModal"));
                    showSuccess.show();
                    
                    fetch("vendo_control.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" }
                    });

                    setTimeout(() => location.reload(), 2000);
                }

                if (data.status === "expired") {
                    new bootstrap.Modal(document.getElementById("expiredModal")).show();
                    setTimeout(() => location.reload(), 2000);
                }
            });
    }

    // ---- Check Vendo State ----
    function checkState() {
        fetch(`https://vendo-machine-c75fb-default-rtdb.asia-southeast1.firebasedatabase.app/vendo.json`)
            .then(res => res.json())
            .then(data => {
                if (!data || data.state === undefined) return;

                if (!showDispensing) {
                    showDispensing = new bootstrap.Modal(document.getElementById("dispensingModal"));
                }

                if (data.state === 1) {
                    showDispensing.show();
                }

                if (data.state === 0) {
                    showDispensing.hide();
                    checkStatus();
                }
            });
    }

    setInterval(checkStatus, 2000);
    setInterval(checkState, 3000);

});
</script>

</body>
</html>
