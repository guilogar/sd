#just trying the commit app

#Library to manage Rabbitmq
import pika

channel = None

#Functions previously declared 
def on_connected(connection):
    connection.channel(on_channel_open)

def on_channel_open(new_channel):
    global channel
    channel = new_channel
    channel.queue_declare(queue = "test", durable=True, exclusive=False, 
    auto_delete=False, callback=on_queue_declared)

def on_queue_declared(frame):
    channel.basic_consume('test', handle_delivery)
    print("This consumes")

#Connection - Similar to main()
parameters = pika.ConnectionParameters('localhost')
connection = pika.Connection(parameters)

#try:
#    connection.ioloop.start()
#except KeyboardInterrupt:
#    connection.close()
#    print("\nSee you!")
#    connection.ioloop.start()