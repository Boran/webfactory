#Installation
##1. Install docker (OSX)

- Install docker locally (boot2docker for osx http://boot2docker.io/)
- Start docker (boot2docker ssh)

##2. Start webfactory

Create a new container for the webfactory website

    docker run -td -p 8000:80 -e "DRUPAL_GIT_REPO=https://github.com/Boran/webfactory.git" -e "DRUPAL_INSTALL_PROFILE=webfact" -e "DRUPAL_GIT_BRANCH=master" -e "VIRTUAL_HOST=webfact.local" -v /data -v /var -v /var/run/docker.sock:/var/run/docker.sock --restart=always --hostname=webfact --name=webfact boran/drupal

##3. Start nginx reverse proxy

In oder to correctly handle subdomains, create a new container for the nginx reverse proxy

    docker run -d -p 80:80 -v /var/run/docker.sock:/tmp/docker.sock --restart=always --hostname nginxproxy --name nginxproxy jwilder/nginx-proxy

On a local set-up you'll still need to manually create the entries into your hostfile (sudo vi /etc/hosts)

    webfact.local    192.168.59.103
    *.webfact.local    192.168.59.103

##3. Test it

Access the webfactory site 

    webfact.local:8000 (192.168.59.103:8000)

Now create your first site through the webfactory ui

(Don't forget to add a manual hostname entry or you won't be able to see the site)

##Additional commands
Access the webfact container
    
    docker exec -it webfact bash
