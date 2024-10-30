<?php

// Input Base64 QR Code
$base64QrCode = "AQNMVEQCDzM5OTk5OTk5OTkwMDAwMwMTMjAyMi0wOC0xN1QxNzo0MTowOAQGMjMxLjE1BQUzMC4xNQYsNnJldnEvVG05MGVQWjV3OEtoT0U4RjBnY01EUXF4SVUraXFvMVlnRHlyQT0HYE1FWUNJUUN6ZEgrN2xKOENjbHR4bmRIb0lNMGxkQi9HbXgyUlNFajg2OENMUDRCUVdnSWhBT0hJcmsxSnQwUDNLNnJoNkNyZFlxVyt3YkxNQmFBWkFBWklyVFNndzYyOEovWghYMFYwEAYHKoZIzj0CAQYFK4EEAAoDQgAEoWCKa0Sa9FIErTOv0uAkC1VIKXxU9nPpx2vlf4yhMejy8c02XJblDq7tPydo8mq0ahOMmNo8gwni7Xt1KT9UeAlHMEUCIQCxP4nIZp1lwlClG3Gt8nIvKKsGi7xXR1Y0K73iPbqgGwIgPYQuDPI4DAQAz0s5ndrojyQOoCkdyxNN1O+Xqmwv61w=";

// Dekode Base64
$binaryData = base64_decode($base64QrCode);

// Cetak hasil
echo "Hasil Dekode QR Code PHP:\n";
echo $binaryData . "\n";

// Cetak hex dump
$hexDump = unpack('H*', $binaryData)[1];
echo "Hex Dump QR Code PHP:\n" . chunk_split($hexDump, 2, ' ') . "\n";

?>