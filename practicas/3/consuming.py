import pika
import json
import twitter
from furl import furl
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
    commit_data = json.loads(body)

    #Queues have to confirm message delivery
    channel.basic_ack(delivery_tag=method.delivery_tag)

    #Consumers needs to be closed or they'll be iterating forever
    channel.cancel()

    #Filtering data to only publish changes made by the repository owners
    if(credentials['github']['REPO_1'] or credentials['github']['REPO_2'] in commit_data['repo_name']):
        if(commit_data['commiter']['login'] == credentials['github']['REPO_1'] or credentials['github']['REPO_2']
        or credentials['github']['REPO_3']):
            on_twitter_publishing(commit_data['repo_name'], commit_data['commiter']['login'], 
            commit_data['html_url'])

            #Start working here, Teo
            #on_dropbox_storing(as) 
            #on_drive_storing()

def on_twitter_publishing(repo, commiter, url_raw):
    #Formating url
    #url = furl(url_raw)
    #Tweeting
    status = apiTwitter.PostUpdate(status='New Commit from: ' + commiter +  ' on: ' + repo + 
    ' follow link to discover the changes.\n' + url_raw)

def on_dropbox_storing(data): #Complete these functions, Teo
    pass

def on_drive_storing(data):
    pass

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
