import pika
import json

data1 = {}
parameters = pika.ConnectionParameters('localhost')
connection = pika.BlockingConnection(parameters)
channel = connection.channel()
channel.queue_declare(queue='Testing')

data = {
    'url' : 'www.github.com/guille31794.io',
    'ip' : '127.0.0.1',
    'something_else' : 3.0
}

i = 0
json_data = json.dumps(data)

while True:
    try:
        channel.basic_publish(exchange='', routing_key='Testing', body=json_data)
        connection.sleep(1.0)
        print ("Sendind data: ", i)
        i = i + 1
    except KeyboardInterrupt:
        channel.cancel()
        connection.close()