<?php
include "php_serial.class.php";

// Let's start the class
$serial = new phpSerial;

// First we must specify the device. This works on both linux and windows (if
// your linux serial device is /dev/ttyS0 for COM1, etc)
$serial->deviceSet("/dev/ttyACM1");
$serial->confBaudRate(9600);
// Then we need to open it
$serial->deviceOpen('w+');

stream_set_timeout($serial->_dHandle, 10);



// To write into

$serial->sendMessage("at\r",1);

// Or to read from
$read = $serial->readPort();

echo $read;

$serial->sendMessage("at+creg?\r",1);

// Or to read from
$read = $serial->readPort();

echo $read;

// If you want to change the configuration, the device must be closed
$serial->deviceClose();

// We can change the baud rate
//$serial->confBaudRate(2400);

// etc...
?>
