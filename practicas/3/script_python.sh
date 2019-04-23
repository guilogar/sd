#!/bin/bash

clear
echo "Setting up necesary stuff.."
echo "Installing python3"
    apt install python3
echo "Pyhton3 installed"

echo "Installing python-twitter"
pip3 install python-twitter
echo "Python-twitter installed"


echo "Installing pika"
pip3 install pika
echo "Pika installed"

echo "Python librarys installed and ready to use"