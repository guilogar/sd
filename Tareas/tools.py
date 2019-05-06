PORT = 40000

def clock_to_bytes(datetime):
    return datetime.to_datetime_string().encode("utf-8")

def receive(sock):
    buffer, address = sock.recvfrom(1000)
    return buffer.decode("utf-8")
