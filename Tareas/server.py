import socket
import pendulum
import tools

def final_clock(clocks):
    return clocks[0]

sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
sock_response = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
sock.bind(("localhost", tools.PORT))
MAX_CLIENT = 2
BUFFER_SIZE=1000
received = []
clocks = []

for i in range(MAX_CLIENT):
   data, address = sock.recvfrom(BUFFER_SIZE)
   its_clock = pendulum.parse(data.decode("utf-8"))
   print("Received " +its_clock.to_time_string() +" from : " +str(address))
   clocks.append(its_clock)
   received.append(address)
   sock_response.sendto(b"Recibido", address)
   
# Decido lo que hacer
final_clock = final_clock(clocks)
print("Final clock: {}".format(final_clock))

# Lo envio a los clientes esperando

sock_response.close()
