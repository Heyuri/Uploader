For installation you can follow this:

1- clone repo into web directory
2- cd into Uploader
3- run chmod +x prepare.sh
4- run the prepare.sh script
5- chown -R webuser:webuser /path/to/Uploader
6- DONE!!

After that it should work fine. You may want to change the $logfile's name from default "souko.log" into something else or block access to it, so it wouldn't be directly accessible from internet.
