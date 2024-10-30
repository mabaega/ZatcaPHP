import base64

# Input Base64 QR Code
base64_qr_code = "AQNMVEQCDzM5OTk5OTk5OTkwMDAwMwMTMjAyMi0wOC0xN1QxNzo0MTowOAQGMjMxLjE1BQUzMC4xNQYsNnJldnEvVG05MGVQWjV3OEtoT0U4RjBnY01EUXF4SVUraXFvMVlnRHlyQT0HYE1FWUNJUUN6ZEgrN2xKOENjbHR4bmRIb0lNMGxkQi9HbXgyUlNFajg2OENMUDRCUVdnSWhBT0hJcmsxSnQwUDNLNnJoNkNyZFlxVyt3YkxNQmFBWkFBWklyVFNndzYyOEovWghYMFYwEAYHKoZIzj0CAQYFK4EEAAoDQgAEoWCKa0Sa9FIErTOv0uAkC1VIKXxU9nPpx2vlf4yhMejy8c02XJblDq7tPydo8mq0ahOMmNo8gwni7Xt1KT9UeAlHMEUCIQCxP4nIZp1lwlClG3Gt8nIvKKsGi7xXR1Y0K73iPbqgGwIgPYQuDPI4DAQAz0s5ndrojyQOoCkdyxNN1O+Xqmwv61w="

# Dekode Base64
binary_data = base64.b64decode(base64_qr_code)

# Cetak hasil
print("Hasil Dekode QR Code Python:")
print(binary_data.decode('utf-8', errors='ignore'))  # Menggunakan 'ignore' untuk mengabaikan karakter yang tidak dapat didekode

# Cetak hex dump
hex_dump = " ".join(f"{byte:02x}" for byte in binary_data)
print("Hex Dump QR Code Python:")
print(hex_dump)