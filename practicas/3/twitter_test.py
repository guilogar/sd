import twitter

api = twitter.Api(consumer_key='qrxCRvTesdtF7h9TCna1I1GHn',
                  consumer_secret=
                      'nbQYCYZLCWSw5lGmnaR1lH7A3wgy9WDMEp0K4bbfOeW22avMa6',
                  access_token_key=
                      '1105477055477678080-ZBsM2hBBczpYfpLdLZqTByLXzRdQQO',
                  access_token_secret='yeMP0jjWeeQUVzi1YwXq151R0Gvx5G8YyWFK2azottfUt')

status = api.PostUpdate('I love pythontwitter!')
print(status.text)

statuses = api.GetUserTimeline(1105477055477678080, 'LunkV12')
print([s.text for s in statuses])
