## Requirement

    - an 360dialog.com account
    - an Active Campaign account
    - a server where run this web project with php 8.1 or more
    
## Installation
Run 

    composer install

## Configuration of the project
Create a .env.local file and complete this values:

    D360_API_KEY= complete with your 360dialog.com API key 
    D360_TEMPLATE = complete with the template name created in 360dialog.com
    D360_NAMESPACE = complete with the namespace of 360dialog.com
    D360_LANGUAGE= complete with the language of the template created in 360dialog.com
    
    D360_DEFAULT_SEND= true for send all the messages to the same number, false to send to the active campaign user
    D360_DEFAULT_PHONE_NUMBER= phone number with international code used when D360_DEFAULT_SEND is true
    D360_HEADER_IMAGE= public link of an images used in the header of the template

### Example of configuration:

    D360_API_KEY=wxS5tmbzktwS5tmbzktw
    D360_TEMPLATE=onetemplate
    D360_NAMESPACE=4b9fc806_9356_4b91_8b9fc806_b9fc806
    D360_LANGUAGE=es
    D360_DEFAULT_SEND=true
    D360_DEFAULT_PHONE_NUMBER=34627500000
    D360_HEADER_IMAGE=https://activecampaign.wardcampbell.com/logomolaboda.jpg

As you can see the phone has 34... this is the spain code

The template used should have the following structure:

    - A variable image in the header,
    - Only one parameter in the body, this parameter is the name of the user


If you set D360_DEFAULT_SEND= false, the system will send the message to the active campaign user, remember the phone number should have the country code

## Configure this project like webhook

    Enter in your active campaign account and set a webhook with: [your url server]/webhook/active-campaign
    Configure the events, by example new user, or edition of a user
    The active campaign webhook should return name and phone number with country code. 







