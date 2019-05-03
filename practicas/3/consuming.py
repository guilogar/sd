import pika
import json
import twitter
import dropbox
import urllib
import os
#Global data needed
with open('credentials.json', 'r') as read_file:
    credentials = json.load(read_file)

#Login in twitter
apiTwitter = twitter.Api(consumer_key=credentials['twitter']["CONSUMER_KEY"],
                  consumer_secret=credentials['twitter']["CONSUMER_SECRET"],
                  access_token_key=credentials['twitter']["ACCESS_TOKEN_KEY"],
                  access_token_secret=credentials['twitter']["ACCESS_TOKEN_SECRET"])

#Login in Dropbox
dbx = dropbox.Dropbox(credentials['dropbox']["ACCESS_TOKEN"])

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
    if((credentials['github']['REPO_1'] in commit_data['repo_name'])
    or (credentials['github']['REPO_2'] in commit_data['repo_name'])):
        if((commit_data['commiter']['login'] == (credentials['github']['REPO_1']) or
        (commit_data['commiter']['login'] == credentials['github']['REPO_2']) or
        (commit_data['commiter']['login'] == credentials['github']['REPO_3']))):
            on_twitter_publishing(commit_data['repo_name'], commit_data['commiter']['login'], commit_data['html_url'])
            on_dropbox_storing(commit_data['files'])

def on_twitter_publishing(repo, commiter, url_raw):
    #Tweeting
    status = apiTwitter.PostUpdate(status='New Commit from: ' + commiter +  ' on: ' + repo +
    ' follow link to discover the changes.\n' + url_raw)

def on_dropbox_storing(datafiles):
	#Check that dropbox login has been successful
	try:
		dbx.users_get_current_account()
	except dropbox.exceptions.AuthError as err:
		print("ERROR: Could not login to Dropbox.")

	#Get every file that the json objects indicates
	for files in datafiles:
		#Download the file from url in local directory
		urllib.request.urlretrieve(files["raw_url"], "gitfile")

		#Open the downloaded file and upload it to dropbox
		with open('gitfile', "rb") as upload_file:
			dbx.files_upload(upload_file.read(), "/" + files["filename"], mode = dropbox.files.WriteMode.overwrite, mute = True)
		#Remove the downloaded file
		os.remove("gitfile")

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
