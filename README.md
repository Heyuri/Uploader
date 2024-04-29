this repo uses flatfiles for storage of data. this may include users IP.
to protect on this i have the extention for anything with sensitive text with .block

this should be some where in ur site configs if you are using nginx
```
    location ~* \.block$ {
        deny all;
    }
```

## For installation you can follow this:

1- clone repo into web directory<br/>
2- set everything to use web user `chown -R webuser:webuser /path/to/Uploader`<br/>
3- edit your superAdmin password in the basse config <br/>
4- change some limits in php.ini acording to how much upload you want

After that it should work fine.
