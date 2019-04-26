import pika
import json
import twitter
import urllib
import dropbox
import pydrive

#Global data needed
with open('credentials.json', 'r') as read_file:
    credentials = json.load(read_file)

#Login in twitter
apiTwitter = twitter.Api(consumer_key=credentials['twitter']["CONSUMER_KEY"],
                  consumer_secret=credentials['twitter']["CONSUMER_SECRET"],
                  access_token_key=credentials['twitter']["ACCESS_TOKEN_KEY"],
                  access_token_secret=credentials['twitter']["ACCESS_TOKEN_SECRET"])

#Login in Dropbox
#apiDropbox = #Continue here..

commit_data = {}
json_data = {}

#Here I receive the message from rabbit    
def on_consuming(channel, method, properties, body):
    #Storage data and dump it from json
    type(body);
    print("\n\n");
    commit_data = json.loads(body)
    #Queues have to confirm message delivery
    channel.basic_ack(delivery_tag=method.delivery_tag)
    #Consumers needs to be closed or they'll be iterating forever
    channel.cancel()
    type(commit_data);
    print("\n\n");
    print(commit_data);
    #on_twitter_publishing(commit_data)
    #on_dropbox_storing(commit_data) #Function for Teodoro
    #on_drive_storing(commit_data)

def on_twitter_publishing(data):
    #url = urllib.parse.quote_plus(data['url'])
    status = apiTwitter.PostUpdate(status='New Commit from: ' + 'commiter' +  ' on: ' + 'repoName' + 
    ' follow link to discover the changes ' + 'repoURL')

def on_dropbox_storing(data): #Complete these functions, Teo
    pass;

def on_drive_storing(data):
    pass;

#Rabbitmq connection
parameters = pika.ConnectionParameters('localhost')
connection = pika.BlockingConnection(parameters)
channel = connection.channel()
channel.basic_consume(queue='github', on_message_callback=on_consuming)

#Spooling
try:
    channel.start_consuming()
    connection.sleep(5.0)   
except KeyboardInterrupt:
    channel.stop_consuming()
    connection.close()
