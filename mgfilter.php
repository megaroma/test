<?php
session_start();

$directory = dirname($_SERVER["PHP_SELF"]);
ini_set("include_path", "$directory:/var/www/html/PHPMailer_v5.2.9");
require("PHPMailerAutoload.php");
date_default_timezone_set("America/Detroit");

//config
$host = '127.0.0.1'; $port = '4040'; $user = 'scott'; $pass = 'brutus1'; $db = 'pactel';
//$host = 'localhost'; $port = '4040'; $user = 'root'; $pass = ''; $db = 'pactel';

$mysqli = new mysqli($host,$user,$pass,$db,$port);

//show message
if(isset($_GET['get_message'])) {
  $id = $_GET['id'];
  if (isset($_SESSION[$id])) {
    echo $_SESSION[$id];
    $_SESSION[$id] = '';
    exit;
  } else {
    echo "message is not found";
    exit;
  }
}

//ajax code
if(isset($_POST['action'])) {

//-----
if($_POST['action'] == 'submit') {
  $acctid = trim($_POST['acctid']);
  $acctid = $mysqli->real_escape_string($acctid);
  $inmatename = "error";
  $sql = "select inmatename from customer where acctid = '$acctid'";
  $result = $mysqli->query($sql);
  if($result) {
    $obj = $result->fetch_object(); 
    if($obj) {
      $inmatename = $obj->inmatename;
    } 
  }
  echo $inmatename;
  exit;
}
//-----
if($_POST['action'] == 'confirm') {
  $acctid = trim($_POST['acctid']);
  $acctid = $mysqli->real_escape_string($acctid);
  $amount = trim($_POST['amount']);
  $amount = $mysqli->real_escape_string($amount); 
  $amount = trim($amount,"$");
  $inmate = trim($_POST['inmate']);
  $inmate = $mysqli->real_escape_string($inmate);   

  $sqlbal = "select balance,svc_plan_id from customer where acctid = '$acctid'"; 
  $result = $mysqli->query($sqlbal); 
  if($result) { 
    $obj = $result->fetch_object(); 
    if($obj) { 
      $priorbalance = round($obj->balance,2); 
      $svc_plan_id = $obj->svc_plan_id; 
    } 
  }
while($result->fetch_object());
$result->close();
    
  $ref_number = trim($_POST['ref_number']);
  $ref_number = $mysqli->real_escape_string($ref_number);
  $sql = "call insertCustomerCredit('$acctid' ,'WU' ,'$ref_number' , '$amount' ,'- (PriorBal $priorbalance ) Sender - $inmate','$amount' )";
  if($result = $mysqli->query($sql)) {
    while($result->fetch_object());
     $result->close();
    while($mysqli->more_results()){
      $mysqli->next_result();
    }
    $balance = '';
    $sqlbal = "select balance from customer where acctid = '$acctid'"; 
    $result = $mysqli->query($sqlbal); 
    if($result) { 
      $obj = $result->fetch_object(); 
      if($obj) { 
        $balance = round($obj->balance,2); 
      } 
    }   
    $result->close();
    $response = "This is an automated message. Do not reply to this email. You must email your trustee with questions. I cannot answer any questions. I can only receive money. Received \${$amount}. Current Balance \$$balance";
    $id = uniqid();
    $_SESSION[$id] = $response;
    echo $id;

// add some code here to chk for un provisiiond lines in didmaprequest table like we do in authnet.php
// and then fire a procmail() call 
    $didcount = 0;
    $sqldid="SELECT COUNT(*) AS linecount FROM DIDmap WHERE acctid = '$acctid'";
    $result = $mysqli->query($sqldid);
    if($result) {
      $obj = $result->fetch_object();
      if($obj) {
        $didcount = $obj->linecount;
      }
    }
    $requestcount = 0;
    $sqlreq="SELECT COUNT(*) AS linecount FROM DIDmapRequest WHERE acctid = '$acctid'";
    $result = $mysqli->query($sqlreq);
    if($result) {
      $obj = $result->fetch_object();
      if($obj) {
        $requestcount = $obj->linecount;
      }
    }
    $MessageBody = "WU Posted $amount for account - $acctid";
    if ($requestcount > 0 ) {
         $MessageBody = $MessageBody.'<br><br>ACCOUNT HAS UNFILLED REQUESTS';
         $Subject = $Subject.' - ACCOUNT REQUIRES ACTION';
    }
    // if flat rate acct (non paygo svcplan_id = 0) and scott did not make the charge, alert stocc that flat rate acct has money added
    elseif ($svc_plan_id <> 1) {
         $MessageBody = $MessageBody.'<br><br> MONEY ADDED TO A FLAT RATE ACCOUNT';
         $Subject = $Subject.' - ACCOUNT MAY REQUIRE ACTION';
    }
    else {
         // we do not need to send an alert for approved/active accts
         $MessageBody = '';
    }
    if ($MessageBody != '') {
       $MessageBody = $MessageBody.'<br><br>'.$ResponseText.
            '<br><br>Prior Balance - '.$priorbalance.'<br>New Balance - '.$balance;
       procemail($Subject,$emailFromName,$MessageBody,'provision@rt.impactfax.com');
    }
  } else {
    echo "error";
  }
  exit;
}

if($_POST['action'] == 'get_possible_accounts') {

  $im_num = trim($_POST['im_num']);
  $im_num = $mysqli->real_escape_string($im_num);
  $accs = "error";

  $sqlpossibleaccts = "SELECT GROUP_CONCAT(CONCAT(acctid,'-',inmatename) SEPARATOR ',') AS accs FROM customer WHERE inmatenum = '$im_num'"; 
  $result = $mysqli->query($sqlpossibleaccts); 
  if($result) { 
    $obj = $result->fetch_object(); 
    if($obj) { 
      $accs = $obj->accs; 
    } 
  }
while($result->fetch_object());
$result->close();
echo $accs;
exit;
}


//----end ajax
}


//code
$result = false;
if(isset($_POST['go'])) {
  $result = true;
  $lines = preg_split ('/$\R?^/m', $_POST['text']);
  $items = array();
  $item = null;
  foreach ($lines as $line) {
    if(trim($line) == "From:") {
      $item = new stdClass();
      $items[] = $item; 
    }
    if(strpos(str_replace(' ','',$line),'Inmate:') !== false ) {
      $item->inmate = trim(substr($line,strpos($line,':')+1));
    }
    if(strpos(str_replace(' ','',$line),'Transfer/ReceiveAmount:') !== false ) {
      $item->amount = trim(substr($line,strpos($line,':')+1));
    }
    if(strpos(str_replace(' ','',$line),'ReferenceNumber:') !== false ) {
      $item->ref_number = trim(substr($line,strpos($line,':')+1));
    }
    if(strpos(str_replace(' ','',$line),'Receiver\'sState:') !== false ) {
      $item->state = trim(substr($line,strpos($line,':')+1));
      $item->state_color = '';
      if('OH' != $item->state) {
         $item->state_color = 'red';
      }
    }
  }
/*
//import to scv file
$now = gmdate("D, d M Y H:i:s");
$file_name = "file";
header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
header("Last-Modified: {$now} GMT");
header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename="'.$file_name.'.csv"');
header("Content-Transfer-Encoding: binary");
ob_start();
$df = fopen("php://output", 'w');
foreach ($items as $item) {
   fputcsv($df, (array) $item);
}
fclose($df);
echo ob_get_clean();
exit;
*/
}

function procemail($Subject,$emailFromName,$MessageBody,$addrlist) {

   $arrayEmailAddr=explode(",",$addrlist);

   $mail = new PHPMailer();
   $mail->IsSMTP();                                   // send via SMTP
   //$mail->Mailer = "mail";
   $mail->Host     = "F6_nonDB_svc;F5_nonDB_svc;localhost"; // SMTP servers
   $mail->SMTPAuth = false;     // turn on SMTP authentication
   $mail->From     ="CustomerService@VerizonVpartners.com";

   $mail->FromName = $emailFromName;  // Full Name of person
   foreach ($arrayEmailAddr as $EmailAddr) {
      $mail->AddAddress($EmailAddr,"");
   }
   //$mail->AddBCC("frankbrock@cinci.rr.com","");
   //$mail->AddCC("5135207379@vtext.com");
   $mail->WordWrap = 50;                              // set word wrap
   $mail->IsHTML(false);                               // send as HTML
   $mail->Subject  =  $Subject;
   $mail->Body     =  $MessageBody;
   $mail->AltBody  =  $MessageBody;

   if(!$mail->Send()) {
      echo "<br><br><span style='font-size:12.0pt;font-family:'Times New Roman''>";
      echo "Welcome Letter was not sent due to an error or there was no Email Address.</span><p>";
      echo "Submission Error: " . $mail->ErrorInfo;

      return false;
   }
   return true;
} //  end procemail()

?>
<html>
<body>
<?php if($result): ?>
<a href="mgfilter.php">Back</a>
<table border="1">
  <tr>
    <td>Inmate</td>
    <td>Transfer/Receive Amount</td>
    <td>State</td>
    <td>Reference Number</td>
    <td>I/M Num</td>
    <td>Possible Accounts</td>
  </tr>
<?php foreach($items as $i => $item): ?>
  <tr>
    <td id="inmate<?php echo $i;?>"><?php echo $item->inmate; ?></td>
    <td ><a id="amount<?php echo $i;?>" href="#" onclick="submit1(<?php echo $i;?>);return false;"><?php echo $item->amount; ?></a></td>
    <td bgcolor="<?php echo $item->state_color; ?>" id="state<?php echo $i;?>"><?php echo $item->state; ?></td>
    <td id="ref_number<?php echo $i;?>"><?php echo $item->ref_number; ?></td>
    <td><input id="im_num<?php echo $i;?>" type="text" size="8" onblur="get_poss_acc(<?php echo $i;?>);"></td>
    <td id="poss_acc<?php echo $i;?>">This is an automated message. Do not reply to this email. You must email your trustee with questions. I cannot answer any questions. I can only receive money. Received <?php echo $item->amount; ?></td>
  </tr>
<?php endforeach; ?>
</table>
<?php else: ?>
  <form method="post">
  <p>CorrLinks Money Gram message(s) text:</p>
  <textarea rows="30" cols="90" name="text"></textarea><br>
  <input type="submit" name="go" value="Submit">
  </form>
<?php endif ?>


<!-- JAVASCRIPTS -->
<script>
function getXmlDoc() {
  var xmlDoc;

  if (window.XMLHttpRequest) {
    // code for IE7+, Firefox, Chrome, Opera, Safari
    xmlDoc = new XMLHttpRequest();
  }
  else {
    // code for IE6, IE5
    xmlDoc = new ActiveXObject("Microsoft.XMLHTTP");
  }
  return xmlDoc;
}

function myPost(url, data, callback) {
  var xmlDoc = getXmlDoc();

  xmlDoc.open('POST', url, true);
  xmlDoc.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

  xmlDoc.onreadystatechange = function() {
    if (xmlDoc.readyState === 4 && xmlDoc.status === 200) {
      callback(xmlDoc.responseText);
    }
  }
  xmlDoc.send(data);
}

function submit1( i ) {
  var inmate = document.getElementById("inmate"+i).innerHTML;
  var amount = document.getElementById("amount"+i).innerHTML;
  var ref_number = document.getElementById("ref_number"+i).innerHTML;

  var result = prompt("Post Money Gram Amount to Account\nReference number - "+ ref_number +"\nAmount - "+ amount +"\nAcct Number:","");

  if(result != '') {
    var data = [];
    data.push(encodeURIComponent("action") + '=' + encodeURIComponent("submit"));
    data.push(encodeURIComponent("acctid") + '=' + encodeURIComponent(result));
    myPost("mgfilter.php", 
         data.join('&'),
         function(response) {
  
          if(response == "error") {
            alert("Customer is not found");
          } else {
            var confr = confirm("Confirm you want to post this amount to the account for "+response);
            if(confr) {
              data = [];
              data.push(encodeURIComponent("action") + '=' + encodeURIComponent("confirm"));
              data.push(encodeURIComponent("acctid") + '=' + encodeURIComponent(result));
              data.push(encodeURIComponent("amount") + '=' + encodeURIComponent(amount));
              data.push(encodeURIComponent("inmate") + '=' + encodeURIComponent(inmate));
              data.push(encodeURIComponent("ref_number") + '=' + encodeURIComponent(ref_number));
              myPost("mgfilter.php", 
                  data.join('&'),
                  function(response) {
                    if(response != "error") {
                      //alert(response);
                      var node = document.getElementById("amount"+i);
                      var td = node.parentNode;
                      td.innerHTML = amount;
                      //new popup
                      window.open('mgfilter.php?get_message=1&id='+response,'Message','location=0,menubar=0,status=0,scrollbars=0,width=500,height=300');
                    } else {
                      alert("Something went wrong");
                    }
                  });
            }
          }
         } );
  } else {
    alert("You didn't enter 'Acct Number'");
  }
  return false;
}

function get_poss_acc(i) {
  var im_num = document.getElementById("im_num"+i).value;
  if(im_num != '') {
    document.getElementById("poss_acc"+i).innerHTML = "Loading...";
    data = [];
    data.push(encodeURIComponent("action") + '=' + encodeURIComponent("get_possible_accounts"));
    data.push(encodeURIComponent("im_num") + '=' + encodeURIComponent(im_num));
    myPost("mgfilter.php", 
            data.join('&'),
            function(response) {
              if(response != "error") {
                document.getElementById("poss_acc"+i).innerHTML = response;
              } else {
                document.getElementById("poss_acc"+i).innerHTML = "error";
              }
    });
  }  
  return true;
}

</script>
</body>
</html>

