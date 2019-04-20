import pika
import json
import twitter

api = twitter.Api(consumer_key='qrxCRvTesdtF7h9TCna1I1GHn',
                  consumer_secret='nbQYCYZLCWSw5lGmnaR1lH7A3wgy9WDMEp0K4bbfOeW22avMa6',
                  access_token_key='1105477055477678080-ZBsM2hBBczpYfpLdLZqTByLXzRdQQO',
                  access_token_secret='yeMP0jjWeeQUVzi1YwXq151R0Gvx5G8YyWFK2azottfUt')

commit_data = {}
json_data = {}

#Here I receive the message from rabbit    
def on_consuming(channel, method, properties, body):
    print (body)
    #Storage data and dump it to json - is this necesary?
    commit_data = body
    json_data = json.dumps(commit_data)
    #Queues have to confirm message delivery
    channel.basic_ack(delivery_tag=method.delivery_tag)
    #Consumers needs to be closed or they'll be iterating forever
    channel.cancel()
    on_twitter_publishing(json_data)

def on_twitter_publishing(data):


parameters = pika.ConnectionParameters('localhost')
connection = pika.BlockingConnection(parameters)
channel = connection.channel()
channel.basic_consume(queue='Testing', on_message_callback=on_consuming)

#Spooling
try:
    channel.start_consuming()
    connection.sleep(5.0)   
except KeyboardInterrupt:
    channel.stop_consuming()
    connection.close()
