<?php
// ═══════════════════════════════════════════════════════════════════
//   RENT MANAGEMENT SYSTEM 2025 – SINGLE FILE (NO DATABASE)
//   Features: Tenant Login, Razorpay, WhatsApp OTP, Electricity Bill
//   Just upload this file as rent.php → Done!
// ═══════════════════════════════════════════════════════════════════

session_start();
error_reporting(0);

// === CONFIGURATION (CHANGE THESE) ===
$RAZORPAY_KEY = 'rzp_test_xxxxxxxxxxxx';     // ← Change
$RAZORPAY_SECRET = 'xxxxxxxxxxxxxxxx';      // ← Change
$WHATSAPP_TOKEN = 'EAAG...';                 // ← Change (optional)
$PHONE_ID = '123456789012345';               // ← Change
$WHATSAPP_VERSION = 'v19.0';
$SITE_URL = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$DATA_FILE = __DIR__ . '/data.json';
!file_exists($DATA_FILE) && file_put_contents($DATA_FILE, json_encode(['tenants'=>[], 'bills'=>[]]));
$data = json_decode(file_get_contents($DATA_FILE), true);

// === HELPER FUNCTIONS ===
function saveData($d) { file_put_contents(__DIR__.'/data.json', json_encode($d, JSON_PRETTY_PRINT)); }
function calculateElectricity($u) {
    $b=100; 
    if($u<=100) $b+=$u*4.5;
    elseif($u<=200) $b+=(100*4.5)+(($u-100)*6);
    else $b+=(100*4.5)+(100*6)+(($u-200)*8);
    return round($b,2);
}
function sendWhatsApp($to, $msg) {
    global $WHATSAPP_TOKEN, $PHONE_ID, $WHATSAPP_VERSION;
    @file_get_contents("https://graph.facebook.com/$WHATSAPP_VERSION/$PHONE_ID/messages", false, stream_context_create([
        'http'=>['method'=>'POST','header'=>"Authorization: Bearer $WHATSAPP_TOKEN\r\nContent-Type: application/json\r\n",
        'content'=>json_encode(['messaging_product'=>'whatsapp','to'=>$to,'type'=>'text','text'=>['body'=>$msg]])]]));
}

// === ROUTES ===
$page = $_GET['p'] ?? 'home';

if ($page === 'home') { ?>
<!DOCTYPE html><html><head><title>Rent System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head><body class="bg-light">
<div class="container mt-5">
  <h1 class="text-center text-primary">Shree Homes</h1>
  <div class="row mt-4">
    <div class="col-6"><a href="?p=admin" class="btn btn-success w-100 p-4">Landlord Login</a></div>
    <div class="col-6"><a href="?p=tenant" class="btn btn-primary w-100 p-4">Tenant Login</a></div>
  </div>
</div></body></html>
<?php }

// ==================== ADMIN PANEL ====================
elseif ($page === 'admin') {
  if ($_POST['add_tenant'] ?? '') {
    $data['tenants'][] = ['id'=>time(),'name'=>$_POST['name'],'phone'=>$_POST['phone'],'rent'=>(float)$_POST['rent']];
    saveData($data);
  }
  if ($_GET['bill'] ?? '') {
    $tid = (int)$_GET['bill'];
    $t = array_filter($data['tenants'], fn($x)=>$x['id']==$tid)[0] ?? null;
    if ($_POST['units'] !== null) {
      $units = (int)$_POST['units'];
      $elec = calculateElectricity($units);
      $total = $t['rent'] + $elec;
      $order_id = 'order_'.time();
      $data['bills'][] = [
        'id'=>count($data['bills']),
        'tenant_id'=>$tid,
        'tenant_name'=>$t['name'],
        'phone'=>$t['phone'],
        'amount'=>$total,
        'units'=>$units,
        'order_id'=>$order_id,
        'paid'=>false,
        'date'=>date('Y-m-d')
      ];
      saveData($data);
      $paylink = "$SITE_URL/?p=pay&bill=".(count($data['bills'])-1);
      sendWhatsApp($t['phone'], "Hi {$t['name']}! Bill: ₹$total (Rent + $units units). Pay now: $paylink");
      echo "<div class='alert alert-success'>Bill sent!</div>";
    }
    if ($t) { ?>
      <div class="container"><h3>Bill for {$t['name']}</h3>
      <form method="POST"><input name="units" class="form-control" placeholder="Units" required>
      <button class="btn btn-danger mt-2">Generate & Send</button></form></div>
    <?php }
  } ?>
  <div class="container">
    <h2>Admin Panel</h2>
    <form method="POST" class="row g-2">
      <div class="col"><input name="name" placeholder="Name" class="form-control" required></div>
      <div class="col"><input name="phone" placeholder="919xxx" class="form-control" required></div>
      <div class="col"><input name="rent" type="number" placeholder="Rent" class="form-control" required></div>
      <div class="col"><button name="add_tenant" class="btn btn-success">Add Tenant</button></div>
    </form>
    <h4 class="mt-4">Tenants</h4>
    <?php foreach($data['tenants'] as $t): ?>
      <div class="card mb-2"><div class="card-body">
        <b><?=$t['name']?></b> | <?=$t['phone']?> | ₹<?=$t['rent']?>
        <a href="?p=admin&bill=<?=$t['id']?>" class="btn btn-sm btn-warning float-end">Bill</a>
      </div></div>
    <?php endforeach; ?>
    <a href="?p=home" class="btn btn-secondary mt-3">Back</a>
  </div>
<?php }

// ==================== TENANT LOGIN ====================
elseif ($page === 'tenant') {
  if ($_POST['phone'] ?? '') {
    $phone = $_POST['phone'];
    $tenant = array_filter($data['tenants'], fn($t)=>$t['phone']==$phone)[0] ?? null;
    if ($tenant) {
      $otp = rand(100000,999999);
      $_SESSION['otp'] = $otp;
      $_SESSION['tenant_phone'] = $phone;
      sendWhatsApp($phone, "Your OTP: $otp");
      echo "<div class='alert alert-success'>OTP sent!</div>";
    } else echo "<div class='alert alert-danger'>Not found!</div>";
  }
  if (isset($_GET['verify'])) { ?>
    <div class="container">
      <form method="POST"><input name="otp" class="form-control" placeholder="Enter OTP" required>
      <button name="login" class="btn btn-success mt-2">Login</button></form>
    </div>
  <?php }
  if ($_POST['login'] ?? '') {
    if ($_POST['otp'] == $_SESSION['otp']) {
      $_SESSION['tenant_logged'] = $_SESSION['tenant_phone'];
      header("Location: ?p=dashboard"); exit;
    } else echo "<div class='alert alert-danger'>Wrong OTP</div>";
  }
  if (!isset($_GET['verify'])) { ?>
    <div class="container mt-5">
      <h3>Tenant Login</h3>
      <form method="POST"><input name="phone" class="form-control" placeholder="919876543210" required>
      <button class="btn btn-primary mt-2">Send OTP</button></form>
      <?php if ($_SESSION['otp'] ?? '') echo "<a href='?p=tenant&verify=1' class='btn btn-link'>Already have OTP?</a>"; ?>
    </div>
  <?php }

// ==================== TENANT DASHBOARD ====================
elseif ($page === 'dashboard') {
  if (!$_SESSION['tenant_logged'] ?? '') { header("Location: ?p=tenant"); exit; }
  $phone = $_SESSION['tenant_logged'];
  $tenant = array_filter($data['tenants'], fn($t)=>$t['phone']==$phone)[0];
  $bills = array_filter($data['bills'], fn($b)=>$b['phone']==$phone); ?>
  <div class="container">
    <h3>Hi <?=$tenant['name']?></h3>
    <?php foreach($bills as $b): ?>
      <div class="card mb-3 <?= $b['paid']?'border-success':'' ?>">
        <div class="card-body">
          <h5>₹<?=$b['amount']?> <?= $b['paid']?'<span class="badge bg-success">PAID</span>':'' ?></h5>
          Rent + <?=$b['units']?> units<br>Due: <?=date('d M', strtotime($b['date']))?>
          <?php if(!$b['paid']): ?>
            <a href="?p=pay&bill=<?=$b['id']?>" class="btn btn-warning mt-2">Pay Now</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <a href="?p=tenant" class="btn btn-secondary">Logout</a>
  </div>
<?php }

// ==================== RAZORPAY PAYMENT PAGE ====================
elseif ($page === 'pay') {
  $bill_id = (int)$_GET['bill'];
  $bill = $data['bills'][$bill_id] ?? null;
  if (!$bill || $bill['paid']) die("Invalid bill");
  require_once 'razorpay-php/Razorpay.php';
  use Razorpay\Api\Api;
  $api = new Api($RAZORPAY_KEY, $RAZORPAY_SECRET);
  $order = $api->order->create(['amount'=>$bill['amount']*100, 'currency'=>'INR', 'receipt'=>'bill_'.$bill_id]);
  $data['bills'][$bill_id]['order_id'] = $order['id'];
  saveData($data);
  ?>
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <button onclick="payNow()" class="btn btn-success">Pay ₹<?=$bill['amount']?></button>
  <script>
  function payNow() {
    var options = {
      key: "<?=$RAZORPAY_KEY?>",
      amount: <?=$bill['amount']*100?>,
      currency: "INR",
      name: "Shree Homes",
      order_id: "<?=$order['id']?>",
      handler: function(){ location.href="?p=success&bill=<?=$bill_id?>"; }
    };
    new Razorpay(options).open();
  }
  </script>
<?php }

// ==================== PAYMENT SUCCESS ====================
elseif ($page === 'success') {
  $bill_id = (int)$_GET['bill'];
  $data['bills'][$bill_id]['paid'] = true;
  saveData($data);
  $b = $data['bills'][$bill_id];
  sendWhatsApp($b['phone'], "Payment of ₹{$b['amount']} received! Thank you {$b['tenant_name']}");
  echo "<div class='container text-center mt-5'><h1 class='text-success'>Payment Successful!</h1>
        <p>₹{$b['amount']} received. Receipt sent on WhatsApp.</p>
        <a href='?p=tenant' class='btn btn-primary'>Back to Login</a></div>";
}

else echo "<h1>Page not found</h1>";
?>
