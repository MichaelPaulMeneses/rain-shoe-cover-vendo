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
    </style>

</head>
    
<body>

    <div class="d-flex justify-content-center align-items-center" style="height: 100vh;">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#myModal">Generate QR Code</button>
    </div>

    <div class="modal fade" id="myModal" tabindex="-1" aria-labelledby="myModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel">Scan to Pay (QR PH)</h5>
                </div>
                <div class="modal-body text-center" id="qrContainer">
                    <p>Loading QR...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                    id="cancelBtn" >Cancel</button>
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

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<script>
document.addEventListener("DOMContentLoaded", () => {

    const qrContainer = document.getElementById("qrContainer");
    const qrModalEl = document.getElementById("myModal");

    let intentId = null;

    let showDispensing = null;


    // ---- Load QR when modal opens ----
    qrModalEl.addEventListener("shown.bs.modal", () => {
        qrContainer.innerHTML = "Generating QR...";

        fetch("generate_qr.php")
            .then(res => res.text())
            .then(html => {
                qrContainer.innerHTML = html;

                // 🔥 GET intentId FROM GENERATED HTML
                const wrapper = document.getElementById("qrWrapper");
                intentId = wrapper?.dataset.intentId;

                console.log("Intent ID:", intentId);

                // 🔥 ATTACH EVENT LISTENERS TO DEEP LINK BUTTONS
                attachDeepLinkHandlers();
            })
            .catch(() => {
                qrContainer.innerHTML = "Error loading QR";
            });
    });

    // ---- Function to attach deep link handlers ----
    function attachDeepLinkHandlers() {
        const gcashBtn = document.querySelector('.open-gcash');
        const mayaBtn = document.querySelector('.open-maya');

        if (gcashBtn) {
            gcashBtn.addEventListener('click', function() {
                const qrUrl = this.dataset.qrUrl;
                if (qrUrl) {
                    // Try to open GCash app with deep link
                    // Format: gcash://qr?data=<qr_url>
                    const deepLink = `gcash://qr?data=${encodeURIComponent(qrUrl)}`;
                    window.location.href = deepLink;
                    
                    // Fallback: if app doesn't open in 2 seconds, show instruction
                    setTimeout(() => {
                        alert('If GCash app did not open, please make sure it is installed on your device.');
                    }, 2000);
                }
            });
        }

        if (mayaBtn) {
            mayaBtn.addEventListener('click', function() {
                const qrUrl = this.dataset.qrUrl;
                if (qrUrl) {
                    // Try to open Maya app with deep link
                    // Format: maya://qr?data=<qr_url>
                    const deepLink = `paymaya://qr?data=${encodeURIComponent(qrUrl)}`;
                    window.location.href = deepLink;
                    
                    // Fallback: if app doesn't open in 2 seconds, show instruction
                    setTimeout(() => {
                        alert('If Maya app did not open, please make sure it is installed on your device.');
                    }, 2000);
                }
            });
        }
    }

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
                    const showSuccess = new bootstrap.Modal(
                        document.getElementById("successModal")
                    );
                    
                    showSuccess.show();
                    
                    fetch("vendo_control.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" }
                    });

                    //setInterval(checkState, 1000);
                    setTimeout(() => location.reload(), 2000);
                }

                if (data.status === "expired") {
                    new bootstrap.Modal(
                        document.getElementById("expiredModal")
                    ).show();

                    setTimeout(() => location.reload(), 2000);
                }
            });
    }

    // ---- Check Vendo State ----
    function checkState() {
        console.log("Started polling state every 3 seconds");

         // start checking state
        fetch(`https://vendo-machine-c75fb-default-rtdb.asia-southeast1.firebasedatabase.app/vendo.json`)
            .then(res => res.json())
            .then(data => {
                if (!data || data.state === undefined) return;

                if (!showDispensing) {
                    showDispensing = new bootstrap.Modal(
                        document.getElementById("dispensingModal")
                    );
                }

                // ---- DISPENSING ----
                if (data.state === 1) {
                    //console.log("State is 1, showing dispensing modal...");
                    showDispensing.show();
                }

                // ---- IDLE / RESET ----
                if (data.state === 0) {
                    //console.log("State is now 0, reloading page...");
                    showDispensing.hide();
                    checkStatus();
                }
            });
    }

setInterval(checkState, 3000);

});
</script>

</body>
</html>
