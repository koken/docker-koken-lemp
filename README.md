# docker-koken-nginx

*This is a work in progress. Please check back later*.

This official Koken docker image installs the latest version of Koken and all necessary system requirements.

## Features

* Automatically sets up and configures the database for Koken and skips that step in the installation process.
* Adds a cron job to do periodic cleanup of the image cache.
* nginx/PHP configured for best Koken performance.
* Can be used on any machine with Docker installed.

## Usage

1. Install [Docker](https://www.docker.io/gettingstarted/#h_installation). Some hosts like Digital Ocean already have Docker available.
2. Start up a Koken container:

~~~bash
sudo docker run -p 80:8080 -dti bradleyboy/docker-koken-nginx /sbin/my_init
~~~

This forwards port 80 on your host machine to the instance of Koken running on port 8080 inside the container. You can now access your new Koken install by loading the IP address or domain name for your host in a browser.

### Using at Digital Ocean

Digital Ocean provides fast, low cost virtual servers that are well suited for Koken. To get started, create a Digital Ocean account, enter your billing information, then click **Create Droplet**.

1. Select the 512 / 1 CPU box size.
2. Select the Region closest to you.
3. Under **Select Image**, click the **Applications** tab and Select **Docker 0.11.1 for Ubuntu**.
4. If you are using SSH keys, select the appropriate keypair. Otherwise, Digital Ocean will email you your root password.
5. Leave **Settings** to their defaults.
6. Click **Create Droplet**.

Once the droplet is running, login as the root user and install the image. These instructions assume the IP is 1.1.1.1. Substitute your actual IP, which is listed on the Digital Ocean droplet page.

~~~bash
ssh root@1.1.1.1
docker run -p 80:8080 -dti bradleyboy/docker-koken-nginx /sbin/my_init
~~~

Once that completes, you can load Koken in your browser to complete the installation (again, substitute your IP address):

http://1.1.1.1