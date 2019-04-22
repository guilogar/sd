import pika
import json
import twitter
import urllib

#Global data needed
with open('twitter_credentials.json', 'r') as read_file:
    credentials = json.load(read_file)

#Login in twitter
api = twitter.Api(consumer_key=credentials["CONSUMER_KEY"],
                  consumer_secret=credentials["CONSUMER_SECRET"],
                  access_token_key=credentials["ACCESS_TOKEN_KEY"],
                  access_token_secret=credentials["ACCESS_TOKEN_SECRET"])

commit_data = {}
json_data = {}

#Here I receive the message from rabbit    
def on_consuming(channel, method, properties, body):
    #Storage data and dump it from json
    commit_data = json.loads(body)
    #Queues have to confirm message delivery
    channel.basic_ack(delivery_tag=method.delivery_tag)
    #Consumers needs to be closed or they'll be iterating forever
    channel.cancel()
    on_twitter_publishing(commit_data)

def on_twitter_publishing(data):
    url = urllib.parse.quote_plus(data['url'])
    status = api.PostUpdate(status='Hello world!\n'+data['url'])

#Rabbitmq connection
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
