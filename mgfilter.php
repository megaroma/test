<?php
//config
$host = '127.0.0.1'; $port = '4040'; $user = 'scott'; $pass = 'brutus1'; $db = 'pactel';

$mysqli = new mysqli($host,$user,$pass,$db,$port);

//ajax code
if(isset($_POST['action'])) {


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

if($_POST['action'] == 'confirm') {
  $acctid = trim($_POST['acctid']);
  $acctid = $mysqli->real_escape_string($acctid);
  $amount = trim($_POST['amount']);
  $amount = $mysqli->real_escape_string($amount); 
  $amount = trim($amount,"$");
  
  $sqlbal = "select balance from customer where acctid = '$acctid'"; 
  $result = $mysqli->query($sqlbal); 
  if($result) { 
    $obj = $result->fetch_object(); 
    if($obj) { 
      $balance = $obj->balance; 
    } 
  }
while($result->fetch_object());
$result->close();
    
  $ref_number = trim($_POST['ref_number']);
  $ref_number = $mysqli->real_escape_string($ref_number);
  $sql = "call insertCustomerCredit('$acctid' ,'WU' ,'$ref_number' , '$amount' ,'- (PriorBal $balance ) ','$amount' )";
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
    echo $response;
  } else {
    echo "error";
  }
  exit;
}


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

?>
<html>
<body>
<?php if($result): ?>
<a href="mgfilter.php">Back</a>
<table border="1">
  <tr>
    <td>Inmate</td>
    <td>Transfer/Receive Amount</td>
    <td>Reference Number</td>
    <td>I/M Email</td>
  </tr>
<?php foreach($items as $i => $item): ?>
  <tr>
    <td id="inmate<?php echo $i;?>"><?php echo $item->inmate; ?></td>
    <td ><a id="amount<?php echo $i;?>" href="#" onclick="submit1(<?php echo $i;?>);return false;"><?php echo $item->amount; ?></a></td>
    <td id="ref_number<?php echo $i;?>"><?php echo $item->ref_number; ?></td>
    <td>This is an automated message. Do not reply to this email. You must email your trustee with questions. I cannot answer any questions. I can only receive money. Received <?php echo $item->amount; ?></td>
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
              data.push(encodeURIComponent("ref_number") + '=' + encodeURIComponent(ref_number));
              myPost("mgfilter.php", 
                  data.join('&'),
                  function(response) {
                    if(response != "error") {
                      alert(response);
                      var node = document.getElementById("amount"+i);
                      var td = node.parentNode;
                      td.innerHTML = amount;
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

</script>

</body>
</html>
