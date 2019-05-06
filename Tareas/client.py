import socket
import pendulum
import random
import tools

dst = ("localhost", tools.PORT)
# Obtengo la hora actual (para españoles)
current = pendulum.now("Europe/Madrid")
# Le meto un incremento aleatorio
offset = random.randrange(-10, 10)
my_clock = current.add(seconds=offset)
# Me conecto con el servidor
sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
sock.sendto(tools.clock_to_bytes(my_clock), dst)
msg = tools.receive(sock)
print(msg)
buffer = tools.receive(sock)
my_new_clock = pendulum.parse(buffer)
print("{} -> {}".format(my_clock.to_time_string(), my_new_clock.to_time_string()))
