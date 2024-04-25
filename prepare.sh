#! /bin/bash

#create log files needed for keeping track of files (yup, this doesn't use any database softwarez!)
touch souko.log
touch count.log

#create directory where files are stored
if [ ! -e src ]
then
	mkdir src
fi
if [ ! -e thmb ]
then
	mkdir thmb
fi
