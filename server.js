// ============================================
// VENDING MACHINE PAYMENT SERVER
// Node.js + Express + PayMongo
// ============================================

const express = require('express');
const cors = require('cors');
const axios = require('axios');
const crypto = require('crypto');

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.static('public')); // Serve your existing frontend

// ============================================
// CONFIGURATION
// ============================================

// PayMongo Configuration (Get from https://dashboard.paymongo.com)
const PAYMONGO_SECRET_KEY = process.env.PAYMONGO_SECRET_KEY || 'sk_live_WLCpGs66PbqcMjBaMVsuK5k6';
const PAYMONGO_API = 'https://api.paymongo.com/v1';

// In-memory storage (use database in production like MongoDB/PostgreSQL)
const paymentSessions = new Map();

// Basic authentication header for PayMongo
const getAuthHeader = () => {
  const encoded = Buffer.from(PAYMONGO_SECRET_KEY + ':').toString('base64');
  return `Basic ${encoded}`;
};

// ============================================
// API ENDPOINT: CREATE PAYMENT
// ============================================

app.post('/api/create-payment', async (req, res) => {
  try {
    console.log('📥 Create payment request:', req.body);
    
    const { amount, currency = 'PHP', description = 'Shoe Cover Vending' } = req.body;
    
    if (!amount || amount <= 0) {
      return res.status(400).json({ error: 'Invalid amount' });
    }
    
    // Generate unique payment ID
    const paymentId = `VENDO_${Date.now()}_${crypto.randomBytes(4).toString('hex')}`;
    
    // Convert amount to centavos (PayMongo requires centavos)
    const amountInCentavos = Math.round(amount * 100);
    
    // Create PayMongo Source for GCash
    const sourceData = {
      data: {
        attributes: {
          amount: amountInCentavos,
          currency: currency,
          type: 'gcash',
          redirect: {
            success: `https://rain-shoe-cover-vendo.onrender.com/success?payment_id=${paymentId}`,
            failed: `https://rain-shoe-cover-vendo.onrender.com/failed?payment_id=${paymentId}`
          }
        }
      }
    };
    
    console.log('📤 Creating PayMongo source...');
    
    const response = await axios.post(
      `${PAYMONGO_API}/sources`,
      sourceData,
      {
        headers: {
          'Authorization': getAuthHeader(),
          'Content-Type': 'application/json'
        }
      }
    );
    
    const source = response.data.data;
    const checkoutUrl = source.attributes.redirect.checkout_url;
    
    console.log('✅ PayMongo source created:', source.id);
    console.log('🔗 Checkout URL:', checkoutUrl);
    
    // Store payment session
    const paymentData = {
      payment_id: paymentId,
      qr_data: checkoutUrl, // This is the URL that will be encoded in QR
      amount: amount,
      currency: currency,
      status: 'pending',
      paymongo_source_id: source.id,
      created_at: Date.now()
    };
    
    paymentSessions.set(paymentId, paymentData);
    
    // Response to ESP32
    res.json({
      payment_id: paymentId,
      qr_data: checkoutUrl, // ESP32 will generate QR from this URL
      amount: amount,
      currency: currency,
      status: 'pending'
    });
    
  } catch (error) {
    console.error('❌ Error creating payment:', error.response?.data || error.message);
    res.status(500).json({ 
      error: 'Failed to create payment',
      details: error.response?.data || error.message 
    });
  }
});

// ============================================
// API ENDPOINT: CHECK PAYMENT STATUS
// ============================================

app.get('/api/check-payment/:paymentId', async (req, res) => {
  try {
    const { paymentId } = req.params;
    
    console.log('🔍 Checking payment:', paymentId);
    
    const session = paymentSessions.get(paymentId);
    
    if (!session) {
      return res.status(404).json({ error: 'Payment not found' });
    }
    
    // Check PayMongo source status
    try {
      const response = await axios.get(
        `${PAYMONGO_API}/sources/${session.paymongo_source_id}`,
        {
          headers: {
            'Authorization': getAuthHeader()
          }
        }
      );
      
      const source = response.data.data;
      const sourceStatus = source.attributes.status;
      
      console.log('📊 PayMongo status:', sourceStatus);
      
      // Map PayMongo status to our status
      let status = 'pending';
      if (sourceStatus === 'chargeable' || sourceStatus === 'paid') {
        status = 'paid';
        
        // If chargeable, create charge automatically
        if (sourceStatus === 'chargeable' && session.status !== 'paid') {
          await createCharge(session);
        }
      } else if (sourceStatus === 'cancelled' || sourceStatus === 'expired') {
        status = 'failed';
      }
      
      // Update stored status
      session.status = status;
      paymentSessions.set(paymentId, session);
      
    } catch (error) {
      console.error('⚠️ Error checking PayMongo:', error.message);
    }
    
    res.json({
      payment_id: paymentId,
      status: session.status,
      amount: session.amount,
      currency: session.currency
    });
    
  } catch (error) {
    console.error('❌ Error checking payment:', error.message);
    res.status(500).json({ error: error.message });
  }
});

// ============================================
// HELPER: CREATE CHARGE
// ============================================

async function createCharge(session) {
  try {
    console.log('💳 Creating charge for:', session.payment_id);
    
    const chargeData = {
      data: {
        attributes: {
          amount: Math.round(session.amount * 100),
          currency: session.currency,
          source: {
            id: session.paymongo_source_id,
            type: 'source'
          }
        }
      }
    };
    
    const response = await axios.post(
      `${PAYMONGO_API}/payments`,
      chargeData,
      {
        headers: {
          'Authorization': getAuthHeader(),
          'Content-Type': 'application/json'
        }
      }
    );
    
    console.log('✅ Charge created:', response.data.data.id);
    session.payment_id_paymongo = response.data.data.id;
    session.status = 'paid';
    
  } catch (error) {
    console.error('❌ Error creating charge:', error.response?.data || error.message);
  }
}

// ============================================
// WEBHOOK ENDPOINT (for real-time updates)
// ============================================

app.post('/api/webhook/paymongo', async (req, res) => {
  try {
    console.log('🔔 PayMongo webhook received');
    console.log('Event:', req.body.data?.attributes?.type);
    
    const event = req.body.data;
    const eventType = event.attributes.type;
    
    if (eventType === 'source.chargeable') {
      const sourceId = event.attributes.data.id;
      
      // Find payment session
      for (const [paymentId, session] of paymentSessions.entries()) {
        if (session.paymongo_source_id === sourceId) {
          console.log('✅ Payment chargeable:', paymentId);
          
          // Create charge
          await createCharge(session);
          
          console.log('✅ Payment completed:', paymentId);
          break;
        }
      }
    }
    
    res.json({ received: true });
    
  } catch (error) {
    console.error('❌ Webhook error:', error.message);
    res.status(500).json({ error: error.message });
  }
});

// ============================================
// SUCCESS/FAILED PAGES
// ============================================

app.get('/success', (req, res) => {
  const { payment_id } = req.query;
  res.send(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Payment Success</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <style>
        body {
          font-family: Arial, sans-serif;
          display: flex;
          justify-content: center;
          align-items: center;
          height: 100vh;
          margin: 0;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
          background: white;
          padding: 40px;
          border-radius: 20px;
          text-align: center;
          box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .checkmark {
          width: 80px;
          height: 80px;
          border-radius: 50%;
          display: block;
          stroke-width: 2;
          stroke: #4bb71b;
          stroke-miterlimit: 10;
          margin: 10% auto;
          box-shadow: inset 0px 0px 0px #4bb71b;
          animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }
        .checkmark__circle {
          stroke-dasharray: 166;
          stroke-dashoffset: 166;
          stroke-width: 2;
          stroke-miterlimit: 10;
          stroke: #4bb71b;
          fill: none;
          animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }
        .checkmark__check {
          transform-origin: 50% 50%;
          stroke-dasharray: 48;
          stroke-dashoffset: 48;
          animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }
        @keyframes stroke {
          100% { stroke-dashoffset: 0; }
        }
        @keyframes scale {
          0%, 100% { transform: none; }
          50% { transform: scale3d(1.1, 1.1, 1); }
        }
        @keyframes fill {
          100% { box-shadow: inset 0px 0px 0px 30px #4bb71b; }
        }
        h1 { color: #4bb71b; margin-top: 20px; }
        p { color: #666; }
      </style>
    </head>
    <body>
      <div class="container">
        <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
          <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
          <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
        </svg>
        <h1>Payment Successful!</h1>
        <p>Your shoe covers are being dispensed.</p>
        <p style="font-size: 12px; color: #999;">Payment ID: ${payment_id}</p>
        <p style="margin-top: 30px;">You can close this page.</p>
      </div>
    </body>
    </html>
  `);
});

app.get('/failed', (req, res) => {
  const { payment_id } = req.query;
  res.send(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Payment Failed</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <style>
        body {
          font-family: Arial, sans-serif;
          display: flex;
          justify-content: center;
          align-items: center;
          height: 100vh;
          margin: 0;
          background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .container {
          background: white;
          padding: 40px;
          border-radius: 20px;
          text-align: center;
          box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .error {
          font-size: 60px;
          color: #f5576c;
        }
        h1 { color: #f5576c; }
        p { color: #666; }
      </style>
    </head>
    <body>
      <div class="container">
        <div class="error">✖</div>
        <h1>Payment Failed</h1>
        <p>Your payment was not completed.</p>
        <p style="font-size: 12px; color: #999;">Payment ID: ${payment_id}</p>
        <p style="margin-top: 30px;">Please try again at the machine.</p>
      </div>
    </body>
    </html>
  `);
});

// ============================================
// MAIN LANDING PAGE (optional)
// ============================================

app.get('/', (req, res) => {
  res.send(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Shoe Cover Vending Machine</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <style>
        body {
          font-family: Arial, sans-serif;
          margin: 0;
          padding: 0;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          color: white;
          text-align: center;
          padding-top: 50px;
        }
        .container {
          max-width: 600px;
          margin: 0 auto;
          padding: 20px;
        }
        h1 { font-size: 2.5em; margin-bottom: 10px; }
        p { font-size: 1.2em; opacity: 0.9; }
        .status {
          background: rgba(255,255,255,0.1);
          padding: 20px;
          border-radius: 10px;
          margin-top: 30px;
        }
      </style>
    </head>
    <body>
      <div class="container">
        <h1>🌧️ Shoe Cover Vending Machine</h1>
        <p>Waterproof Protection for Rainy Days</p>
        <div class="status">
          <h2>Payment API Server</h2>
          <p>✅ Online and Ready</p>
          <p style="font-size: 0.9em; opacity: 0.7;">This server handles QR code payment generation</p>
        </div>
      </div>
    </body>
    </html>
  `);
});

// ============================================
// START SERVER
// ============================================

app.listen(PORT, () => {
  console.log('\n╔══════════════════════════════════════╗');
  console.log('║  VENDING MACHINE PAYMENT SERVER     ║');
  console.log('╚══════════════════════════════════════╝');
  console.log(`\n🚀 Server running on port ${PORT}`);
  console.log(`📡 API: http://localhost:${PORT}/api`);
  console.log(`\n📝 Endpoints:`);
  console.log(`   POST /api/create-payment`);
  console.log(`   GET  /api/check-payment/:paymentId`);
  console.log(`   POST /api/webhook/paymongo`);
  console.log(`\n💡 Ready to receive requests from ESP32\n`);
});

// Graceful shutdown
process.on('SIGTERM', () => {
  console.log('👋 SIGTERM signal received: closing server');
  process.exit(0);
});
